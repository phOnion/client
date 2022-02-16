<?php

use GuzzleHttp\Psr7\{Message, Request, Uri, Utils};
use Onion\Framework\Client\{Client, Contexts\SecureContext};
use Psr\Http\Message\{RequestInterface, ResponseInterface};

use function Onion\Framework\Client\gethostbyname;
use function Onion\Framework\Loop\{coroutine, scheduler, tick};

require_once __DIR__ . '/../vendor/autoload.php';

class HttpClient
{
    private const ADDRESS_VALIDITY_REGEX = '/^(?P<scheme>https?)\:\/\/((?P<address>[a-z0-9_.-]+|\[[a-f0-9:]+\])(\:(?P<port>\d+))?)?(?P<rest>.*)$/i';
    public function __construct(private readonly array $options = [
        'verify' => ['peer' => true, 'name' => true, 'depth' => 5, 'fingerprint' => null],
        'local' => ['cert' => null, 'key' => null, 'passphrase' => null],
        'capture' => ['peer' => false, 'chain' => false],
    ])
    {
    }

    public function send(RequestInterface $request): ResponseInterface
    {
        $matches = [];
        if (!preg_match(static::ADDRESS_VALIDITY_REGEX, (string) $request->getUri(), $matches) === 0) {
            throw new \InvalidArgumentException("Invalid address '{$request->getUri()}' provided");
        }

        $contexts = [];
        $scheme = strtolower($matches['scheme']);
        $port = !$matches['port'] ? match ($scheme) {
            'http' => 80,
            'https' => 443,
        }
            : (int) $matches['port'];
        $server = gethostbyname($matches['address']);

        if ($scheme === 'https') {
            $ctx = $this->getSecurityContext($this->options);
            $ctx->setPeerName($request->getUri()->getHost());
            $ctx->setSniEnable(true);
            $ctx->setSniServerName($request->getUri()->getHost());
            $contexts[] = $ctx->getContextArray();
        }

        $result = Client::send(
            "tcp://{$server}:{$port}",
            Message::toString($request),
            contexts: array_merge(...$contexts),
        );

        $message = '';
        while (!$result->eof()) {
            $message .= $result->read(8192);
            tick();

            if (substr($message, -4, 4) === "\r\n\r\n") {
                break;
            }
        }

        $response = Message::parseResponse($message);

        if ($response->getStatusCode() >= 300 && $response->hasHeader('Location')) {
            return $this->send(
                $request->withUri(
                    new Uri($response->getHeaderLine('location')),
                ),
            );
        }

        if ($response->hasHeader('transfer-encoding') && $response->getHeaderLine('transfer-encoding') === 'chunked') {
            $body = Utils::streamFor('');
            $content = $response->getBody()->getContents();

            for (; !empty($content); $content = trim($content)) {
                $pos = stripos($content, "\r\n");
                $len = hexdec(substr($content, 0, $pos));
                $body->write(substr($content, $pos + 2, $len));
                $content = substr($content, $pos + 2 + $len);

                tick();
            }

            $response = $response->withBody($body);
        }

        return $response;
    }

    private function getSecurityContext(array $options): SecureContext
    {
        $ctx = new SecureContext();

        if (isset($options['verify']['peer'])) $ctx->setVerifyPeer($options['verify']['peer']);
        if (isset($options['verify']['name'])) $ctx->setVerifyPeerName($options['verify']['name']);
        if (isset($options['verify']['depth'])) $ctx->setVerifyDepth($options['verify']['depth']);
        if (isset($options['verify']['fingerprint'])) $ctx->setPeerFingerprint($options['verify']['fingerprint']);

        if (isset($options['local']['cert'])) $ctx->setLocalCert($options['local']['cert']);
        if (isset($options['local']['key'])) $ctx->setLocalKey($options['local']['key']);
        if (isset($options['local']['passphrase'])) $ctx->setLocalKeyPassphrase($options['local']['passphrase']);

        if (isset($options['capture']['peer'])) $ctx->setPeerCertCapture($options['capture']['peer']);
        if (isset($options['capture']['chain'])) $ctx->setPeerCertChainCapture($options['capture']['chain']);

        return $ctx;
    }
}

coroutine(function () {
    $client = new HttpClient();
    $req = new Request('GET', 'https://api.publicapis.org/entries', [
        'accept' => '*/*',
        'user-agent' => 'test',
    ]);
    $start = microtime(true);
    var_dump($client->send($req)->getBody()->getSize());
    echo microtime(true) - $start;
});

scheduler()->start();

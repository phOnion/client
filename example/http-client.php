<?php

use GuzzleHttp\Psr7\{Message, Request, Uri, Utils};
use Onion\Framework\Client\{Client, Contexts\SecureContext};
use Psr\Http\Message\{RequestInterface, ResponseInterface};

use function Onion\Framework\Client\gethostbyname;
use function Onion\Framework\Loop\{coroutine, scheduler, suspend, read, write};

require_once __DIR__ . '/../vendor/autoload.php';

scheduler(new \Onion\Framework\Loop\Scheduler\Select());
scheduler()->addErrorHandler(fn (Throwable $ex) => var_dump($ex->getMessage()));

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
            $server = \gethostbyname($matches['address']);

        if ($scheme === 'https') {
            $ctx = $this->getSecurityContext($this->options);
            $ctx->setPeerName($request->getUri()->getHost());
            $ctx->setSniEnable(true);
            $ctx->setSniServerName($request->getUri()->getHost());
            $contexts[] = $ctx;
        }

        $client = Client::connect(
            "tcp://{$server}:{$port}",
            ...$contexts,
        );

        write($client, Message::toString($request));
        $message = read($client, function ($client) use (&$result) {
            $headers = '';
            while (!$client->eof()) {
                $headers .= $client->read(1);
                suspend();

                if (substr($headers, -4, 4) === "\r\n\r\n") {
                    break;
                }
            }

            return $headers;
        });

        $response = Message::parseResponse($message);

        if ($response->getStatusCode() >= 300 && $response->hasHeader('Location')) {
            // handle redirects
            return $this->send(
                $request->withUri(
                    new Uri($response->getHeaderLine('location')),
                ),
            );
        }

        if ($response->hasHeader('transfer-encoding') && $response->getHeaderLine('transfer-encoding') === 'chunked') {
            $response = $response->withBody(read($client, function (\Onion\Framework\Loop\Interfaces\ResourceInterface $client) {
                $body = Utils::streamFor('');

                $size = '';
                while ($size !== '0') {
                    $chunk = $client->read(1);
                    if ($chunk === "\r" && $size !== '0') {
                        $client->read(1);
                        $length = hexdec(trim($size)) + 2;

                        $chunk = '';
                        $size = '';
                        while (strlen($chunk) < $length) {
                            $chunk .= $client->read($length - strlen($chunk));
                            suspend();
                        }

                        $body->write(trim($chunk));
                        continue;
                    }

                    // suspend();
                    $size .= $chunk;
                }

                $body->rewind();
                return $body;
            }));
        }

        $client->close();

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

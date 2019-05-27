<?php
namespace Onion\Framework\Client;

use function GuzzleHttp\Psr7\parse_response;
use function GuzzleHttp\Psr7\str;
use function Onion\Framework\Promise\promise;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Stream\StreamInterface;
use Onion\Framework\Promise\Interfaces\PromiseInterface;
use Onion\Framework\Promise\Promise;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

class HttpClient
{
    private $connection;
    private $preferTls;

    public function __construct(bool $preferTls = true)
    {
        $this->preferTls = $preferTls;
    }

    private function getConnection(UriInterface $uri, callable $callback)
    {
        if ($this->connection === null) {
            $host = $uri->getHost();
            $port = $uri->getPort() ?: ($uri->getScheme() === 'http' ? 80 : 443);
            $type = Client::TYPE_TCP;
            if ($uri->getScheme() === 'https') {
                $type = $type | ($this->preferTls ? Client::SECURE_TLS : Client::SECURE_SSL);
            }
            $this->connection = new Client($type, "{$host}:{$port}");
            $this->connection->on('data', function (StreamInterface $stream) use ($callback) {
                $message = '';
                while (!$stream->eof() && ($data = $stream->read(8192)) !== '') {
                    $message .= $data;
                }

                call_user_func($callback, parse_response($message));
            });
        }

        return $this->connection;
    }

    public function send(RequestInterface $request): PromiseInterface
    {
        return new Promise(function ($resolve, $reject) use ($request) {
            try {
                $this->getConnection($request->getUri(), $resolve)
                    ->send(str($request))
                    ->otherwise($reject);
            } catch (\Throwable $ex) {
                $reject($ex);
            }
        });
    }
}

<?php
namespace Onion\Framework\Client;

use function Onion\Framework\EventLoop\attach;
use function Onion\Framework\EventLoop\detach;
use GuzzleHttp\Stream\Stream;
use GuzzleHttp\Stream\StreamInterface;
use function Onion\Framework\Promise\async;
use Onion\Framework\Promise\Interfaces\PromiseInterface;

/**
 * EVENTS:
 *  - connect
 *  - data
 *  - close
 *
 */
class Client
{
    public const TYPE_TCP = 1;
    public const TYPE_UDP = 2;
    public const TYPE_SOCK = 4;
    public const TYPE_SECURE = 8;

    private const DEFAULT_OPTIONS = [
        'verify_peer' => true,
        'allow_self_signed' => false,
        'verify_depth' => 10,
        'ca_file' => null,
    ];

    private $type;
    private $remote;
    private $timeout;
    private $listeners = [];
    private $options = [];

    private $streams = [];


    public function __construct(int $type, string $remote, int $timeout = 10, array $options = [])
    {
        if (
            ($type & self::TYPE_SOCK) !== self::TYPE_SOCK &&
            ($type & self::TYPE_TCP) !== self::TYPE_TCP &&
            ($type & self::TYPE_UDP) !== self::TYPE_UDP
        ) {
            throw new \InvalidArgumentException("Invalid server type provided");
        }

        $this->type = $type;
        $this->timeout = $timeout;
        $this->remote = $remote;
        $this->listeners = [
            'connect' => function () {},
            'data' => function () {},
            'close' => function () {},
        ];

        $this->options = array_merge($options, self::DEFAULT_OPTIONS);
    }

    private function trigger(string $event, ... $args)
    {
        if (!isset($this->listeners[$event])) {
            throw new \InvalidArgumentException("Unknown event name '{$event}'");
        }

        call_user_func($this->listeners[$event], ... $args);
    }

    public function on(string $event, callable $callback): void
    {
        $this->listeners[strtolower($event)] = $callback;
    }

    public function connect(): PromiseInterface
    {
        return async(function () {
            $secure = ($this->type & self::TYPE_SECURE) === self::TYPE_SECURE;
            $context = stream_context_create();

            foreach ($this->options as $key => $value) {
                stream_context_set_option($context, 'ssl', $key, $value);
            }

            if (($this->type & self::TYPE_TCP) === self::TYPE_TCP) {
                $type = "tcp";
            }

            if (($this->type & self::TYPE_UDP) === self::TYPE_UDP) {
                $type = 'udp';
            }

            if (($this->type & self::TYPE_SOCK) === self::TYPE_SOCK) {
                $type = 'unix';
            }

            $socket = stream_socket_client(
                "{$type}://{$this->remote}",
                $errno,
                $message,
                $this->timeout,
                STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT,
                $context
            );

            if (!$socket) {
                throw new \RuntimeException("Unable to connect: {$message} ($errno)", $errno);
            }

            if ($secure) {
                stream_set_blocking($socket, true);
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_ANY_CLIENT);
            }
            stream_set_blocking($socket, false);

            if (!$socket) {
                throw new \RuntimeException("Unable to connect: {$message} ({$errno})");
            }

            return new Stream($socket);
        })->then(function (StreamInterface $stream) {
            $this->trigger('connect', $stream);
        })->then(function (StreamInterface $stream) {
            attach($stream, function (StreamInterface $stream) {
                if ($stream->eof()) {
                    $this->trigger('close');
                    detach($stream);
                    return;
                }

                $this->trigger('data', $stream);
            });
        })->then(function (StreamInterface $stream) {
            foreach ($this->streams as $watched) {
                list($watched, $duplex)=$watched;

                attach($watched, function (StreamInterface $payload) use ($stream) {
                    $eof = false;
                    while (!$eof) {
                        $chunk = $payload->read(1024);
                        if ($chunk === '') {
                            $eof = false;
                        }
                    }

                    $stream->write($payload->read(8192));
                });
            }
        });
    }

    public function watch(StreamInterface $streamInterface, bool $duplex = false)
    {
        $this->streams[] = [$streamInterface, $duplex];
    }
}

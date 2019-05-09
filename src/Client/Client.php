<?php
namespace Onion\Framework\Client;

use function Onion\Framework\EventLoop\attach;
use function Onion\Framework\EventLoop\detach;
use function Onion\Framework\Promise\async;
use GuzzleHttp\Stream\Stream;
use GuzzleHttp\Stream\StreamInterface;
use Onion\Framework\Client\Interfaces\ClientInterface;
use Onion\Framework\Promise\Interfaces\PromiseInterface;

/**
 * EVENTS:
 *  - connect
 *  - data
 *  - close
 */
class Client implements ClientInterface
{
    public const TYPE_TCP = 1;
    public const TYPE_UDP = 2;
    public const TYPE_SOCK = 4;

    public const SECURE_SSL = 8;
    public const SECURE_TLS = 16;

    private const DEFAULT_OPTIONS = [
        'verify_peer' => true,
        'allow_self_signed' => false,
        'verify_depth' => 10,
        'ca_file' => null,
    ];

    private const EXPOSED_EVENTS = [
        'connect',
        'data',
        'close',
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
        $event = strtolower($event);
        if (!in_array($event, static::EXPOSED_EVENTS, true)) {
            throw new \InvalidArgumentException(sprintf(
                "Event '%s' is not supported, supported are %s",
                $event,
                implode(', ', static::EXPOSED_EVENTS)
            ));
        }

        $this->listeners[strtolower($event)] = $callback;
    }

    public function connect(): PromiseInterface
    {
        return async(function () {
            $context = stream_context_create();

            foreach ($this->options as $key => $value) {
                stream_context_set_option($context, 'ssl', $key, $value);
            }

            if (($this->type & self::TYPE_TCP) === self::TYPE_TCP) {
                $type = "tcp";
                if (($this->type & static::SECURE_SSL) === static::SECURE_SSL) {
                    $type = 'ssl';
                }

                if (($this->type & static::SECURE_TLS) === static::SECURE_TLS) {
                    $type = 'tls';
                }
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
            stream_set_blocking($socket, false);

            if (!$socket) {
                throw new \RuntimeException("Unable to connect: {$message} ($errno)", $errno);
            }

            return new Stream($socket);
        })->then(function (StreamInterface $stream) {
            attach($stream, function (StreamInterface $stream) {
                $channel = $stream;
                if (($this->type & static::TYPE_UDP) === static::TYPE_UDP) {
                    $channel = new Packet($stream);
                }

                if (!$channel instanceof Packet) {
                    $this->trigger('connect', $channel);
                }

                attach($stream, function (StreamInterface $stream) {
                    if ($stream->eof()) {
                        $this->trigger('close');
                        detach($stream);
                        return;
                    }

                    if (($this->type & static::TYPE_UDP) === static::TYPE_UDP) {
                        $channel = new Packet($stream);
                    }

                    $this->trigger('data', $channel ?? $stream);
                    detach($stream);
                });
            });
        })->then(function (StreamInterface $stream) {
            foreach ($this->streams as $watched) {
                attach($watched, function (StreamInterface $payload) use ($stream) {
                    $eof = false;
                    while (!$eof) {
                        $chunk = $payload->read(1024);
                        $stream->write($chunk);

                        if ($chunk === '') {
                            $eof = true;
                        }
                    }
                });
            }
        })->then(function (StreamInterface $stream) {
            if (($this->type & static::TYPE_UDP) === static::TYPE_UDP) {
                $stream = new Packet($stream);
            }

            return $stream;
        });
    }

    public function proxy(StreamInterface $streamInterface): void
    {
        $this->streams[] = $streamInterface;
    }

    public function send(string $data)
    {
        return $this->connect()->then(function ($channel) use ($data) {
            if ($channel instanceof Packet) {
                $channel->send($data, $channel->getAddress(true));
            }

            if ($channel instanceof StreamInterface) {
                $channel->write($data);
            }
        })->then(function () {
            return $this;
        });
    }
}

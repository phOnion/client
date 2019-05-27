<?php
namespace Onion\Framework\Client;

use function Onion\Framework\EventLoop\detach;

class Connection
{
    private $target;
    private $timeout;
    private $context;

    private $connection;

    public function __construct(string $target, int $timeout, array $options = [])
    {
        $this->target = $target;
        $this->timeout = $timeout;

        $context = stream_context_create();
        foreach ($options as $key => $value) {
            stream_context_set_option($context, 'ssl', $key, $value);
        }

        $this->context = $context;
    }

    public function send(string $data)
    {
        if (!$this->isConnected()) {
            $socket = stream_socket_client(
                $this->target,
                $errno,
                $message,
                $this->timeout,
                STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT,
                $this->context
            );
            stream_set_blocking($socket, false);

            if (!$socket) {
                throw new \RuntimeException("Unable to connect: {$message} ($errno)", $errno);
            }
            $this->connection = new Stream($socket);
        }
    }
    public function receive(int $size = -1) {}
    public function disconnect(): void
    {
        if ($this->isConnected()) {
            detach($this->connection);
            $this->connection->close();
        }
    }

    private function isConnected(): bool
    {
        return $this->connection !== null;
    }
}

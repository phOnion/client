<?php
namespace Onion\Framework\Client\Adapters;

use function Onion\Framework\Loop\async;
use Onion\Framework\Client\Adapters\AdapterTrait;
use Onion\Framework\Client\Interfaces\AdapterInterface;
use Onion\Framework\Client\Interfaces\ContextInterface;
use Onion\Framework\Loop\Descriptor;
use Onion\Framework\Loop\Interfaces\ResourceInterface;
use Onion\Framework\Loop\Signal;

class UdpAdapter implements AdapterInterface
{
    private $contexts;

    protected const SOCKET_FLAGS = STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT | STREAM_CLIENT_PERSISTENT;

    use AdapterTrait;

    public function __construct(ContextInterface ...$contexts)
    {
        $this->contexts = $contexts;
    }

    public function getAddress(string $address, ?int $port = null): string
    {
        return file_exists($address) ? "unix://{$address}" : "udp://{$address}:{$port}";
    }

    public function send(string $data, string $address, ?int $port = null, int $timeout = 5): Signal
    {
        return async(function () use ($data, $address, $port, $timeout) {
            $connection = $this->createSocket($address, $port, $timeout, $this->contexts);
            $length = 0;
            $total = strlen($data);
            yield $connection->wait(ResourceInterface::OPERATION_WRITE);

            while ($length !== $total) {
                $length += $connection->write(substr($data, $length));
            }

            yield $connection->wait();

            $message = '';
            while (($packet = $connection->read(8192))) {
                $message .= $packet;
            }

            return $message;
        });
    }
}

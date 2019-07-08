<?php
namespace Onion\Framework\Client\Adapters;

use Onion\Framework\Client\Interfaces\ContextInterface;
use Onion\Framework\Loop\Interfaces\ResourceInterface;
use Onion\Framework\Loop\Socket;

trait AdapterTrait
{
    protected function createSocket(string $address, ?int $port = null, int $timeout = 5,  array $contexts = []): ResourceInterface
    {
        $ctx = [];
        foreach ($contexts as $context) {
            /** @var ContextInterface $context */
            $ctx = array_merge($ctx, $context->getContextArray());
        }

        $client = stream_socket_client(
            "{$this->getAddress($address, $port)}",
            $errno,
            $errstr,
            $timeout,
            static::SOCKET_FLAGS,
            stream_context_create($ctx)
        );

        if (!$client) {
            throw new \RuntimeException($errstr, $errno);
        }

        $connection = new Socket($client);
        $connection->unblock();

        return $connection;
    }
}

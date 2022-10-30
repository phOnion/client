<?php

declare(strict_types=1);

namespace Onion\Framework\Client;

use Onion\Framework\Client\Interfaces\ClientInterface;
use Onion\Framework\Client\Interfaces\ContextInterface;
use Onion\Framework\Loop\Descriptor;
use Onion\Framework\Loop\Interfaces\ResourceInterface;
use Onion\Framework\Loop\Types\Operation;

use function Onion\Framework\Loop\tick;

class Client implements ClientInterface
{
    private static $cryptoClientProtocol;

    public static function setClientCryptoProtocol(int $protocol): void
    {
        static::$cryptoClientProtocol = $protocol;
    }

    public static function connect(string $address, ?float $timeout, ContextInterface ...$context): ResourceInterface
    {
        /** @var array $contexts */
        $contexts = array_merge(
            ...array_map(
                fn (ContextInterface $ctx) => $ctx->getContextArray(),
                $context
            )
        );

        $client = stream_socket_client(
            $address,
            $code,
            $error,
            $timeout,
            STREAM_CLIENT_CONNECT,
            stream_context_create($contexts),
        );

        if (!$client) {
            throw new \RuntimeException($error, $code);
        }

        $connection = new Descriptor($client);
        $connection->unblock();

        if (isset($contexts['ssl'])) {
            do {
                $connection->wait(Operation::WRITE);
                $crypto = stream_socket_enable_crypto(
                    $connection->getResource(),
                    true,
                    static::$cryptoClientProtocol ?? static::DEFAULT_STREAM_CRYPTO,
                    $connection->getResource(),
                );
                tick();
            } while ($crypto === 0);
        }

        return $connection;
    }
    public static function send(
        string $address,
        string $data,
        ?float $timeout = null,
        array $contexts = [],
    ): Descriptor {
        $connection = static::connect($address, $timeout, ...$contexts);

        $connection->wait(Operation::WRITE);
        $chunks = str_split($data, 1024);
        foreach ($chunks as $chunk) {
            $offset = 0;
            while ($offset !== strlen($chunk)) {
                $offset += $connection->write(substr($chunk, $offset));
                tick();
            }
        }

        $connection->wait();

        return $connection;
    }
}

<?php

declare(strict_types=1);

namespace Onion\Framework\Client;

use Onion\Framework\Client\Interfaces\ClientInterface;
use Onion\Framework\Client\Interfaces\ContextInterface;
use Onion\Framework\Loop\Descriptor;
use Onion\Framework\Loop\Types\Operation;

use function Onion\Framework\Loop\tick;

class Client implements ClientInterface
{
    public static function send(
        string $address,
        string $data,
        ?float $timeout = null,
        array $contexts = [],
    ): Descriptor {
        $client = stream_socket_client(
            $address,
            $code,
            $error,
            $timeout,
            STREAM_CLIENT_CONNECT,
            stream_context_create(
                array_merge(
                    ...array_map(
                        fn (ContextInterface $ctx) => $ctx->getContextArray(),
                        $contexts
                    )
                )
            ),
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
                    STREAM_CRYPTO_METHOD_TLS_CLIENT,
                    $connection->getResource(),
                );
            } while ($crypto === 0);
        }

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

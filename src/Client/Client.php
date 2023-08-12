<?php

declare(strict_types=1);

namespace Onion\Framework\Client;

use Closure;
use Onion\Framework\Client\Contexts\AggregateContext;
use Onion\Framework\Client\Interfaces\ClientInterface;
use Onion\Framework\Client\Interfaces\ContextInterface;
use Onion\Framework\Loop\Interfaces\{ResourceInterface, TaskInterface, SchedulerInterface};
use Onion\Framework\Loop\Types\{NetworkProtocol, NetworkAddress};

use function Onion\Framework\Loop\{signal,write};

class Client implements ClientInterface
{
    private static $cryptoClientProtocol;

    public static function setClientCryptoProtocol(int $protocol): void
    {
        static::$cryptoClientProtocol = $protocol;
    }

    public static function connect(string $address, ?float $timeout, ContextInterface ...$context): ResourceInterface
    {
        return signal(function (Closure $resume, TaskInterface $task, SchedulerInterface $scheduler) use ($address, $context) {
            $parts = parse_url($address);
            $scheduler->connect(
                $parts['host'],
                $parts['port'] ?? 0,
                fn (ResourceInterface $resource) => $resume($resource),
                match ($parts['scheme']) {
                    'tcp', 'unix' => NetworkProtocol::TCP,
                    'udp', 'udg' => NetworkProtocol::UDP,
                },
                new AggregateContext($context),
                match ($parts['scheme']) {
                    'tcp', 'udp' => NetworkAddress::NETWORK,
                    'unix', 'udg' => NetworkAddress::LOCAL,
                }
            );
        });
    }

    public static function send(
        string $address,
        string $data,
        ?float $timeout = null,
        array $contexts = [],
    ): ResourceInterface {
        $connection = static::connect($address, $timeout, ...$contexts);

        write($connection, $data);

        return $connection;
    }
}

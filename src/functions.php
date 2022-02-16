<?php

declare(strict_types=1);

namespace Onion\Framework\Client;

use LibDNS\Decoder\DecoderFactory;
use LibDNS\Encoder\EncoderFactory;
use LibDNS\Messages\MessageFactory;
use LibDNS\Messages\MessageTypes;
use LibDNS\Records\QuestionFactory;
use LibDNS\Records\Resource;
use LibDNS\Records\ResourceQTypes;

if (!function_exists(__NAMESPACE__ . '\resolve')) {
    function resolve(string $domain, string $type, int $timeout = 3, string $server = null)
    {
        $constantType = constant(ResourceQTypes::class . '::' . strtoupper($type));

        if ($constantType === null) {
            throw new \InvalidArgumentException("Unknown record type '{$type}'");
        }
        $question = (new QuestionFactory())->create($constantType);
        $question->setName($domain);

        $request = (new MessageFactory())->create(MessageTypes::QUERY);
        $request->getQuestionRecords()->add($question);
        $request->isRecursionDesired(true);
        $request->isAuthoritative(true);

        if ($server === null) {
            static $servers = [];

            if (count($servers) === 0) {
                if (DIRECTORY_SEPARATOR === '\\') {
                    preg_match_all(
                        '/(?<=[{;,"])([\da-f.:]{4,})(?=[};,"])/i',
                        shell_exec('wmic NICCONFIG get "DNSServerSearchOrder" /format:CSV'),
                        $matches
                    );

                    $servers = $matches[0];
                } else {
                    preg_match_all('/^nameserver\s+(\S+)\s*$/m', file_get_contents('/etc/resolv.conf'), $matches);

                    foreach ($matches[1] as $ip) {
                        if (strpos($ip, ':') !== false || strpos($ip, '%') !== false) {
                            continue;
                        }

                        $servers[] = $ip;
                    }
                }
            }

            $server = current($servers);
        }


        [$address, $port,] = explode(':', $server . ':53');

        $socket = Client::send(
            "udp://{$address}:{$port}",
            (new EncoderFactory())->create()->encode($request),
            $timeout,
        );

        $socket->wait();
        $data = '';
        while ($packet = $socket->read(1024)) {
            $data .= $packet;
        }

        $response = (new DecoderFactory())->create()->decode($data);

        $result = [];
        foreach ($response->getAnswerRecords() as $answer) {
            /** @var Resource $answer */
            $result[] = (string) $answer->getData();
        }


        return count($result) === 0 &&
            strtolower($domain) === 'localhost' ? [
                match (strtolower($type)) {
                    'a' => '127.0.0.1',
                    'aaaa' => '::1',
                },
            ] : $result;
    }
}

if (!function_exists(__NAMESPACE__ . '\gethostbyname')) {
    function gethostbyname(string $domain, int $timeout = 3, string $server = null)
    {
        $domain = strtolower($domain);
        static $lines = [];
        static $map = [];


        if (isset($map[$domain])) {
            return $map[$domain];
        }

        if (count($lines) === 0) {
            $location = match (DIRECTORY_SEPARATOR) {
                '\\' => (getenv('SystemRoot') ?: 'C:\\Windows') . '\system32\drivers\etc\hosts',
                default => '/etc/hosts',
            };

            $contents = preg_replace('/[ \t]*#.*/', '', strtolower(file_get_contents($location) ?: ''));
            $lines = array_map(fn (string $line) => preg_split('/\s+/', $line), preg_split('/\r?\n/', $contents));
        }

        foreach ($lines as $line) {
            $ip = array_shift($line);

            if (!$ip || count($line) === 0) {
                continue;
            }
            if (array_search($domain, $line) !== false && strpos($ip, ':') === false && strpos($ip, '%') === false) {
                $map[$domain] = $ip;

                return $ip;
            }
        }

        $record = resolve($domain, 'a', $timeout, $server);
        if (count($record) === 0) {
            $record = resolve($domain, 'aaaa', $timeout, $server);
        }

        return current($record);
    }
}

<?php
namespace Onion\Framework\Client;

use function Onion\Framework\EventLoop\loop;
use LibDNS\Decoder\DecoderFactory;
use LibDNS\Encoder\EncoderFactory;
use LibDNS\Messages\MessageFactory;
use LibDNS\Messages\MessageTypes;
use LibDNS\Records\QuestionFactory;
use LibDNS\Records\Record;
use LibDNS\Records\ResourceQTypes;
use Onion\Framework\Promise\Interfaces\PromiseInterface;
use Onion\Framework\Promise\Promise;
use function Onion\Framework\EventLoop\coroutine;
use function Onion\Framework\EventLoop\task;

if (!function_exists(__NAMESPACE__ . '\resolve')) {
    function resolve(string $domain, string $type, string $server = '1.1.1.1:53'): PromiseInterface {
        $type = constant(ResourceQTypes::class . '::' . strtoupper($type));

        if ($type === null) {
            throw new \InvalidArgumentException("Unknown record type '{$type}'");
        }

        return task(function () use ($domain, $type, $server) {
            $question = (new QuestionFactory)->create($type);
            $question->setName($domain);

            $request = (new MessageFactory)->create(MessageTypes::QUERY);
            $request->getQuestionRecords()->add($question);
            $request->isRecursionDesired(true);

            $client = new Client(Client::TYPE_UDP, $server, 10);

            return new Promise(function ($resolve, $reject) use (&$client) {
                $client->on('data', function (Packet $packet) use (&$resolve) {
                    $response = (new DecoderFactory)->create()->decode($packet->read(10 * 1024 * 1024));

                    $result = [];
                    foreach ($response->getAnswerRecords() as $answer) {
                        /** @var Record $answer */
                        $result[] = (string) $answer->getData();
                    }

                    $resolve($result);
                });

            });

            $client->send((new EncoderFactory)->create()->encode($request));
        });
    }
}

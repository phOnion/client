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

if (!function_exists(__NAMESPACE__ . '\resolve')) {
    function resolve(string $domain, string $type, string $server = '1.1.1.1:53'): PromiseInterface {
        $type = constant(ResourceQTypes::class . '::' . strtoupper($type));

        if ($type === null) {
            throw new \InvalidArgumentException("Unknown record type '{$type}'");
        }

        return new Promise(function (callable $resolve, callable $reject) use ($domain, $type, $server) {
            coroutine(function ($resolve, $reject, $domain, $type, $server) {
                try {
                    $question = (new QuestionFactory)->create($type);
                    $question->setName($domain);

                    $request = (new MessageFactory)->create(MessageTypes::QUERY);
                    $request->getQuestionRecords()->add($question);
                    $request->isRecursionDesired(true);

                    $client = new Client(Client::TYPE_UDP, $server, 10);

                    $promise = new Promise(function ($resolve, $reject) use (&$client) {
                        $client->on('data', function (Packet $packet) use (&$resolve) {
                            $response = (new DecoderFactory)->create()->decode($packet->read(10 * 1024 * 1024));

                            $result = [];
                            foreach ($response->getAnswerRecords() as $answer) {
                                /** @var Record $answer */
                                $result[] = (string) $answer->getData();
                            }

                            $resolve($result);
                        });

                    }, function () {
                        loop()->tick();
                    });
                    $client->send((new EncoderFactory)->create()->encode($request));

                    $resolve($promise);
                } catch (\Throwable $ex) {
                    $reject($ex);
                }
            }, $resolve, $reject, $domain, $type, $server);
        });
    }
}

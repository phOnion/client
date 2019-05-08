<?php
namespace Onion\Framework\Client;

use function Onion\Framework\EventLoop\loop;
use function Onion\Framework\Promise\async;
use LibDNS\Decoder\DecoderFactory;
use LibDNS\Encoder\EncoderFactory;
use LibDNS\Messages\MessageFactory;
use LibDNS\Messages\MessageTypes;
use LibDNS\Records\QuestionFactory;
use LibDNS\Records\Record;
use LibDNS\Records\ResourceQTypes;
use Onion\Framework\Promise\Interfaces\PromiseInterface;
use Onion\Framework\Promise\Promise;

if (!function_exists(__NAMESPACE__ . '\resolve')) {
    function resolve(string $domain, string $type, string $server = '1.1.1.1:53'): PromiseInterface {
        $type = constant(ResourceQTypes::class . '::' . strtoupper($type));

        if ($type === null) {
            throw new \InvalidArgumentException("Unknown record type '{$type}'");
        }

        try {
            $question = (new QuestionFactory)->create($type);
            $question->setName($domain);

            $request = (new MessageFactory)->create(MessageTypes::QUERY);
            $request->getQuestionRecords()->add($question);
            $request->isRecursionDesired(true);

            $packet = (new EncoderFactory)->create()->encode($request);

            $client = new Client(Client::TYPE_UDP, $server, 10);

            $promise = new Promise(function ($resolve, $reject) use (&$client) {
                $client->on('data', function (Packet $packet) use (&$resolve) {
                    $response = (new DecoderFactory)->create()->decode($packet->read(512));

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

            $client->send($packet);

            return $promise;
        } catch (\Throwable $ex) {
            var_dump($ex);
            exit;
        }
    }
}

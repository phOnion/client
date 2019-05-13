<?php
namespace Onion\Framework\Client;

use LibDNS\Decoder\DecoderFactory;
use LibDNS\Encoder\EncoderFactory;
use LibDNS\Messages\MessageFactory;
use LibDNS\Messages\MessageTypes;
use LibDNS\Records\QuestionFactory;
use LibDNS\Records\Record;
use LibDNS\Records\ResourceQTypes;
use Onion\Framework\Promise\Interfaces\PromiseInterface;
use Onion\Framework\Promise\Promise;
use Onion\Framework\Client\Interfaces\ClientInterface;
use LibDNS\Records\Question;
use function Onion\Framework\Promise\promise;

if (!function_exists(__NAMESPACE__ . '\resolve')) {
    function resolve(string $domain, string $type, int $timeout = 3, string $server = '1.1.1.1:53'): PromiseInterface {
        $type = constant(ResourceQTypes::class . '::' . strtoupper($type));

        if ($type === null) {
            throw new \InvalidArgumentException("Unknown record type '{$type}'");
        }

        $question = (new QuestionFactory)->create($type);
        $question->setName($domain);

        $client = new Client(Client::TYPE_UDP, $server, $timeout);

        return promise(function (Question $question, ClientInterface $client) {
            $request = (new MessageFactory)->create(MessageTypes::QUERY);
            $request->getQuestionRecords()->add($question);
            $request->isRecursionDesired(true);

            return $client->send((new EncoderFactory)->create()->encode($request))
                ->then(function (ClientInterface $client) {
                    return new Promise(function ($resolve) use (&$client) {
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
                });
        }, null, $question, $client);
    }
}

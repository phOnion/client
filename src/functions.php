<?php
namespace Onion\Framework\Client;

use LibDNS\Decoder\DecoderFactory;
use LibDNS\Encoder\EncoderFactory;
use LibDNS\Messages\MessageFactory;
use LibDNS\Messages\MessageTypes;
use LibDNS\Records\QuestionFactory;
use LibDNS\Records\Record;
use LibDNS\Records\ResourceQTypes;
use Onion\Framework\Client\Adapters\UdpAdapter;
use Onion\Framework\Loop\Result;
use Onion\Framework\Loop\Signal;
use Onion\Framework\Loop\Coroutine;
use Onion\Framework\Promise\Promise;
use Onion\Framework\Loop\Interfaces\TaskInterface;
use Onion\Framework\Loop\Interfaces\SchedulerInterface;

if (!function_exists(__NAMESPACE__ . '\resolve')) {
    function resolve(string $domain, string $type, int $timeout = 3, string $server = '1.1.1.1:53')
    {
        return new Signal(function (TaskInterface $task, SchedulerInterface $scheduler) use ($domain, $type, $timeout, $server) {
            $scheduler->add(new Coroutine(function () use ($task, $scheduler, $domain, $type, $timeout, $server) {
                $adapter = new UdpAdapter();

                $client = new Client($adapter);

                $type = constant(ResourceQTypes::class . '::' . strtoupper($type));

                if ($type === null) {
                    throw new \InvalidArgumentException("Unknown record type '{$type}'");
                }
                $question = (new QuestionFactory)->create($type);
                $question->setName($domain);

                $request = (new MessageFactory)->create(MessageTypes::QUERY);
                $request->getQuestionRecords()->add($question);
                $request->isRecursionDesired(true);
                [$address, $port, ] = explode(':', $server . ':53');

                $promise = (yield $client->send((new EncoderFactory)->create()->encode($request), $address, $port, $timeout));
                $promise->then(function (string $data) {
                    $response = (new DecoderFactory)->create()->decode($data);

                    $result = [];
                    foreach ($response->getAnswerRecords() as $answer) {
                        /** @var Record $answer */
                        $result[] = (string) $answer->getData();
                    }

                    return $result;
                });

                $task->send($promise);
                $scheduler->schedule($task);
            }));
        });

    }
}

if (!function_exists(__NAMESPACE__ . '\gethostbyname')) {
    function gethostbyname(string $domain, int $timeout = 3, string $server = '1.1.1.1:53')
    {
        return new Signal(function (TaskInterface $task, SchedulerInterface $scheduler) use ($domain, $timeout, $server) {
            $scheduler->add(new Coroutine(function () use ($task, $scheduler, $domain, $timeout, $server) {
                $result = (yield (yield resolve($domain, 'a', $timeout, $server))->then('current')->await());
                if (!$result) {
                    $result = (yield (yield resolve($domain, 'aaaa', $timeout, $server))->then('current')->await());
                }

                $task->send($result);
                $scheduler->schedule($task);
            }));
        });
    }
}

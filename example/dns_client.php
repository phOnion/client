<?php
use function Onion\Framework\EventLoop\loop;

use LibDNS\Decoder\DecoderFactory;
use LibDNS\Encoder\EncoderFactory;
use LibDNS\Messages\MessageFactory;
use LibDNS\Messages\MessageTypes;
use LibDNS\Records\QuestionFactory;
use LibDNS\Records\ResourceQTypes;
use Onion\Framework\Client\Client;

require __DIR__ . '/../vendor/autoload.php';

loop();

$client = new Client(Client::TYPE_UDP, '8.8.8.8:53');
$client->on('connect', function ($stream) {
    echo "Connected\n";
});

$client->on('data', function ($stream) {
    echo "Data\n";

    $packet = $stream->read(512);

    $response = (new DecoderFactory)->create()->decode($packet);

    $answers = $response->getAnswerRecords();
    foreach ($answers as $answer) {
        echo "Answer: {$answer->getData()}\n";
    }
    loop()->stop();
});

$question = (new QuestionFactory)->create(ResourceQTypes::A);
$question->setName('example.com');

$request = (new MessageFactory)->create(MessageTypes::QUERY);
$request->getQuestionRecords()->add($question);
$request->isRecursionDesired(true);

$packet = (new EncoderFactory)->create()->encode($request);

$client->send($packet)
    ->otherwise(function(\Throwable $ex) {
        echo "Error: {$ex->getMessage()} ({$ex->getCode()})\n";
    });

loop()->start();

<?php

use GuzzleHttp\Stream\StreamInterface;
use Onion\Framework\Client\Client;
use function Onion\Framework\EventLoop\loop;
use GuzzleHttp\Stream\Stream;

require __DIR__ . '/../vendor/autoload.php';

loop();
$symbols = [];
$buffer = [];
stream_set_blocking(STDIN, false);
$client = new Client(Client::TYPE_TCP, 'localhost:1338');
$client->on('connect', function (StreamInterface $stream) {
    echo "Connected\n";
    $stream->write("Test");
});
$client->on('data', function (StreamInterface $__stream) use (&$symbols, &$buffer) {
    echo "> {$__stream->read(8192)}";
});
$client->on('close', function () {
    echo "Bye!\n";
});
$client->proxy(new Stream(STDIN));
$client->connect()
    ->otherwise(function (\Throwable $exception) {
        echo "Error: {$exception->getMessage()}";
    })->await();

loop()->start();

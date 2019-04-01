<?php

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Stream\StreamInterface;
use Onion\Framework\Client\Client;
use function Onion\Framework\EventLoop\loop;

require __DIR__ . '/../vendor/autoload.php';

$request = new Request('GET', 'https://example.com');
loop();
$client = new Client(Client::TYPE_TCP | Client::TYPE_SECURE, 'example.com:443');
$client->on('connect', function (StreamInterface $stream) {
    echo "Connected\n";
    $req = "GET / HTTP/1.1\n\rHost: www.example.com:443\n\rAccept: */*\n\rAccept-Language: en-us\n\rAccept-Encoding: gzip, deflate\n\rUser-Agent: Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)\n\r\n\r";
    $stream->write($req);
});
$client->on('data', function (StreamInterface $stream) {
    echo "Received: {$stream->read(8192)}\n";
});
$client->on('close', function () {
    echo "Bye!\n";
});
$client->connect()
    ->otherwise(function (\Throwable $exception) {
        echo "Error: {$exception->getMessage()}";
    })->await();

loop()->start();

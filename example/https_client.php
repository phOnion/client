<?php
use function GuzzleHttp\Psr7\str;
use function Onion\Framework\EventLoop\loop;
use GuzzleHttp\Psr7\Request;
use Onion\Framework\Client\Client;

require __DIR__ . '/../vendor/autoload.php';

loop();

$client = new Client(Client::TYPE_TCP | Client::SECURE_TLS, 'example.com:443');
$client->on('connect', function () {
    echo "Connected\n";
});

$client->on('data', function ($stream) {
    echo "Data\n";
    file_put_contents(__DIR__ . '/result.html', $stream->getContents(), FILE_APPEND);
});

$request = new Request('GET', '/', [
    'Host' => 'www.example.com',
    'Accept' => 'text/html',
    'User-Agent' => 'php/test-client'
]);
$client->send(str($request))
    ->otherwise(function(\Throwable $ex) {
        echo "Error: {$ex->getMessage()} ({$ex->getCode()})\n";
    })->await();

loop()->start();

<?php

use GuzzleHttp\Stream\StreamInterface;
use Onion\Framework\Client\Client;
use function Onion\Framework\EventLoop\loop;
use Onion\Framework\Promise\Promise;
use Onion\Framework\Client\HttpClient;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;

require __DIR__ . '/../vendor/autoload.php';

loop();
// $symbols = [];
// $buffer = [];
// stream_set_blocking(STDIN, false);
// $client = new Client(Client::TYPE_TCP | Client::SECURE_TLS, 'facebook.com:443');
// $client->on('data', function (StreamInterface $__stream) use (&$symbols, &$buffer) {
//     $data = 1;
//     while (!$__stream->eof() && ($data = $__stream->read(8192)) !== '') {
//         echo "> {$data}";
//     }
// });

// $stack = [];
// for ($i=0; $i<5; $i++) {
//     $stack[] = $client->send("GET / HTTP/1.1\r\nHost: facebook.com\r\nConnection: Keep-Alive\r\nAccept: */*\r\n\r\n")->then(function () {
//         usleep(1000000);
//     });
// }

// Promise::all($stack)->then('var_dump');
// $client->on('close', function () {
//     echo "Bye!\n";
// });

// $client->on('connect', function ($stream) {
//     // $stream->write("GET / HTTP/1.1\r\nHost: facebook.com\r\nConnection: Keep-Alive\r\nAccept: */*\r\n\r\n");
//     // usleep(5)
//     // $stream->write("GET / HTTP/1.1\r\nHost: facebook.com\r\nConnection: Keep-Alive\r\nAccept: */*\r\n\r\n");
//     // $stream->write("GET / HTTP/1.1\r\nHost: facebook.com\r\nConnection: Keep-Alive\r\nAccept: */*\r\n\r\n");
//     // $stream->write("GET / HTTP/1.1\r\nHost: facebook.com\r\nConnection: Keep-Alive\r\nAccept: */*\r\n\r\n");
//     // $stream->write("GET / HTTP/1.1\r\nHost: facebook.com\r\nConnection: Keep-Alive\r\nAccept: */*\r\n\r\n");
// });
// // $client->send("GET / HTTP/1.1\r\nHost: facebook.com\r\nConnection: Keep-Alive\r\nAccept: */*\r\n\r\n")
// //     ->then(function ($client) {
// //         $client->send("GET / HTTP/1.1\r\nHost: facebook.com\r\nConnection: Keep-Alive\r\nAccept: */*\r\n\r\n");
// //     });

// $client->send("GET / HTTP/1.1\r\nHost: facebook.com\r\nConnection: Keep-Alive\r\nAccept: */*\r\n\r\n");
// $client->send("GET / HTTP/1.1\r\nHost: facebook.com\r\nConnection: Keep-Alive\r\nAccept: */*\r\n\r\n");
// $client->send("GET / HTTP/1.1\r\nHost: facebook.com\r\nConnection: Keep-Alive\r\nAccept: */*\r\n\r\n");
// // $client->proxy(new Stream(STDIN));
// var_dump($client->send("GET / HTTP/1.0\r\nHost: www.example.com\r\nAccept: */*\r\n\r\n")
//     // ->then(function ($stream) {
//     //     $stream->write("GET / HTTP/1.0\r\nHost: www.example.com\r\nAccept: */*\r\n\r\n");
//     // })
//     ->otherwise(function (\Throwable $exception) {
//         echo "Error: {$exception->getMessage()}";
//     })
//     // ->finally(function () {
//     //     loop()->stop();
//     // })
//     ->await());

$http = new HttpClient();
$r = $http->send(
    (new Request('GET', 'https://example.com/', [
        'Accept' => '*/*',
    ]))->withProtocolVersion('1.1')
)->then(function (ResponseInterface $response) {
    echo "> " . $response->getBody();
}, function (\Throwable $ex) {
    echo "{$ex->getMessage()}\n";
});

loop()->start();

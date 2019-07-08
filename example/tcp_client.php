<?php

use Onion\Framework\Client\Adapters\TcpAdapter;
use Onion\Framework\Loop\Coroutine;
use Onion\Framework\Loop\Scheduler;
use Onion\Framework\Client\Client;

require __DIR__ . '/../vendor/autoload.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$scheduler = new Scheduler;
$scheduler->add(new Coroutine(function () {
    $adapter = new TcpAdapter();
    $client = new Client($adapter);

    $message = "GET / HTTP/1.1\r\nHost: example.com\r\n\r\n";

    $promise = yield $client->send($message, '93.184.216.32', 80, 5);
    $v = yield $promise->then(function (string $message) {
            echo sprintf("Received %d bytes\n", strlen($message));
        })->await();

    echo $v;
}));
$scheduler->start();

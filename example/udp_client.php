<?php
// 52.20.16.20 40000

use Onion\Framework\Client\Adapters\TcpAdapter;
use Onion\Framework\Loop\Coroutine;
use Onion\Framework\Loop\Scheduler;
use Onion\Framework\Client\Client;
use Onion\Framework\Client\Adapters\UdpAdapter;

require __DIR__ . '/../vendor/autoload.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$scheduler = new Scheduler;
$scheduler->add(new Coroutine(function () {
    $adapter = new UdpAdapter();
    $client = new Client($adapter);

    $message = "Test";

    try {
        yield (yield $client->send($message, '127.0.0.1', 40000, 1))
            ->then(function (string $message) {
                echo sprintf("Received %d bytes\n", strlen($message));
            })->then('var_dump', 'var_dump');
    } catch (\Throwable $ex) {
        var_dump($ex);
    }
}));
$scheduler->start();

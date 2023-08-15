<?php
// 52.20.16.20 40000

use Onion\Framework\Client\Client;

use function Onion\Framework\Loop\{coroutine,scheduler,read,write,suspend};

require __DIR__ . '/../vendor/autoload.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

coroutine(function () {
    $client = Client::connect('udp://52.20.16.20:40001');
    write($client, 'Test!');

    read($client, function (\Onion\Framework\Loop\Interfaces\ResourceInterface $resource) {
        while (!$resource->eof()) {
            echo $resource->read(1);
            suspend();
        }
    });
});
scheduler()->start();

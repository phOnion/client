<?php
// 52.20.16.20 40000

use Onion\Framework\Loop\Scheduler;
use Onion\Framework\Client\Client;

use function Onion\Framework\Loop\coroutine;
use function Onion\Framework\Loop\scheduler;

require __DIR__ . '/../vendor/autoload.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$scheduler = new Scheduler;
coroutine(function () {
    var_dump(
        Client::send(
            "Test",
            'udp://52.20.16.20:40001',
            1
        ),
    );
});
scheduler()->start();

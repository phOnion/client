<?php
use function Onion\Framework\Client\resolve;
use Onion\Framework\Loop\Scheduler;
use Onion\Framework\Loop\Coroutine;
use function Onion\Framework\Client\gethostbyname;
use Onion\Framework\Loop\Timer;

require_once __DIR__ . '/../vendor/autoload.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$scheduler = new Scheduler;
$scheduler->add(new Coroutine(function () {
    yield (yield resolve('example.com', 'ns'))->then('var_dump')->await();
    $timer = yield Timer::interval(function () {
        echo ".";

        yield;
    }, 1);

    yield Timer::after(function () use ($timer) {
        yield Coroutine::kill($timer);
    }, 150);

    echo (yield gethostbyname('google.com', 5, '8.8.8.8'));
}));
$scheduler->start();

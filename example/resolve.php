<?php
use function Onion\Framework\Client\resolve;
use Onion\Framework\Loop\Scheduler;
use Onion\Framework\Loop\Coroutine;
use function Onion\Framework\Client\gethostbyname;

require_once __DIR__ . '/../vendor/autoload.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$scheduler = new Scheduler;
$scheduler->add(new Coroutine(function () {

    print_r(yield resolve('example.com', 'ns'));
    echo (yield gethostbyname('example.com')) . PHP_EOL;
}));
$scheduler->start();

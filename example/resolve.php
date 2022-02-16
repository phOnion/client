<?php

use function Onion\Framework\Client\resolve;
use function Onion\Framework\Client\gethostbyname;
use function Onion\Framework\Loop\coroutine;
use function Onion\Framework\Loop\scheduler;

require_once __DIR__ . '/../vendor/autoload.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);


coroutine(function () {
    print_r(resolve('example.com', 'ns'));
    print_r(resolve('localhost', 'a'));

    echo gethostbyname('localhost') . PHP_EOL;
    echo (gethostbyname('example.com')) . PHP_EOL;
});
scheduler()->start();

<?php
use function Onion\Framework\Client\resolve;
use function Onion\Framework\EventLoop\loop;

require_once __DIR__ . '/../vendor/autoload.php';

ini_set('display_errors', 1);
error_reporting(E_ALL ^ E_WARNING);

resolve('example.com', 'NS', 1, '8.8.8.8:53')
    ->then('var_dump')
    ->otherwise(function ($ex) {
        echo "{$ex->getMessage()}\n";
    })->finally([loop(), 'stop']);
loop()->start();

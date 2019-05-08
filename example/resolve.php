<?php
use function Onion\Framework\Client\resolve;
use function Onion\Framework\EventLoop\after;
use function Onion\Framework\EventLoop\loop;
use function Onion\Framework\EventLoop\timer;
use function Onion\Framework\Promise\async;

require_once __DIR__ . '/../vendor/autoload.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

async(function () {
    try {
        $promise = resolve('example.com', 'NS', '8.8.8.8:53')
            ->then(function ($r) {
                var_dump($r);
            })->otherwise(function ($ex) {
                echo "{$ex->getMessage()}\n";
                loop()->stop();
            });

            try {
                (clone $promise)->await();
            } catch (\Throwable $ex) {
                var_dump($ex->getMessage());
            }
timer(1000, function () use ($promise) {
    echo time() . "\n";
    try {
        var_dump(implode(', ', (clone $promise)->await()));
    } catch (\Throwable $ex) {
        var_dump($ex->getMessage());
    }
});
        after(20000, function () use ($promise) {
            echo "Tick!\n";
            try {
                var_dump(implode(', ', $promise->await()));
            } catch (\Throwable $ex) {
                var_dump($ex->getMessage());
            }
            // loop()->stop();
        });
    } catch (\Throwable $ex) {
        echo "{$ex->getMessage()}\n";
        loop()->stop();
    }
});
loop()->start();

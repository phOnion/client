<?php

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Stream\StreamInterface;
use Onion\Framework\Client\Client;
use function Onion\Framework\EventLoop\loop;
use function Onion\Framework\EventLoop\detach;

require __DIR__ . '/../vendor/autoload.php';

$request = new Request('GET', 'https://example.com');
loop();
$symbols = [];
$buffer = [];
stream_set_blocking(STDIN, false);
$client = new Client(Client::TYPE_TCP, 'localhost:1338');
$client->on('connect', function (StreamInterface $stream) {
    echo "Connected\n";
    // $stream->write("Test");
});
$client->on('data', function (StreamInterface $__stream) use (&$symbols, &$buffer) {
    // $pre = [];
    // $pre = array_keys(get_defined_vars());
    // extract($symbols);
    // $code = $__stream->getContents();

    // if (preg_match('~^:(\w+)\s(.*)?$~i', $code, $matches)) {
    //     switch($matches[1]) {
    //         case 'save':
    //             file_put_contents($matches[2], "<?php\n" . implode("\n", $buffer));
    //             break;
    //         case 'quit':
    //         echo "Bye!\n";
    //             break;
    //         default:
    //             echo "Unknown command\n";
    //             break;
    //     }

    //     return;
    // }
    // $buffer[] = $code;
    // echo eval($code);
    // $post = get_defined_vars();
    // foreach ($pre as $name) {
    //     if (isset($post[$name])) {
    //         unset($post[$name]);
    //     }
    // }

    // $symbols = $post;

    echo $__stream->getContents();
});
$client->on('close', function () {
    echo "Bye!\n";
});
$client->connect()
    ->otherwise(function (\Throwable $exception) {
        echo "Error: {$exception->getMessage()}";
    })->await();

loop()->start();

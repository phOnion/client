<?php

use Onion\Framework\Client\Client;
use Onion\Framework\Client\Contexts\SecureContext;

use function Onion\Framework\Client\gethostbyname;
use function Onion\Framework\Loop\coroutine;
use function Onion\Framework\Loop\scheduler;

require __DIR__ . '/../vendor/autoload.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

coroutine(function () {
    $ctx = new SecureContext();
    $ctx->setPeerName('www.cloudflare.com');

    $server = gethostbyname('www.cloudflare.com');
    file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'cloudflare.html', Client::send(
        "tls://{$server}:443",
        "GET / HTTP/1.1\r\nHost: www.cloudflare.com:443\r\nAccept: */*\r\n\r\n",
        contexts: $ctx->getContextArray(),
    )->read(8192));
});
scheduler()->start();

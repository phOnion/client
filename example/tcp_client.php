<?php

use Onion\Framework\Client\Client;
use Onion\Framework\Client\Contexts\SecureContext;

use Onion\Framework\Loop\Scheduler\Select;

use function Onion\Framework\Loop\{coroutine, scheduler, write, read, suspend};

require __DIR__ . '/../vendor/autoload.php';

scheduler(new Select());
scheduler()->addErrorHandler(fn (Throwable $ex) => print($ex->getMessage()));
coroutine(function () {
    $ctx = new SecureContext();
    $ctx->setVerifyPeer(true);
    $ctx->setVerifyPeerName(true);
    $ctx->setVerifyDepth(5);
    $ctx->setPeerName('www.example.com');
    $ctx->setPeerCertCapture(true);

    $resource = Client::connect(
        "tcp://example.com:443",
        $ctx,
    );

    write($resource, "GET / HTTP/1.1\r\n" .
        "Host: www.example.org:443\r\n" .
        "Accept: */*\r\n" .
        "Cache-Control: no-cache\r\n\r\n",
    );

    read($resource, function ($resource) {
        $contents = '';
        do {
            if (substr($contents, -4, 4) === "\r\n\r\n") {
                break;
            }

            suspend();

            $contents .= $resource->read(1);
        } while(!$resource->eof());

        echo "Received headers: '{$contents}'";
    });
    $resource->close();
});
scheduler()->start();

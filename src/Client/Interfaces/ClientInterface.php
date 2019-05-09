<?php
namespace Onion\Framework\Client\Interfaces;

use GuzzleHttp\Stream\StreamInterface;
use Onion\Framework\Promise\Interfaces\PromiseInterface;

interface ClientInterface
{
    public function on(string $event, callable $callback): void;
    public function proxy(StreamInterface $stream): void;
    public function connect(): PromiseInterface;
    public function send(string $data): PromiseInterface;
}

<?php
namespace Onion\Framework\Client\Interfaces;

use Onion\Framework\Promise\Interfaces\PromiseInterface;


interface ClientInterface
{
    public function on(string $event, callable $callback): void;
    public function connect(): PromiseInterface;
}

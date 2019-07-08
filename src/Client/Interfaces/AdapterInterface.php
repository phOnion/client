<?php
namespace Onion\Framework\Client\Interfaces;

use Onion\Framework\Loop\Signal;

interface AdapterInterface
{
    public function getAddress(string $address, ?int $port = null): string;

    public function send(string $data, string $address, ?int $port = null, int $timeout = 5): Signal;
}

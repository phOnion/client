<?php
namespace Onion\Framework\Client;

use Onion\Framework\Loop\Signal;
use Onion\Framework\Client\Interfaces\AdapterInterface;

class Client
{
    private $adapter;

    public function __construct(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
    }

    public function send(string $data, string $address, ?int $port = null, int $timeout = 5): Signal
    {
        return $this->adapter->send($data, $address, $port, $timeout);
    }
}

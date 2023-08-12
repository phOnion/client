<?php
declare(strict_types=1);

namespace Onion\Framework\Client\Contexts;
use Onion\Framework\Client\Interfaces\ContextInterface;

class AggregateContext implements ContextInterface
{
    public function __construct(private readonly array $contexts)
    {}

    public function getContextOptions(): array
    {
        return [];
    }

    public function getContextArray(): array
    {
        return array_merge(
            ...array_map(
                fn (ContextInterface $context) => $context->getContextArray(),
                $this->contexts,
            ),
        );
    }
}

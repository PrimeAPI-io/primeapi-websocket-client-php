<?php

declare(strict_types=1);

namespace PrimeAPI\WebSocket;

interface PriceHandlerInterface
{
    public function price(
        string $symbol,
        ?string $bid,
        ?string $ask,
    ): void;
}
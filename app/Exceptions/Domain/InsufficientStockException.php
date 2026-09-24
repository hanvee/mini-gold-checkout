<?php

namespace App\Exceptions\Domain;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(public readonly int $availableStock)
    {
        parent::__construct(
            $availableStock > 0
                ? "Only {$availableStock} unit(s) available."
                : 'This product is out of stock.'
        );
    }
}

<?php

namespace App\Exceptions\Domain;

use RuntimeException;

/**
 * Thrown when code attempts to mutate an Order field that is frozen at
 * checkout time. See App\Models\Order::booted() and AI_AGENT.md §2.2.
 */
class ImmutableOrderFieldException extends RuntimeException
{
    public function __construct(string $field)
    {
        parent::__construct("Order field [{$field}] is immutable once the order is created.");
    }
}

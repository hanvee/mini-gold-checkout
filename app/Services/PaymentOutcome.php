<?php

namespace App\Services;

use App\Enums\PaymentResponseCode;
use App\Enums\PaymentResult;
use App\Models\Order;

/**
 * Return value of PaymentService::handle(). Carries everything the
 * controller needs to build the response envelope in AI_AGENT.md §3.3.
 */
final class PaymentOutcome
{
    public function __construct(
        public readonly PaymentResult $result,
        public readonly PaymentResponseCode $code,
        public readonly string $message,
        public readonly ?Order $order = null,
    ) {}
}

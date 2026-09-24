<?php

namespace App\Enums;

/**
 * Machine-readable reason code returned alongside PaymentResult.
 * See AI_AGENT.md §2.4 and §3.3 for the full decision flow and HTTP status mapping.
 */
enum PaymentResponseCode: string
{
    case Ok = 'ok';
    case InvalidToken = 'invalid_token';
    case ValidationFailed = 'validation_failed';
    case EventConflict = 'event_conflict';
    case OrderNotFound = 'order_not_found';
    case UnsupportedStatus = 'unsupported_status';
    case AmountMismatch = 'amount_mismatch';
    case DuplicateEvent = 'duplicate_event';
    case OrderAlreadyPaid = 'order_already_paid';

    public function httpStatus(): int
    {
        return match ($this) {
            self::InvalidToken => 401,
            self::ValidationFailed, self::UnsupportedStatus, self::AmountMismatch => 422,
            self::EventConflict => 409,
            self::OrderNotFound => 404,
            self::Ok, self::DuplicateEvent, self::OrderAlreadyPaid => 200,
        };
    }
}

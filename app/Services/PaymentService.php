<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentResponseCode;
use App\Enums\PaymentResult;
use App\Models\Order;
use App\Models\PaymentEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Owns the idempotency and pending->paid transition logic for
 * POST /api/mock-payments. See AI_AGENT.md §2.4 for the full decision flow.
 *
 * Token verification is NOT this class's job — it happens earlier, in the
 * VerifyMockPaymentToken middleware, so this service only ever runs for
 * requests that already carry a valid token.
 */
class PaymentService
{
    /**
     * @param  array{event_id: string, order_number: string, amount: int, status: string}  $payload
     */
    public function handle(array $payload): PaymentOutcome
    {
        $eventId = $payload['event_id'];
        $orderNumber = $payload['order_number'];
        $amount = (int) $payload['amount'];
        $status = $payload['status'];

        $incomingHash = PaymentEvent::hashPayload($eventId, $orderNumber, $amount, $status);

        $existingEvent = PaymentEvent::where('event_id', $eventId)->first();

        if ($existingEvent) {
            return $this->resolveAgainstExistingEvent($existingEvent, $incomingHash);
        }

        try {
            return DB::transaction(
                fn () => $this->processNewEvent($eventId, $orderNumber, $amount, $status, $incomingHash)
            );
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            // Lost a race: another request inserted this event_id first.
            // Re-read it and resolve exactly as if it already existed.
            $existingEvent = PaymentEvent::where('event_id', $eventId)->firstOrFail();

            return $this->resolveAgainstExistingEvent($existingEvent, $incomingHash);
        }
    }

    private function processNewEvent(
        string $eventId,
        string $orderNumber,
        int $amount,
        string $status,
        string $payloadHash,
    ): PaymentOutcome {
        // Locked so a second, different event for the same order arriving
        // concurrently is serialized behind this one. See AI_AGENT.md §2.3/§2.4.
        $order = Order::where('order_number', $orderNumber)->lockForUpdate()->first();

        if (! $order) {
            return new PaymentOutcome(
                PaymentResult::Rejected,
                PaymentResponseCode::OrderNotFound,
                "No order found with number [{$orderNumber}].",
            );
        }

        if ($status !== OrderStatus::Paid->value) {
            return new PaymentOutcome(
                PaymentResult::Rejected,
                PaymentResponseCode::UnsupportedStatus,
                "Only the 'paid' payment status is supported.",
                $order,
            );
        }

        if ($amount !== $order->total_amount) {
            return new PaymentOutcome(
                PaymentResult::Rejected,
                PaymentResponseCode::AmountMismatch,
                'The amount does not match the order total.',
                $order,
            );
        }

        if ($order->status === OrderStatus::Paid) {
            // A genuinely new event, but the order was already settled by an
            // earlier event. Recorded for audit, but has no further effect —
            // stock and paid_at are left untouched.
            $this->recordEvent($eventId, $order, $amount, $status, $payloadHash, 'already_paid');

            return new PaymentOutcome(
                PaymentResult::AlreadyProcessed,
                PaymentResponseCode::OrderAlreadyPaid,
                'Order is already paid; no additional effect applied.',
                $order,
            );
        }

        $order->status = OrderStatus::Paid;
        $order->paid_at = now();
        $order->save();

        $this->recordEvent($eventId, $order, $amount, $status, $payloadHash, 'accepted');

        return new PaymentOutcome(
            PaymentResult::Accepted,
            PaymentResponseCode::Ok,
            'Payment accepted; order marked as paid.',
            $order,
        );
    }

    private function resolveAgainstExistingEvent(PaymentEvent $event, string $incomingHash): PaymentOutcome
    {
        if ($event->payload_hash !== $incomingHash) {
            return new PaymentOutcome(
                PaymentResult::Rejected,
                PaymentResponseCode::EventConflict,
                'This event_id was already used with different data.',
                $event->order,
            );
        }

        return new PaymentOutcome(
            PaymentResult::AlreadyProcessed,
            PaymentResponseCode::DuplicateEvent,
            'This event was already processed; no additional effect applied.',
            $event->order,
        );
    }

    private function recordEvent(
        string $eventId,
        Order $order,
        int $amount,
        string $status,
        string $payloadHash,
        string $result,
    ): void {
        PaymentEvent::create([
            'event_id' => $eventId,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'amount' => $amount,
            'status' => $status,
            'payload_hash' => $payloadHash,
            'result' => $result,
        ]);
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array($exception->getCode(), ['23000', '23505'], true)
            || str_contains($exception->getMessage(), 'UNIQUE constraint failed');
    }
}

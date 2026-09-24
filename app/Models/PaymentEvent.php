<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'order_id',
        'order_number',
        'amount',
        'status',
        'payload_hash',
        'result',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Deterministic hash of the fields that define "the same event" per
     * AI_AGENT.md §2.4 — used to tell a legitimate retry apart from an
     * event_id reused with different data.
     */
    public static function hashPayload(string $eventId, string $orderNumber, int $amount, string $status): string
    {
        return hash('sha256', "{$eventId}|{$orderNumber}|{$amount}|{$status}");
    }
}

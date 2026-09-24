<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Exceptions\Domain\ImmutableOrderFieldException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    /**
     * Frozen at checkout time. See AI_AGENT.md §2.2 — an order's identity,
     * pricing, and quantity must never change after creation, regardless of
     * later product price changes.
     */
    public const IMMUTABLE_FIELDS = [
        'order_number',
        'product_id',
        'product_name',
        'quantity',
        'unit_price',
        'total_amount',
    ];

    protected $fillable = [
        'order_number',
        'product_id',
        'product_name',
        'quantity',
        'unit_price',
        'total_amount',
        'status',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'total_amount' => 'integer',
            'status' => OrderStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'order_number';
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function paymentEvents(): HasMany
    {
        return $this->hasMany(PaymentEvent::class);
    }

    protected static function booted(): void
    {
        static::updating(function (Order $order) {
            foreach (self::IMMUTABLE_FIELDS as $field) {
                if ($order->isDirty($field)) {
                    throw new ImmutableOrderFieldException($field);
                }
            }
        });
    }
}

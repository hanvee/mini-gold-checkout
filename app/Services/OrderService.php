<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\Domain\InsufficientStockException;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Owns the only code path allowed to touch products.stock. See
 * AI_AGENT.md §2.3 for why this exact locking + guarded-decrement pattern
 * is non-negotiable.
 */
class OrderService
{
    private const MAX_ORDER_NUMBER_ATTEMPTS = 5;

    /**
     * @throws InsufficientStockException when stock is unavailable for the requested quantity.
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException when the product does not exist.
     */
    public function placeOrder(int $productId, int $quantity): Order
    {
        return DB::transaction(function () use ($productId, $quantity) {
            // lockForUpdate() gives real row-level blocking on MySQL/PostgreSQL.
            // On SQLite it is a no-op; the guarded decrement below is what
            // actually prevents negative stock there (whole-DB write lock).
            $product = Product::whereKey($productId)->lockForUpdate()->firstOrFail();

            if ($product->stock < $quantity) {
                throw new InsufficientStockException($product->stock);
            }

            // Single guarded UPDATE: only succeeds if stock is still sufficient
            // at the moment of writing. This is the second, driver-independent
            // safety net referenced in AI_AGENT.md §2.3.
            $affected = Product::whereKey($productId)
                ->where('stock', '>=', $quantity)
                ->decrement('stock', $quantity);

            if ($affected !== 1) {
                throw new InsufficientStockException($product->stock);
            }

            $unitPrice = $product->price;
            $totalAmount = $unitPrice * $quantity;

            return $this->createOrderWithUniqueNumber([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_amount' => $totalAmount,
                'status' => OrderStatus::Pending,
            ]);
        });
    }

    /**
     * order_number collisions are astronomically unlikely (a random
     * 8-character base32 suffix per day), but the insert is retried a
     * few times against the unique index rather than trusting probability
     * alone — this keeps the "no partial failures" guarantee even here.
     */
    private function createOrderWithUniqueNumber(array $attributes): Order
    {
        for ($attempt = 1; $attempt <= self::MAX_ORDER_NUMBER_ATTEMPTS; $attempt++) {
            try {
                return Order::create([
                    'order_number' => $this->generateOrderNumber(),
                    ...$attributes,
                ]);
            } catch (QueryException $exception) {
                $isLastAttempt = $attempt === self::MAX_ORDER_NUMBER_ATTEMPTS;

                if (! $this->isUniqueConstraintViolation($exception) || $isLastAttempt) {
                    throw $exception;
                }
            }
        }

        throw new \RuntimeException('Unable to generate a unique order number.');
    }

    private function generateOrderNumber(): string
    {
        return 'ORD-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        // SQLite: 19 (constraint), MySQL: 1062, PostgreSQL: 23505.
        return in_array($exception->getCode(), ['23000', '23505'], true)
            || str_contains($exception->getMessage(), 'UNIQUE constraint failed');
    }
}

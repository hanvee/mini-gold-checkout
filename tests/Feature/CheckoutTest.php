<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers required scenarios 1-3 from BRIEF.md §06, mapped per AI_AGENT.md §4.1.
 */
class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_checkout_creates_pending_order_and_reduces_stock(): void
    {
        $product = Product::factory()->create(['price' => 1_450_000, 'stock' => 3]);

        $response = $this->post(route('orders.store'), [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $order = Order::sole();

        $response->assertRedirect(route('orders.show', $order));
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame($product->id, $order->product_id);
        $this->assertSame($product->name, $order->product_name);
        $this->assertSame(2, $order->quantity);
        $this->assertSame(1_450_000, $order->unit_price);
        $this->assertSame(2_900_000, $order->total_amount);
        $this->assertNotEmpty($order->order_number);

        $this->assertSame(1, $product->fresh()->stock);
    }

    public function test_insufficient_stock_is_rejected_without_data_change(): void
    {
        $product = Product::factory()->create(['price' => 1_500_000, 'stock' => 1]);

        $response = $this->from(route('products.index'))->post(route('orders.store'), [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response->assertRedirect(route('products.index'));
        $response->assertSessionHasErrors('quantity');

        $this->assertSame(0, Order::count());
        $this->assertSame(1, $product->fresh()->stock);
    }

    public function test_checkout_against_zero_stock_product_is_rejected(): void
    {
        $product = Product::factory()->create(['stock' => 0]);

        $response = $this->from(route('products.index'))->post(route('orders.store'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response->assertSessionHasErrors('quantity');
        $this->assertSame(0, Order::count());
    }

    public function test_request_price_and_total_are_ignored(): void
    {
        $product = Product::factory()->create(['price' => 1_500_000, 'stock' => 5]);

        $this->post(route('orders.store'), [
            'product_id' => $product->id,
            'quantity' => 1,
            // Forged fields — must have zero effect per AI_AGENT.md §3.2.
            'price' => 1,
            'total' => 1,
            'unit_price' => 1,
            'total_amount' => 1,
        ]);

        $order = Order::sole();

        $this->assertSame(1_500_000, $order->unit_price);
        $this->assertSame(1_500_000, $order->total_amount);
    }

    public function test_unknown_product_is_rejected(): void
    {
        $response = $this->post(route('orders.store'), [
            'product_id' => 999_999,
            'quantity' => 1,
        ]);

        $response->assertSessionHasErrors('product_id');
        $this->assertSame(0, Order::count());
    }

    public function test_non_positive_or_non_integer_quantity_is_rejected(): void
    {
        $product = Product::factory()->create(['stock' => 5]);

        foreach ([0, -1, 1.5] as $invalidQuantity) {
            $response = $this->post(route('orders.store'), [
                'product_id' => $product->id,
                'quantity' => $invalidQuantity,
            ]);

            $response->assertSessionHasErrors('quantity');
        }

        $this->assertSame(0, Order::count());
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_later_price_change_does_not_affect_an_existing_order(): void
    {
        $product = Product::factory()->create(['price' => 1_500_000, 'stock' => 5]);

        $this->post(route('orders.store'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $order = Order::sole();

        $product->update(['price' => 2_000_000]);

        $this->assertSame(1_500_000, $order->fresh()->unit_price);
        $this->assertSame(1_500_000, $order->fresh()->total_amount);
    }
}

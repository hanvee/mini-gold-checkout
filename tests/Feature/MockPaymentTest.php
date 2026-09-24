<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\PaymentEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers required scenarios 4-5 from BRIEF.md §06, mapped per AI_AGENT.md §4.1.
 */
class MockPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(Order $order, array $overrides = []): array
    {
        return array_merge([
            'event_id' => 'evt-001',
            'order_number' => $order->order_number,
            'amount' => $order->total_amount,
            'status' => 'paid',
        ], $overrides);
    }

    private function postPayment(array $payload, ?string $token = null): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/mock-payments', $payload, [
            'X-Payment-Token' => $token ?? config('services.mock_payment.token'),
        ]);
    }

    public function test_valid_payment_marks_order_as_paid(): void
    {
        $order = Order::factory()->create(['total_amount' => 1_500_000]);

        $response = $this->postPayment($this->validPayload($order, ['amount' => 1_500_000]));

        $response->assertOk();
        $response->assertJson([
            'result' => 'accepted',
            'code' => 'ok',
            'order_number' => $order->order_number,
            'order_status' => 'paid',
        ]);

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame(1, PaymentEvent::count());
    }

    public function test_repeated_event_has_no_additional_effect(): void
    {
        $order = Order::factory()->create(['total_amount' => 1_500_000]);
        $payload = $this->validPayload($order, ['amount' => 1_500_000]);

        $first = $this->postPayment($payload);
        $first->assertOk();
        $paidAtAfterFirst = $order->fresh()->paid_at;

        $second = $this->postPayment($payload);

        $second->assertOk();
        $second->assertJsonPath('result', 'already_processed');

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertTrue($paidAtAfterFirst->equalTo($order->paid_at));
        // No second row for the same event, and no stock/order side effect twice.
        $this->assertSame(1, PaymentEvent::count());
    }

    public function test_reused_event_id_with_different_payload_is_rejected(): void
    {
        $orderA = Order::factory()->create(['total_amount' => 1_500_000]);
        $orderB = Order::factory()->create(['total_amount' => 750_000]);

        $this->postPayment($this->validPayload($orderA, ['event_id' => 'evt-shared', 'amount' => 1_500_000]))
            ->assertOk();

        $response = $this->postPayment($this->validPayload($orderB, ['event_id' => 'evt-shared', 'amount' => 750_000]));

        $response->assertStatus(409);
        $response->assertJsonPath('code', 'event_conflict');

        $this->assertSame(OrderStatus::Pending, $orderB->fresh()->status);
    }

    public function test_new_event_for_an_already_paid_order_is_not_reprocessed(): void
    {
        $order = Order::factory()->paid()->create(['total_amount' => 1_500_000, 'paid_at' => now()->subHour()]);
        $originalPaidAt = $order->paid_at;

        $response = $this->postPayment($this->validPayload($order, ['event_id' => 'evt-new', 'amount' => 1_500_000]));

        $response->assertOk();
        $response->assertJsonPath('result', 'already_processed');
        $response->assertJsonPath('code', 'order_already_paid');

        $order->refresh();
        $this->assertTrue($originalPaidAt->equalTo($order->paid_at));
        // The event itself is still recorded for audit purposes.
        $this->assertSame(1, PaymentEvent::where('event_id', 'evt-new')->count());
    }

    public function test_missing_or_incorrect_token_does_not_change_status(): void
    {
        $order = Order::factory()->create(['total_amount' => 1_500_000]);
        $payload = $this->validPayload($order, ['amount' => 1_500_000]);

        $missing = $this->postJson('/api/mock-payments', $payload);
        $missing->assertStatus(401);

        $incorrect = $this->postPayment($payload, 'wrong-token');
        $incorrect->assertStatus(401);

        $order->refresh();
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertNull($order->paid_at);
        $this->assertSame(0, PaymentEvent::count());
    }

    public function test_incorrect_amount_does_not_change_status(): void
    {
        $order = Order::factory()->create(['total_amount' => 1_500_000]);

        $response = $this->postPayment($this->validPayload($order, ['amount' => 999_999]));

        $response->assertStatus(422);
        $response->assertJsonPath('code', 'amount_mismatch');

        $order->refresh();
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertNull($order->paid_at);
    }

    public function test_unknown_order_number_is_rejected(): void
    {
        $response = $this->postPayment([
            'event_id' => 'evt-unknown',
            'order_number' => 'ORD-DOES-NOT-EXIST',
            'amount' => 1_000_000,
            'status' => 'paid',
        ]);

        $response->assertStatus(404);
        $response->assertJsonPath('code', 'order_not_found');
    }

    public function test_unsupported_status_is_rejected(): void
    {
        $order = Order::factory()->create(['total_amount' => 1_500_000]);

        $response = $this->postPayment($this->validPayload($order, ['status' => 'failed', 'amount' => 1_500_000]));

        $response->assertStatus(422);
        $response->assertJsonPath('code', 'unsupported_status');

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_payment_does_not_change_product_stock(): void
    {
        $order = Order::factory()->create(['total_amount' => 1_500_000]);
        $stockBefore = $order->product->stock;

        $this->postPayment($this->validPayload($order, ['amount' => 1_500_000]))->assertOk();

        $this->assertSame($stockBefore, $order->product->fresh()->stock);
    }

    public function test_malformed_payload_is_rejected_as_validation_failed(): void
    {
        $response = $this->postPayment([
            'event_id' => '',
            'order_number' => '',
            'amount' => 'not-a-number',
            'status' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('code', 'validation_failed');
        $response->assertJsonStructure(['errors']);
    }
}

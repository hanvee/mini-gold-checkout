<x-layout :title="'Order '.$order->order_number.' — Mini Gold Checkout'">
    <div class="rounded-lg border border-border bg-surface p-4 shadow-sm">
        <div class="flex items-center justify-between gap-3">
            <span class="font-mono text-sm text-muted">{{ $order->order_number }}</span>
            <x-status-badge :status="$order->status" />
        </div>

        <dl class="mt-4 divide-y divide-border text-sm">
            <div class="flex items-center justify-between py-2">
                <dt class="text-muted">Product</dt>
                <dd>{{ $order->product_name }}</dd>
            </div>
            <div class="flex items-center justify-between py-2">
                <dt class="text-muted">Quantity</dt>
                <dd class="tabular-nums">{{ $order->quantity }}</dd>
            </div>
            <div class="flex items-center justify-between py-2">
                <dt class="text-muted">Price at checkout</dt>
                <dd><x-money :amount="$order->unit_price" /></dd>
            </div>
            <div class="flex items-center justify-between py-3">
                <dt class="font-semibold">Total</dt>
                <dd><x-money :amount="$order->total_amount" class="text-2xl font-semibold" /></dd>
            </div>
        </dl>

        @if ($order->status === \App\Enums\OrderStatus::Pending)
            <p class="mt-4 text-sm text-muted">Waiting for payment confirmation.</p>
        @endif
    </div>

    <a href="{{ route('products.index') }}" class="mt-4 inline-block text-sm text-gold-700 underline">
        Back to catalog
    </a>
</x-layout>

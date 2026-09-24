<x-layout title="Catalog — Mini Gold Checkout">
    <h1 class="mb-4 text-xl font-semibold">Catalog</h1>

    <div class="space-y-4">
        @foreach ($products as $product)
            <div class="rounded-lg border border-border bg-surface p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold">{{ $product->name }}</h2>
                        <x-money :amount="$product->price" class="mt-1 block text-lg font-semibold" />
                    </div>

                    @if ($product->stock > 0)
                        <span class="shrink-0 text-sm text-muted">{{ $product->stock }} in stock</span>
                    @else
                        <x-status-badge status="out_of_stock" />
                    @endif
                </div>

                @if ($product->stock > 0)
                    <form
                        method="POST"
                        action="{{ route('orders.store') }}"
                        class="mt-4 flex items-center gap-3"
                        x-data="{ qty: 1, max: {{ $product->stock }}, submitting: false }"
                        @submit="submitting = true"
                    >
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $product->id }}">

                        <div class="flex items-center rounded-md border border-border">
                            <button
                                type="button"
                                class="flex h-11 w-11 items-center justify-center text-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-gold-700"
                                @click="qty = Math.max(1, qty - 1)"
                                aria-label="Decrease quantity"
                            >−</button>

                            <label for="quantity-{{ $product->id }}" class="sr-only">Quantity</label>
                            <input
                                id="quantity-{{ $product->id }}"
                                type="number"
                                name="quantity"
                                min="1"
                                :max="max"
                                x-model.number="qty"
                                class="tabular-nums h-11 w-14 border-x border-border text-center [appearance:textfield] focus-visible:outline focus-visible:outline-2 focus-visible:outline-gold-700"
                            >

                            <button
                                type="button"
                                class="flex h-11 w-11 items-center justify-center text-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-gold-700"
                                @click="qty = Math.min(max, qty + 1)"
                                aria-label="Increase quantity"
                            >+</button>
                        </div>

                        <button
                            type="submit"
                            class="h-11 flex-1 rounded-md bg-ink text-sm font-medium text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-gold-700 disabled:opacity-60"
                            :disabled="submitting"
                            x-text="submitting ? 'Processing…' : 'Order'"
                        >Order</button>
                    </form>
                @else
                    <button
                        type="button"
                        disabled
                        class="mt-4 h-11 w-full rounded-md bg-stone-100 text-sm font-medium text-stone-600"
                    >Out of stock</button>
                @endif
            </div>
        @endforeach
    </div>
</x-layout>

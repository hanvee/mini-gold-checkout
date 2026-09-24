@props(['status'])

@php
    $value = $status instanceof \App\Enums\OrderStatus ? $status->value : $status;

    $styles = match ($value) {
        'paid' => 'bg-green-100 text-green-800',
        'pending' => 'bg-amber-100 text-amber-800',
        'out_of_stock' => 'bg-stone-100 text-stone-600',
        default => 'bg-stone-100 text-stone-600',
    };

    $label = match ($value) {
        'paid' => 'Paid',
        'pending' => 'Pending',
        'out_of_stock' => 'Out of stock',
        default => ucfirst($value),
    };
@endphp

{{-- Label text is always rendered, never color alone. See DESIGN.md §6. --}}
<span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium {{ $styles }}">
    {{ $label }}
</span>

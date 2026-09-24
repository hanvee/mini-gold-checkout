@props(['amount'])

{{-- The only place currency is formatted for display. See DESIGN.md §4 and AI_AGENT.md §3.4. --}}
<span {{ $attributes->merge(['class' => 'tabular-nums']) }}>{{ \App\Support\Money::rupiah((int) $amount) }}</span>

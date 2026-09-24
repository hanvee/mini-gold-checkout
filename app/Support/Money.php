<?php

namespace App\Support;

/**
 * The single place that formats an integer-rupiah amount for display.
 * See DESIGN.md §4 and AI_AGENT.md §3.4 — no other file should format currency.
 */
final class Money
{
    public static function rupiah(int $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }
}

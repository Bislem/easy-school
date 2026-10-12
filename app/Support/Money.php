<?php

namespace App\Support;

/** Ledger arithmetic is done in integer cents to avoid float drift. */
final class Money
{
    public static function cents(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    public static function amount(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}

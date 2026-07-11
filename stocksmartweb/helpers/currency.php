<?php
/**
 * Single source of truth for currency formatting across every server-rendered
 * amount (receipts, activity-log strings, exports). The client-side twin is
 * assets/js/currency.js — both produce the same "Rs. 1,25,000" Nepali digit
 * grouping (last 3 digits, then pairs), sourced from system_settings.
 */

declare(strict_types=1);

function currency_symbol(): string
{
    return 'Rs.';
}

/** Nepali/Indian digit grouping: 1234567 -> "12,34,567". */
function currency_group_digits(string $digits): string
{
    if (strlen($digits) <= 3) {
        return $digits;
    }
    $lastThree = substr($digits, -3);
    $rest = substr($digits, 0, -3);
    $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
    return $rest . ',' . $lastThree;
}

function money_npr(float $amount, int $decimals = 0): string
{
    $isNegative = $amount < 0;
    $amount = abs($amount);
    $rounded = round($amount, $decimals);
    $intPart = (string) floor($rounded);
    $formatted = currency_group_digits($intPart);

    if ($decimals > 0) {
        $decimalPart = number_format($rounded - floor($rounded), $decimals, '.', '');
        $formatted .= substr($decimalPart, 1);
    }

    return ($isNegative ? '-' : '') . currency_symbol() . ' ' . $formatted;
}

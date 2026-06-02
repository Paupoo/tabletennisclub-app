<?php

if (! function_exists('euros')) {
    function euros(?int $cents): string
    {
        if ($cents === null) {
            return '0,00 €';
        }

        return number_format($cents / 100, 2, ',', ' ') . ' €';
    }
}

if (! function_exists('cents')) {
    function cents(string $price): int
    {
        // normalize "2,50" or "2.50"
        $normalized = str_replace([' ', ','], ['', '.'], $price);

        return (int) round(((float) $normalized) * 100);
    }
}
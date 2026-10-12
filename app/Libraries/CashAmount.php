<?php

namespace App\Libraries;

use InvalidArgumentException;

/** Exact cents arithmetic; no float input, extension, or round-off dependency. */
final class CashAmount
{
    public static function cents(string $value): int
    {
        if (PHP_INT_SIZE < 8 || preg_match('/^(-?)(0|[1-9][0-9]{0,11})(?:\.([0-9]{1,2}))?$/D', $value, $m) !== 1) {
            throw new InvalidArgumentException('Nominal harus desimal maksimal 12 digit dan 2 angka pecahan.');
        }
        $cents = (int) $m[2] * 100 + (int) str_pad($m[3] ?? '', 2, '0');
        return $m[1] === '-' ? -$cents : $cents;
    }

    public static function format(int $cents): string
    {
        return ($cents < 0 ? '-' : '') . intdiv(abs($cents), 100) . '.' . str_pad((string) (abs($cents) % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Display computed entitlements without hiding cents; integral rupiah keep their usual format. */
    public static function rpPrecise(mixed $value): string
    {
        $cents = self::cents((string) ($value === null || $value === '' ? '0' : $value));
        $absolute = abs($cents);
        $fraction = $absolute % 100;
        return ($cents < 0 ? '-Rp' : 'Rp') . number_format(intdiv($absolute, 100), 0, ',', '.')
            . ($fraction === 0 ? '' : ',' . str_pad((string) $fraction, 2, '0', STR_PAD_LEFT));
    }

    /**
     * Format a nominal for Indonesian display with thousand separator dot and no decimals.
     * e.g. 100000 => 'Rp100.000', '6550000.00' => 'Rp6.550.000', 0 => 'Rp0'
     */
    public static function rp(mixed $value, bool $withPrefix = true): string
    {
        if ($value === null || $value === '') {
            return $withPrefix ? 'Rp0' : '0';
        }
        if (is_numeric($value)) {
            $val = (float) $value;
        } else {
            $clean = trim(str_ireplace(['Rp', ' '], '', (string) $value));
            if (is_numeric($clean)) {
                $val = (float) $clean;
            } elseif (strpos($clean, ',') !== false && strpos($clean, '.') !== false) {
                if (strrpos($clean, ',') > strrpos($clean, '.')) {
                    $clean = str_replace('.', '', $clean);
                    $clean = str_replace(',', '.', $clean);
                } else {
                    $clean = str_replace(',', '', $clean);
                }
                $val = (float) $clean;
            } else {
                $val = (float) $clean;
            }
        }
        $formatted = number_format(abs($val), 0, ',', '.');
        if (! $withPrefix) {
            return ($val < 0 ? '-' : '') . $formatted;
        }
        return ($val < 0 ? '-Rp' : 'Rp') . $formatted;
    }
}

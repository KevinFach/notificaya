<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Normalises recipient numbers to E.164, the format FastSMS expects.
 * Mirrors the normalisation FastSMS applies for its push channels so the
 * number we store and the number that goes out are the same.
 */
class PhoneNormalizer
{
    /**
     * FastSMS caps the `numero` column at 20 characters.
     */
    public const MAX_LENGTH = 20;

    private const MIN_DIGITS = 8;

    public static function normalize(?string $number, string $prefix = '+52'): ?string
    {
        if (blank($number)) {
            return null;
        }

        $cleaned = preg_replace('/[^0-9+]/', '', $number) ?? '';

        if ($cleaned === '') {
            return null;
        }

        $normalized = Str::startsWith($cleaned, '+')
            ? '+'.preg_replace('/\D/', '', $cleaned)
            : self::withPrefix($cleaned, $prefix);

        if (mb_strlen(preg_replace('/\D/', '', $normalized) ?? '') < self::MIN_DIGITS) {
            return null;
        }

        return mb_strlen($normalized) > self::MAX_LENGTH ? null : $normalized;
    }

    private static function withPrefix(string $digits, string $prefix): string
    {
        $digits = ltrim(preg_replace('/\D/', '', $digits) ?? '', '0');
        $prefix = '+'.ltrim(preg_replace('/\D/', '', $prefix) ?? '', '0');

        return $prefix.$digits;
    }
}

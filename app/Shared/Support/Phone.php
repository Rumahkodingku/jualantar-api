<?php

namespace App\Shared\Support;

final class Phone
{
    /**
     * Normalize a phone number into the canonical E.164 format used across
     * JualAntar. Indonesian local numbers (leading 0) become +62, while
     * already-international numbers are preserved.
     */
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $value = (string) preg_replace('/[^0-9+]/', '', trim($phone));

        if ($value === '') {
            return $value;
        }

        return match (true) {
            str_starts_with($value, '0') => '+62'.substr($value, 1),
            str_starts_with($value, '+') => $value,
            str_starts_with($value, '62') => '+'.$value,
            str_starts_with($value, '8') => '+62'.$value,
            default => $value,
        };
    }
}

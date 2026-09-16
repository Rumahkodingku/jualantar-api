<?php

namespace App\Modules\Merchant\Application\Common\Concerns;

/**
 * Deterministic hashing for snapshot subtrees. Keys are sorted recursively and
 * list items are ordered by their canonical JSON representation so that an
 * irrelevant key order never changes the resulting hash.
 */
trait CanonicalHasher
{
    private function canonicalHash(mixed $value): string
    {
        $encoded = json_encode(
            $this->canonicalize($value),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        return hash('sha256', $encoded === false ? '' : $encoded);
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            $items = array_map(fn(mixed $item): mixed => $this->canonicalize($item), $value);

            usort($items, fn(mixed $a, mixed $b): int => strcmp(
                (string) json_encode($a, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                (string) json_encode($b, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ));

            return $items;
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }
}

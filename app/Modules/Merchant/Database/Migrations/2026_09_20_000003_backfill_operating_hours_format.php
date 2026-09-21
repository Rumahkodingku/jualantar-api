<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Normalize the legacy per-day window list (`{day: [{open, close}]}`) into
     * the canonical Merchant Operations shape (`{day: {is_open, open, close}}`).
     * The first window of a day becomes the representative schedule.
     */
    public function up(): void
    {
        $this->transform(fn (array $hours): array => $this->toCanonical($hours));
    }

    public function down(): void
    {
        $this->transform(fn (array $hours): array => $this->toLegacy($hours));
    }

    /**
     * @param  callable(array<string, mixed>): array<string, mixed>  $convert
     */
    private function transform(callable $convert): void
    {
        DB::table('merchant.merchant_outlets')
            ->whereNotNull('operating_hours')
            ->orderBy('id')
            ->chunk(100, function ($outlets) use ($convert): void {
                foreach ($outlets as $outlet) {
                    $current = json_decode((string) $outlet->operating_hours, true);

                    if (! is_array($current)) {
                        continue;
                    }

                    DB::statement(
                        'UPDATE merchant.merchant_outlets SET operating_hours = ?::jsonb WHERE id = ?',
                        [json_encode($convert($current)), $outlet->id],
                    );
                }
            });
    }

    /**
     * @param  array<string, mixed>  $hours
     * @return array<string, mixed>
     */
    private function toCanonical(array $hours): array
    {
        $converted = [];

        foreach ($hours as $day => $value) {
            if (is_array($value) && array_key_exists('is_open', $value)) {
                $converted[$day] = $value;

                continue;
            }

            $first = is_array($value) && isset($value[0]) && is_array($value[0]) ? $value[0] : null;
            $open = $first['open'] ?? null;
            $close = $first['close'] ?? null;

            $converted[$day] = ($open !== null && $close !== null)
                ? ['is_open' => true, 'open' => $open, 'close' => $close]
                : ['is_open' => false];
        }

        return $converted;
    }

    /**
     * @param  array<string, mixed>  $hours
     * @return array<string, mixed>
     */
    private function toLegacy(array $hours): array
    {
        $converted = [];

        foreach ($hours as $day => $value) {
            if (is_array($value) && ! array_key_exists('is_open', $value)) {
                $converted[$day] = $value;

                continue;
            }

            $converted[$day] = (is_array($value) && ($value['is_open'] ?? false) === true)
                ? [['open' => $value['open'] ?? null, 'close' => $value['close'] ?? null]]
                : [];
        }

        return $converted;
    }
};

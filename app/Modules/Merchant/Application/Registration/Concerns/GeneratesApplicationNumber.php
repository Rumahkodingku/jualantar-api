<?php

namespace App\Modules\Merchant\Application\Registration\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Generates `MA-{YYYYMMDD}-{6 digit}` application numbers through a per-day
 * counter table. The counter row is locked for update so concurrent
 * registrations cannot collide; the unique index on application_number is the
 * final protection.
 */
trait GeneratesApplicationNumber
{
    private function nextApplicationNumber(): string
    {
        $date = now()->toDateString();

        DB::table('merchant.merchant_application_sequences')->insertOrIgnore([
            'date' => $date,
            'last_number' => 0,
        ]);

        $lastNumber = (int) DB::table('merchant.merchant_application_sequences')
            ->where('date', $date)
            ->lockForUpdate()
            ->value('last_number');

        $next = $lastNumber + 1;

        DB::table('merchant.merchant_application_sequences')
            ->where('date', $date)
            ->update(['last_number' => $next]);

        return 'MA-'.now()->format('Ymd').'-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}

<?php

namespace App\Modules\Merchant\Application\Operations\Services;

use App\Modules\Merchant\Domain\Enums\MerchantStatus;
use App\Modules\Merchant\Domain\Enums\OutletStatus;
use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use Carbon\CarbonImmutable;

/**
 * Derives the open/closed availability of an outlet from the merchant status,
 * the outlet status and the outlet operating hours. Availability is never
 * persisted.
 */
final class OperationalAvailabilityResolver
{
    /**
     * @return array{
     *     status: string,
     *     reason: string|null,
     *     merchant_status: string,
     *     outlet_status: string,
     *     schedule: array{open: string, close: string}|null
     * }
     */
    public function resolve(MerchantOutlet $outlet): array
    {
        $merchant = $outlet->merchant;
        $merchantStatus = $merchant->status;
        $outletStatus = $outlet->status;

        if ($merchantStatus === MerchantStatus::Inactive) {
            return $this->closed('merchant_inactive', $merchantStatus, $outletStatus);
        }

        if ($merchantStatus === MerchantStatus::Suspended) {
            return $this->closed('merchant_suspended', $merchantStatus, $outletStatus);
        }

        if ($outletStatus === OutletStatus::Inactive) {
            return $this->closed('outlet_inactive', $merchantStatus, $outletStatus);
        }

        $timezone = (string) config('merchant.operations.timezone', 'Asia/Jakarta');
        $now = CarbonImmutable::now($timezone);

        $day = strtolower($now->englishDayOfWeek);
        $schedule = $outlet->operating_hours[$day] ?? null;

        if (! is_array($schedule) || ($schedule['is_open'] ?? false) !== true) {
            return $this->closed('scheduled_closed', $merchantStatus, $outletStatus);
        }

        $open = $schedule['open'] ?? null;
        $close = $schedule['close'] ?? null;

        if (! is_string($open) || ! is_string($close)) {
            return $this->closed('scheduled_closed', $merchantStatus, $outletStatus);
        }

        $window = ['open' => $open, 'close' => $close];
        $openAt = CarbonImmutable::parse($now->toDateString().' '.$open, $timezone);
        $closeAt = CarbonImmutable::parse($now->toDateString().' '.$close, $timezone);

        if ($now->lt($openAt) || $now->gte($closeAt)) {
            return $this->closed('outside_operating_hours', $merchantStatus, $outletStatus, $window);
        }

        return [
            'status' => 'open',
            'reason' => null,
            'merchant_status' => $merchantStatus->value,
            'outlet_status' => $outletStatus->value,
            'schedule' => $window,
        ];
    }

    /**
     * @param  array{open: string, close: string}|null  $schedule
     * @return array{
     *     status: string,
     *     reason: string,
     *     merchant_status: string,
     *     outlet_status: string,
     *     schedule: array{open: string, close: string}|null
     * }
     */
    private function closed(
        string $reason,
        MerchantStatus $merchantStatus,
        OutletStatus $outletStatus,
        ?array $schedule = null,
    ): array {
        return [
            'status' => 'closed',
            'reason' => $reason,
            'merchant_status' => $merchantStatus->value,
            'outlet_status' => $outletStatus->value,
            'schedule' => $schedule,
        ];
    }
}

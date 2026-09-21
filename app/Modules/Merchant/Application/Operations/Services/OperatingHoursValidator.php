<?php

namespace App\Modules\Merchant\Application\Operations\Services;

use App\Shared\Result\Result;
use App\Shared\Result\ResultError;
use DateTimeImmutable;

/**
 * Validates and normalizes the canonical operating-hours payload:
 *
 *     { "monday": { "is_open": true, "open": "08:00", "close": "22:00" },
 *       "sunday": { "is_open": false } }
 *
 * Overnight schedules (close <= open) are rejected rather than guessed.
 */
final class OperatingHoursValidator
{
    /**
     * @var list<string>
     */
    public const DAYS = [
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',
    ];

    /**
     * @param  array<string, mixed>  $hours
     */
    public function validate(array $hours): Result
    {
        $unknown = array_diff(array_keys($hours), self::DAYS);

        if ($unknown !== []) {
            return $this->invalid('Unknown day keys: '.implode(', ', $unknown).'.');
        }

        $normalized = [];

        foreach ($hours as $day => $schedule) {
            if (! is_array($schedule) || ! array_key_exists('is_open', $schedule)) {
                return $this->invalid("The {$day} schedule must define is_open.");
            }

            $isOpen = $schedule['is_open'];

            if (! is_bool($isOpen)) {
                return $this->invalid("The {$day} is_open field must be a boolean.");
            }

            if (! $isOpen) {
                $normalized[$day] = ['is_open' => false];

                continue;
            }

            $open = $schedule['open'] ?? null;
            $close = $schedule['close'] ?? null;

            if (! $this->isTime($open) || ! $this->isTime($close)) {
                return $this->invalid("The {$day} schedule requires valid open and close times (HH:mm).");
            }

            if ($close <= $open) {
                return $this->invalid("The {$day} close time must be after its open time.");
            }

            $normalized[$day] = ['is_open' => true, 'open' => $open, 'close' => $close];
        }

        return Result::ok($normalized);
    }

    private function isTime(mixed $value): bool
    {
        if (! is_string($value) || ! preg_match('/^\d{2}:\d{2}$/', $value)) {
            return false;
        }

        return DateTimeImmutable::createFromFormat('!H:i', $value) !== false;
    }

    private function invalid(string $message): Result
    {
        return Result::err(new ResultError(
            code: 'invalid_operating_hours',
            message: $message,
            status: 422,
            title: 'Unprocessable Entity',
        ));
    }
}

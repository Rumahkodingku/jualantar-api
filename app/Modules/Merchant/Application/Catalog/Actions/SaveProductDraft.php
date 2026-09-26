<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Application\Catalog\Concerns\ManagesProductDrafts;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\ProductDraft;
use App\Modules\Storage\Contracts\ObjectStorage;
use App\Shared\Result\Result;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Upserts the merchant's wizard draft under optimistic concurrency control.
 *
 * The client echoes the version it last saw; anything else means the draft
 * moved on (another tab, another device) and the save is refused rather than
 * silently overwriting work. The payload itself is never validated for
 * completeness here — a draft is by definition allowed to be incomplete.
 */
final class SaveProductDraft
{
    use ManagesProductDrafts;

    public function __construct(
        private readonly ObjectStorage $storage,
    ) {}

    /**
     * @param  array{expected_version?: int|null, step_index: int, data: array<string, mixed>}  $data
     */
    public function __invoke(Merchant $merchant, array $data): Result
    {
        $this->purgeExpiredDrafts($merchant, $this->storage);

        $draft = $this->activeDraft($merchant);
        $expectedVersion = $data['expected_version'] ?? null;

        $guard = $this->guardVersion($draft, $expectedVersion);

        if ($guard !== null) {
            return $guard;
        }

        $attributes = [
            'step_index' => $data['step_index'],
            'payload' => $data['data'],
            'expires_at' => $this->draftFreshExpiry(),
        ];

        try {
            $saved = DB::transaction(function () use ($merchant, $draft, $attributes): ProductDraft {
                if ($draft === null) {
                    return ProductDraft::query()->create([
                        'merchant_id' => $merchant->id,
                        'version' => 1,
                        ...$attributes,
                    ]);
                }

                $draft->update([
                    ...$attributes,
                    'version' => $draft->version + 1,
                ]);

                return $draft;
            });
        } catch (QueryException $exception) {
            // Two concurrent saves race past the read above; the unique index on
            // merchant_id is the arbiter and the loser gets the same 409.
            if (! $this->isUniqueViolation($exception)) {
                throw $exception;
            }

            return $this->draftVersionConflict(
                'The draft was saved concurrently. Reload and try again.',
            );
        }

        return Result::ok($saved->refresh());
    }

    /**
     * A null result means the save may proceed.
     */
    private function guardVersion(?ProductDraft $draft, ?int $expectedVersion): ?Result
    {
        if ($draft === null && $expectedVersion !== null) {
            return $this->draftVersionConflict(
                'The draft no longer exists. Reload before saving.',
            );
        }

        if ($draft === null) {
            return null;
        }

        if ($expectedVersion === null) {
            return $this->draftVersionConflict(
                'A draft already exists. Reload before saving.',
                ['version' => $draft->version],
            );
        }

        if ($draft->version !== $expectedVersion) {
            return $this->draftVersionConflict(
                'The draft changed since it was last loaded. Reload before saving.',
                ['version' => $draft->version, 'updated_at' => $draft->updated_at?->toIso8601String()],
            );
        }

        return null;
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        return in_array($exception->getCode(), ['23505', '23000'], true);
    }
}

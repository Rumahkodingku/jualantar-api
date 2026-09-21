<?php

namespace App\Modules\Merchant\Application\Operations\Services;

use App\Modules\IdentityAccess\Contracts\Authorization;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use App\Modules\Merchant\Domain\Models\MerchantOutletUser;
use App\Shared\Result\Result;
use App\Shared\Result\ResultError;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resolves the merchant/outlet scope of the authenticated user and enforces
 * that managers and staff can only touch the outlets they are assigned to.
 *
 * This service only answers authorization questions; business rules stay in
 * the use-case actions.
 */
final class MerchantOperationsAuthorization
{
    public function __construct(private readonly Authorization $authorization) {}

    public function userId(): string
    {
        return (string) auth()->id();
    }

    /**
     * The merchant context for read/bootstrap endpoints: the merchant owned by
     * the user, otherwise the merchant of their outlet assignment.
     */
    public function merchantContext(): Result
    {
        $owned = Merchant::query()->where('user_id', $this->userId())->first();

        if ($owned !== null) {
            return Result::ok($owned);
        }

        $merchantId = MerchantOutletUser::query()
            ->where('user_id', $this->userId())
            ->orderBy('created_at')
            ->value('merchant_id');

        if ($merchantId !== null) {
            $merchant = Merchant::query()->find($merchantId);

            if ($merchant !== null) {
                return Result::ok($merchant);
            }
        }

        return $this->merchantNotFound();
    }

    /**
     * The merchant owned by the authenticated user (owner-only endpoints).
     */
    public function ownedMerchant(): Result
    {
        $merchant = Merchant::query()->where('user_id', $this->userId())->first();

        if ($merchant === null) {
            return $this->merchantNotFound();
        }

        return Result::ok($merchant);
    }

    public function isOwner(Merchant $merchant): bool
    {
        return $merchant->user_id !== null && $merchant->user_id === $this->userId();
    }

    /**
     * Base query for every outlet the user may see: all outlets of the merchant
     * they own, plus the outlets they are assigned to as an employee.
     *
     * @return Builder<MerchantOutlet>
     */
    public function outletQuery(): Builder
    {
        $userId = $this->userId();

        return MerchantOutlet::query()->where(function (Builder $query) use ($userId): void {
            $query->whereHas('merchant', fn (Builder $merchant) => $merchant->where('user_id', $userId))
                ->orWhereHas('assignments', fn (Builder $assignment) => $assignment->where('user_id', $userId));
        });
    }

    /**
     * Resolve an outlet the user may operate on, distinguishing a same-merchant
     * outlet outside the assignment scope (403) from a foreign merchant (404).
     */
    public function authorizedOutlet(string $outletId): Result
    {
        $outlet = MerchantOutlet::query()->find($outletId);

        if ($outlet === null) {
            return $this->outletNotFound();
        }

        if ($this->isOwner($outlet->merchant)) {
            return Result::ok($outlet);
        }

        $assigned = MerchantOutletUser::query()
            ->where('outlet_id', $outlet->id)
            ->where('user_id', $this->userId())
            ->exists();

        if ($assigned) {
            return Result::ok($outlet);
        }

        $hasSiblingAssignment = MerchantOutletUser::query()
            ->where('merchant_id', $outlet->merchant_id)
            ->where('user_id', $this->userId())
            ->exists();

        if ($hasSiblingAssignment) {
            return Result::err(new ResultError(
                code: 'outlet_scope_forbidden',
                message: 'You are not assigned to this outlet.',
                status: 403,
                title: 'Forbidden',
            ));
        }

        return $this->outletNotFound();
    }

    public function can(string $permission): bool
    {
        return $this->authorization->userHasPermission($this->userId(), $permission);
    }

    private function merchantNotFound(): Result
    {
        return Result::err(new ResultError(
            code: 'merchant_not_found',
            message: 'No merchant context was found for this user.',
            status: 404,
            title: 'Not Found',
        ));
    }

    private function outletNotFound(): Result
    {
        return Result::err(new ResultError(
            code: 'outlet_not_found',
            message: 'The outlet was not found in your scope.',
            status: 404,
            title: 'Not Found',
        ));
    }
}

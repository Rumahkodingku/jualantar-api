<?php

namespace App\Modules\Merchant\Http\Middleware;

use App\Modules\Merchant\Application\Operations\Services\MerchantOperationsAuthorization;
use App\Shared\Http\ProblemDetailsFactory;
use App\Shared\Result\ResultError;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Coarse gate for owner-only merchant operations: the authenticated caller must
 * own the merchant. Replaces the account-level Spatie permission middleware so
 * authorization no longer depends on global roles.
 */
final class EnsureMerchantOwner
{
    public function __construct(private readonly MerchantOperationsAuthorization $authorization) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->authorization->ownedMerchant()->isErr()) {
            return app(ProblemDetailsFactory::class)->problemFromResultError(
                $this->forbidden(),
                $request,
            );
        }

        return $next($request);
    }

    private function forbidden(): ResultError
    {
        return new ResultError(
            code: 'forbidden',
            message: 'Only the merchant owner can perform this action.',
            status: 403,
            title: 'Forbidden',
        );
    }
}

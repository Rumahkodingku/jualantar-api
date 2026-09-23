<?php

namespace App\Modules\Merchant\Http\Middleware;

use App\Modules\Merchant\Application\Operations\Services\MerchantOperationsAuthorization;
use App\Shared\Http\ProblemDetailsFactory;
use App\Shared\Result\ResultError;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Coarse gate for the merchant operations API: the authenticated caller must
 * resolve to a merchant context, either as the merchant owner or through an
 * outlet assignment. Capability-specific checks stay with
 * MerchantOperationsAuthorization::authorizeOutletAction() in the controllers.
 */
final class EnsureMerchantContext
{
    public function __construct(private readonly MerchantOperationsAuthorization $authorization) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->authorization->merchantContext()->isErr()) {
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
            message: 'You do not have a merchant context for this action.',
            status: 403,
            title: 'Forbidden',
        );
    }
}

<?php

namespace App\Modules\Communications\Contracts;

use App\Modules\Communications\Contracts\DataTransferObjects\CommunicationResult;
use App\Modules\Communications\Contracts\DataTransferObjects\SendCommunicationData;

/**
 * Public seam for out-of-app transactional communication delivery.
 *
 * Producers (future IdentityAccess/Merchant/etc. integrations) depend on this
 * contract only and must never import Communications domain models, provider
 * classes, Laravel Mail objects, or provider SDK types.
 */
interface Communications
{
    /**
     * Persist a delivery intent and queue it for asynchronous delivery.
     *
     * When an idempotency key is supplied and already exists, the existing
     * logical communication is returned instead of creating a duplicate.
     */
    public function send(SendCommunicationData $data): CommunicationResult;
}

<?php

namespace App\Modules\Communications\Application\Services;

use App\Modules\Communications\Application\Actions\SendCommunication;
use App\Modules\Communications\Contracts\Communications;
use App\Modules\Communications\Contracts\DataTransferObjects\CommunicationResult;
use App\Modules\Communications\Contracts\DataTransferObjects\SendCommunicationData;
use App\Modules\Communications\Domain\Exceptions\CommunicationException;

/**
 * Contract implementation for trusted application code.
 *
 * Delegates to the SendCommunication action so the domain rules live in one
 * place, and translates Result failures into the ApiException hierarchy
 * expected by callers of the public contract.
 */
final class EloquentCommunications implements Communications
{
    public function __construct(private readonly SendCommunication $sendCommunication) {}

    public function send(SendCommunicationData $data): CommunicationResult
    {
        $result = ($this->sendCommunication)($data);

        if ($result->isErr()) {
            $error = $result->error();

            throw new CommunicationException($error->message, $error->status, $error->code);
        }

        return $result->unwrap();
    }
}

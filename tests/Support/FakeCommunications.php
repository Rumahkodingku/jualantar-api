<?php

namespace Tests\Support;

use App\Modules\Communications\Contracts\Communications;
use App\Modules\Communications\Contracts\DataTransferObjects\CommunicationResult;
use App\Modules\Communications\Contracts\DataTransferObjects\SendCommunicationData;
use App\Modules\Communications\Contracts\Enums\CommunicationStatus;
use Illuminate\Support\Str;

/**
 * Test double for the Communications contract.
 *
 * Records every send so producer module tests can assert the delivery intent
 * without crossing the Communications module boundary.
 */
final class FakeCommunications implements Communications
{
    /**
     * @var list<SendCommunicationData>
     */
    public array $sent = [];

    public function send(SendCommunicationData $data): CommunicationResult
    {
        $this->sent[] = $data;

        return new CommunicationResult(
            communicationId: (string) Str::uuid(),
            status: CommunicationStatus::Queued,
            queued: true,
        );
    }

    public function last(): ?SendCommunicationData
    {
        if ($this->sent === []) {
            return null;
        }

        return $this->sent[array_key_last($this->sent)];
    }

    /**
     * @return list<SendCommunicationData>
     */
    public function ofType(string $type): array
    {
        return array_values(array_filter(
            $this->sent,
            fn (SendCommunicationData $data): bool => $data->type === $type,
        ));
    }
}

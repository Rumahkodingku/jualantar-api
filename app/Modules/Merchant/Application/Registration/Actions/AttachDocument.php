<?php

namespace App\Modules\Merchant\Application\Registration\Actions;

use App\Modules\Merchant\Application\Registration\Concerns\ReportsRegistrationErrors;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Storage\Contracts\ObjectStorage;
use App\Shared\Result\Result;

final class AttachDocument
{
    use ReportsRegistrationErrors;

    public function __construct(private readonly ObjectStorage $storage) {}

    /**
     * @param  array{document_type: string, object_key: string, file_name: string, mime_type: string, file_size: int}  $data
     */
    public function __invoke(Merchant $merchant, array $data): Result
    {
        if ($error = $this->requireEditable($merchant)) {
            return $error;
        }

        if (! str_starts_with($data['object_key'], "merchants/{$merchant->id}/")) {
            return $this->uploadInvalid('The object key does not belong to this merchant.');
        }

        if (! $this->storage->exists($data['object_key'])) {
            return $this->uploadInvalid('The uploaded object could not be found.');
        }

        $document = $merchant->documents()->create($data);

        return Result::ok($document);
    }
}

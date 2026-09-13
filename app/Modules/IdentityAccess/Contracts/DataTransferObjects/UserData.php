<?php

namespace App\Modules\IdentityAccess\Contracts\DataTransferObjects;

final readonly class UserData
{
    public function __construct(
        public string $id,
        public string $email,
        public ?string $phone,
        public bool $emailVerified,
    ) {}
}

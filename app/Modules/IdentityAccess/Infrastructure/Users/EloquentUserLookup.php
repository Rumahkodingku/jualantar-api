<?php

namespace App\Modules\IdentityAccess\Infrastructure\Users;

use App\Modules\IdentityAccess\Contracts\DataTransferObjects\UserData;
use App\Modules\IdentityAccess\Contracts\UserLookup;
use App\Modules\IdentityAccess\Domain\Exceptions\UserNotFoundException;
use App\Modules\IdentityAccess\Domain\Models\User;

final class EloquentUserLookup implements UserLookup
{
    public function user(string $userId): UserData
    {
        $user = User::query()->find($userId);

        if ($user === null) {
            throw new UserNotFoundException;
        }

        return $this->toData($user);
    }

    /**
     * @param  list<string>  $userIds
     * @return array<string, UserData>
     */
    public function usersByIds(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return User::query()
            ->whereKey($userIds)
            ->get()
            ->keyBy('id')
            ->map(fn (User $user): UserData => $this->toData($user))
            ->all();
    }

    private function toData(User $user): UserData
    {
        return new UserData(
            id: $user->id,
            email: $user->email,
            phone: $user->phone,
            emailVerified: $user->hasVerifiedEmail(),
        );
    }
}

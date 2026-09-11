<?php

namespace App\Modules\Customer\Application\Actions;

use App\Modules\Customer\Application\Concerns\ReportsAuthErrors;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Shared\Result\Result;
use Illuminate\Support\Facades\Hash;

final class Login
{
    use ReportsAuthErrors;

    /**
     * @param  array{email: string, password: string}  $credentials
     */
    public function __invoke(array $credentials): Result
    {
        $user = User::query()->where('email', $credentials['email'])->first();

        if ($user === null || ! Hash::check($credentials['password'], $user->password)) {
            return $this->invalidCredentials();
        }

        if (! $user->hasVerifiedEmail()) {
            return $this->emailNotVerified();
        }

        $token = $user->createToken('auth')->plainTextToken;

        return Result::ok([
            'user' => $user,
            'token' => $token,
        ]);
    }
}

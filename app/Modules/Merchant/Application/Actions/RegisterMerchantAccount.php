<?php

namespace App\Modules\Merchant\Application\Actions;

use App\Modules\IdentityAccess\Contracts\DataTransferObjects\UserData;
use App\Modules\IdentityAccess\Contracts\EmailVerification;
use App\Modules\IdentityAccess\Contracts\UserProvisioning;
use App\Modules\Merchant\Application\Concerns\ReportsRegistrationErrors;
use App\Shared\Result\Result;
use App\Shared\Support\Phone;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class RegisterMerchantAccount
{
    use ReportsRegistrationErrors;

    public function __construct(
        private readonly UserProvisioning $userProvisioning,
        private readonly EmailVerification $emailVerification,
    ) {}

    /**
     * Register a merchant identity account and send the verification email.
     *
     * @param  array{email: string, phone: string, password: string}  $data
     */
    public function __invoke(array $data): Result
    {
        $phone = Phone::normalize($data['phone']);

        try {
            $user = DB::transaction(fn (): UserData => $this->userProvisioning->provisionMerchant([
                'email' => $data['email'],
                'phone' => $phone,
                'password' => $data['password'],
            ]));
        } catch (UniqueConstraintViolationException) {
            return $this->duplicateAccount();
        }

        $this->emailVerification->send($user->id);

        Log::info('merchant_account_registered', ['user_id' => $user->id]);

        return Result::ok($user);
    }
}

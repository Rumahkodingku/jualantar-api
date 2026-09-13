<?php

namespace App\Modules\Customer\Application\Actions;

use App\Modules\Customer\Application\Concerns\ReportsRegistrationErrors;
use App\Modules\Customer\Domain\Models\Customer;
use App\Modules\IdentityAccess\Contracts\DataTransferObjects\UserData;
use App\Modules\IdentityAccess\Contracts\EmailVerification;
use App\Modules\IdentityAccess\Contracts\UserProvisioning;
use App\Shared\Result\Result;
use App\Shared\Support\Phone;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class RegisterCustomer
{
    use ReportsRegistrationErrors;

    public function __construct(
        private readonly UserProvisioning $userProvisioning,
        private readonly EmailVerification $emailVerification,
    ) {}

    /**
     * @param  array{email: string, phone: string, username: string, full_name: string, password: string}  $data
     */
    public function __invoke(array $data): Result
    {
        $fullName = $data['full_name'];
        $phone = Phone::normalize($data['phone']);

        try {
            $user = DB::transaction(function () use ($data, $fullName, $phone): UserData {
                $user = $this->userProvisioning->provisionCustomer([
                    'email' => $data['email'],
                    'phone' => $phone,
                    'password' => $data['password'],
                ]);

                Customer::create([
                    'user_id' => $user->id,
                    'username' => $data['username'],
                    'full_name' => $fullName,
                ]);

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            return $this->duplicateRegistration();
        }

        $this->emailVerification->send($user->id);

        Log::info('registration_success', ['user_id' => $user->id]);

        return Result::ok($user);
    }
}

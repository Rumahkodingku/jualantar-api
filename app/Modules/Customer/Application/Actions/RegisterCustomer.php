<?php

namespace App\Modules\Customer\Application\Actions;

use App\Modules\Customer\Application\Concerns\ReportsAuthErrors;
use App\Modules\Customer\Domain\Models\Customer;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Shared\Result\Result;
use App\Shared\Support\Phone;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class RegisterCustomer
{
    use ReportsAuthErrors;

    /**
     * @param  array{email: string, phone: string, username: string, full_name: string, password: string}  $data
     */
    public function __invoke(array $data): Result
    {
        $fullName = $data['full_name'];
        $phone = Phone::normalize($data['phone']);

        try {
            $user = DB::transaction(function () use ($data, $fullName, $phone): User {
                $user = User::create([
                    'name' => $fullName,
                    'email' => $data['email'],
                    'phone' => $phone,
                    'password' => $data['password'],
                ]);

                $user->assignRole('customer');

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

        $user->sendEmailVerificationNotification();

        Log::info('registration_success', ['user_id' => $user->id]);

        return Result::ok($user);
    }
}

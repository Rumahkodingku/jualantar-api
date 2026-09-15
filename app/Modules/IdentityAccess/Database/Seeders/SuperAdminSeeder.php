<?php

namespace App\Modules\IdentityAccess\Database\Seeders;

use App\Modules\IdentityAccess\Domain\Models\Role;
use App\Modules\IdentityAccess\Domain\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (Role::query()->where('name', Role::SUPER_ADMIN)->doesntExist()) {
            $this->call(RbacSeeder::class);
        }

        $email = (string) (env('SUPER_ADMIN_EMAIL') ?: 'superadmin@jualantar.test');
        $phone = (string) (env('SUPER_ADMIN_PHONE') ?: null);
        $password = (string) (env('SUPER_ADMIN_PASSWORD') ?: 'password');

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'phone' => $phone,
                'password' => $password,
                'email_verified_at' => now(),
            ],
        );

        $user->assignRole(Role::SUPER_ADMIN);
    }
}

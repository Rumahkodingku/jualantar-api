<?php

namespace Database\Seeders;

use App\Modules\BankDirectory\Database\Seeders\BankSeeder;
use App\Modules\Geography\Database\Seeders\RegionSeeder;
use App\Modules\IdentityAccess\Database\Seeders\TestUserSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(BankSeeder::class);
        $this->call(RegionSeeder::class);
        $this->call(TestUserSeeder::class);
    }
}

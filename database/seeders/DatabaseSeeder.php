<?php

namespace Database\Seeders;

use App\Modules\BankDirectory\Database\Seeders\BankSeeder;
use App\Modules\Geography\Database\Seeders\RegionSeeder;
use App\Modules\IdentityAccess\Database\Seeders\RbacSeeder;
use App\Modules\IdentityAccess\Database\Seeders\SuperAdminSeeder;
use App\Modules\Merchant\Database\Seeders\CatalogSeeder;
use App\Modules\Service\Database\Seeders\ServiceSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(BankSeeder::class);
        $this->call(RegionSeeder::class);
        $this->call(ServiceSeeder::class);
        $this->call(RbacSeeder::class);
        $this->call(SuperAdminSeeder::class);
        $this->call(CatalogSeeder::class);
    }
}

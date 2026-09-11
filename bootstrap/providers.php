<?php

use App\Modules\BankDirectory\BankDirectoryServiceProvider;
use App\Modules\Customer\CustomerServiceProvider;
use App\Modules\Geography\GeographyServiceProvider;
use App\Modules\IdentityAccess\IdentityAccessServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    IdentityAccessServiceProvider::class,
    CustomerServiceProvider::class,
    GeographyServiceProvider::class,
    BankDirectoryServiceProvider::class,
];

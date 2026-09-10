<?php

use App\Modules\BankDirectory\BankDirectoryServiceProvider;
use App\Modules\Geography\GeographyServiceProvider;
use App\Modules\IdentityAccess\IdentityAccessServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    IdentityAccessServiceProvider::class,
    GeographyServiceProvider::class,
    BankDirectoryServiceProvider::class,
];

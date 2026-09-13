<?php

use App\Modules\BankDirectory\BankDirectoryServiceProvider;
use App\Modules\Customer\CustomerServiceProvider;
use App\Modules\Geography\GeographyServiceProvider;
use App\Modules\IdentityAccess\IdentityAccessServiceProvider;
use App\Modules\Merchant\MerchantServiceProvider;
use App\Modules\Payout\PayoutServiceProvider;
use App\Modules\Service\ServiceServiceProvider;
use App\Modules\Storage\StorageServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    IdentityAccessServiceProvider::class,
    CustomerServiceProvider::class,
    GeographyServiceProvider::class,
    BankDirectoryServiceProvider::class,
    ServiceServiceProvider::class,
    StorageServiceProvider::class,
    PayoutServiceProvider::class,
    MerchantServiceProvider::class,
];

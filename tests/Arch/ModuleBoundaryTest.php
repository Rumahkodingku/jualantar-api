<?php

$modules = ['Customer', 'Geography', 'BankDirectory', 'Service', 'Merchant', 'Payout'];

foreach ($modules as $module) {
    arch("{$module}: Domain hanya dipakai dalam modulnya sendiri")
        ->expect("App\\Modules\\{$module}\\Domain")
        ->toOnlyBeUsedIn("App\\Modules\\{$module}");

    arch("{$module}: Application hanya dipakai dalam modulnya sendiri")
        ->expect("App\\Modules\\{$module}\\Application")
        ->toOnlyBeUsedIn("App\\Modules\\{$module}");
}

arch('IdentityAccess: Domain hanya dipakai IdentityAccess dan business module Customer')
    ->expect('App\Modules\IdentityAccess\Domain')
    ->toOnlyBeUsedIn(['App\Modules\IdentityAccess', 'App\Modules\Customer']);

arch('IdentityAccess: Application hanya dipakai dalam modulnya sendiri')
    ->expect('App\Modules\IdentityAccess\Application')
    ->toOnlyBeUsedIn('App\Modules\IdentityAccess');

arch('IdentityAccess: Infrastructure hanya dipakai dalam modulnya sendiri')
    ->expect('App\Modules\IdentityAccess\Infrastructure')
    ->toOnlyBeUsedIn('App\Modules\IdentityAccess');

arch('Shared Kernel tidak boleh bergantung ke modul bisnis mana pun')
    ->expect('App\Shared')
    ->not->toUse('App\Modules');

arch('modul bisnis tidak boleh memakai internal Spatie Permission secara langsung')
    ->expect(['App\Modules\Customer', 'App\Modules\Geography', 'App\Modules\BankDirectory', 'App\Modules\Service', 'App\Modules\Merchant', 'App\Modules\Payout'])
    ->not->toUse('Spatie\Permission');

arch('modul bisnis tidak boleh menangani credential/token langsung')
    ->expect(['App\Modules\Customer', 'App\Modules\Geography', 'App\Modules\BankDirectory', 'App\Modules\Service', 'App\Modules\Merchant', 'App\Modules\Payout'])
    ->not->toUse([
        'Illuminate\Support\Facades\Hash',
        'Illuminate\Support\Facades\Auth',
    ]);

arch('modul lain hanya mengakses IdentityAccess lewat Contracts')
    ->expect(['App\Modules\Geography', 'App\Modules\BankDirectory', 'App\Modules\Service', 'App\Modules\Merchant', 'App\Modules\Payout'])
    ->not->toUse('App\Modules\IdentityAccess\Domain');

arch('modul lain tidak menembus Application layer IdentityAccess')
    ->expect(['App\Modules\Customer', 'App\Modules\Geography', 'App\Modules\BankDirectory', 'App\Modules\Service', 'App\Modules\Merchant', 'App\Modules\Payout'])
    ->not->toUse('App\Modules\IdentityAccess\Application');

arch('modul lain tidak menembus Infrastructure layer IdentityAccess')
    ->expect(['App\Modules\Customer', 'App\Modules\Geography', 'App\Modules\BankDirectory', 'App\Modules\Service', 'App\Modules\Merchant', 'App\Modules\Payout'])
    ->not->toUse('App\Modules\IdentityAccess\Infrastructure');

arch('IdentityAccess tidak bergantung pada business module (tanpa circular dependency)')
    ->expect('App\Modules\IdentityAccess')
    ->not->toUse('App\Modules\Customer');

arch('Storage: Domain hanya dipakai dalam modulnya sendiri')
    ->expect('App\Modules\Storage\Domain')
    ->toOnlyBeUsedIn('App\Modules\Storage');

arch('Storage: Infrastructure hanya dipakai dalam modulnya sendiri')
    ->expect('App\Modules\Storage\Infrastructure')
    ->toOnlyBeUsedIn('App\Modules\Storage');

arch('Storage tidak bergantung pada modul bisnis mana pun')
    ->expect('App\Modules\Storage')
    ->not->toUse([
        'App\Modules\Customer',
        'App\Modules\Geography',
        'App\Modules\BankDirectory',
        'App\Modules\IdentityAccess',
        'App\Modules\Merchant',
        'App\Modules\Payout',
    ]);

arch('modul bisnis hanya boleh mengakses Storage lewat Contracts')
    ->expect(['App\Modules\Customer', 'App\Modules\Geography', 'App\Modules\BankDirectory', 'App\Modules\Service', 'App\Modules\IdentityAccess', 'App\Modules\Merchant', 'App\Modules\Payout'])
    ->not->toUse([
        'App\Modules\Storage\Domain',
        'App\Modules\Storage\Application',
        'App\Modules\Storage\Infrastructure',
    ]);

arch('modul lain hanya mengakses Service lewat Contracts')
    ->expect(['App\Modules\Merchant'])
    ->not->toUse([
        'App\Modules\Service\Application',
        'App\Modules\Service\Infrastructure',
    ]);

arch('modul lain hanya mengakses Geography lewat Contracts')
    ->expect(['App\Modules\Merchant'])
    ->not->toUse([
        'App\Modules\Geography\Application',
        'App\Modules\Geography\Infrastructure',
    ]);

arch('modul lain hanya mengakses BankDirectory lewat Contracts')
    ->expect(['App\Modules\Payout'])
    ->not->toUse([
        'App\Modules\BankDirectory\Application',
        'App\Modules\BankDirectory\Infrastructure',
    ]);

arch('modul lain hanya mengakses Payout lewat Contracts')
    ->expect(['App\Modules\Merchant'])
    ->not->toUse([
        'App\Modules\Payout\Domain',
        'App\Modules\Payout\Application',
        'App\Modules\Payout\Infrastructure',
    ]);

arch('Payout tidak bergantung pada Merchant')
    ->expect('App\Modules\Payout')
    ->not->toUse('App\Modules\Merchant');

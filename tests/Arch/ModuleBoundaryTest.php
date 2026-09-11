<?php

$modules = ['Customer', 'Geography', 'BankDirectory'];

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
    ->expect(['App\Modules\Customer', 'App\Modules\Geography', 'App\Modules\BankDirectory'])
    ->not->toUse('Spatie\Permission');

arch('modul bisnis tidak boleh menangani credential/token langsung')
    ->expect(['App\Modules\Customer', 'App\Modules\Geography', 'App\Modules\BankDirectory'])
    ->not->toUse([
        'Illuminate\Support\Facades\Hash',
        'Illuminate\Support\Facades\Auth',
    ]);

arch('modul lain hanya mengakses IdentityAccess lewat Contracts')
    ->expect(['App\Modules\Geography', 'App\Modules\BankDirectory'])
    ->not->toUse('App\Modules\IdentityAccess\Domain');

arch('modul lain tidak menembus Application layer IdentityAccess')
    ->expect(['App\Modules\Customer', 'App\Modules\Geography', 'App\Modules\BankDirectory'])
    ->not->toUse('App\Modules\IdentityAccess\Application');

arch('modul lain tidak menembus Infrastructure layer IdentityAccess')
    ->expect(['App\Modules\Customer', 'App\Modules\Geography', 'App\Modules\BankDirectory'])
    ->not->toUse('App\Modules\IdentityAccess\Infrastructure');

arch('IdentityAccess tidak bergantung pada business module (tanpa circular dependency)')
    ->expect('App\Modules\IdentityAccess')
    ->not->toUse('App\Modules\Customer');

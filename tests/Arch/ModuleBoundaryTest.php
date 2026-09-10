<?php

$modules = ['IdentityAccess', 'Geography', 'BankDirectory'];

foreach ($modules as $module) {
    arch("{$module}: Domain hanya dipakai dalam modulnya sendiri")
        ->expect("App\\Modules\\{$module}\\Domain")
        ->toOnlyBeUsedIn("App\\Modules\\{$module}");

    arch("{$module}: Application hanya dipakai dalam modulnya sendiri")
        ->expect("App\\Modules\\{$module}\\Application")
        ->toOnlyBeUsedIn("App\\Modules\\{$module}");
}

arch('Shared Kernel tidak boleh bergantung ke modul bisnis mana pun')
    ->expect('App\Shared')
    ->not->toUse('App\Modules');

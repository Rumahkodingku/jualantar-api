<?php

/**
 * Enforces the module boundary rules from docs/ARCHITECTURE.md (Bab 4 & 6):
 * Domain, Application and Infrastructure are private to their module; other
 * modules may only communicate through Contracts/.
 *
 * Test infrastructure under Tests\ is intentionally excluded because the shared
 * Tests\Support\InteractsWithModules helper builds cross-module fixtures.
 */
$modules = [
    'Customer' => ['Domain', 'Application'],
    'Geography' => ['Domain', 'Application', 'Infrastructure'],
    'BankDirectory' => ['Domain', 'Application', 'Infrastructure'],
    'Communications' => ['Domain', 'Application', 'Infrastructure'],
    'Service' => ['Domain', 'Application', 'Infrastructure'],
    'Merchant' => ['Domain', 'Application'],
    'Notifications' => ['Domain', 'Application', 'Infrastructure'],
    'Payout' => ['Domain', 'Infrastructure'],
    'IdentityAccess' => ['Domain', 'Application', 'Infrastructure'],
    'Storage' => ['Domain', 'Infrastructure'],
];

foreach ($modules as $module => $layers) {
    foreach ($layers as $layer) {
        arch("{$module}: {$layer} hanya dipakai dalam modulnya sendiri")
            ->expect("App\\Modules\\{$module}\\{$layer}")
            ->toOnlyBeUsedIn("App\\Modules\\{$module}")
            ->ignoring('Tests');
    }
}

arch('Shared Kernel tidak boleh bergantung ke modul bisnis mana pun')
    ->expect('App\Shared')
    ->not->toUse('App\Modules');

arch('modul Notifications tidak boleh bergantung ke internal modul lain')
    ->expect('App\Modules\Notifications')
    ->not->toUse([
        'App\Modules\BankDirectory',
        'App\Modules\Customer',
        'App\Modules\Geography',
        'App\Modules\IdentityAccess',
        'App\Modules\Merchant',
        'App\Modules\Payout',
        'App\Modules\Service',
        'App\Modules\Storage',
    ])
    ->ignoring('Tests');

arch('modul Communications tidak boleh bergantung ke internal modul lain')
    ->expect('App\Modules\Communications')
    ->not->toUse([
        'App\Modules\BankDirectory',
        'App\Modules\Customer',
        'App\Modules\Geography',
        'App\Modules\IdentityAccess',
        'App\Modules\Merchant',
        'App\Modules\Notifications',
        'App\Modules\Payout',
        'App\Modules\Service',
        'App\Modules\Storage',
    ])
    ->ignoring('Tests');

arch('Communications Contracts tidak boleh membocorkan tipe mail/provider')
    ->expect('App\Modules\Communications\Contracts')
    ->not->toUse([
        'Illuminate\Mail',
        'Illuminate\Notifications',
        'Symfony\Component\Mailer',
        'Symfony\Component\Mime',
    ]);

arch('Communications Application dan Domain tidak memakai tipe mail/provider')
    ->expect([
        'App\Modules\Communications\Application',
        'App\Modules\Communications\Domain',
    ])
    ->not->toUse([
        'Illuminate\Mail',
        'Illuminate\Notifications',
        'Symfony\Component\Mailer',
        'Symfony\Component\Mime',
    ]);

arch('modul bisnis tidak boleh memakai internal Spatie Permission secara langsung')
    ->expect(['App\Modules\Customer', 'App\Modules\Geography', 'App\Modules\BankDirectory', 'App\Modules\Service', 'App\Modules\Merchant', 'App\Modules\Payout'])
    ->not->toUse('Spatie\Permission');

arch('modul bisnis tidak boleh menangani credential/token langsung')
    ->expect(['App\Modules\Customer', 'App\Modules\Geography', 'App\Modules\BankDirectory', 'App\Modules\Service', 'App\Modules\Merchant', 'App\Modules\Payout'])
    ->not->toUse([
        'Illuminate\Support\Facades\Hash',
        'Illuminate\Support\Facades\Auth',
    ]);

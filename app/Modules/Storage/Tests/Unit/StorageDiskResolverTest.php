<?php

use App\Modules\Storage\Infrastructure\Filesystem\StorageDiskResolver;
use Illuminate\Support\Facades\Storage;

it('resolves the configured default disk', function () {
    config(['storage.default_disk' => 'local']);
    $fake = Storage::fake('local');

    expect((new StorageDiskResolver)->resolve())->toBe($fake);
});

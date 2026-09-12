<?php

namespace App\Modules\Storage\Infrastructure\Filesystem;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

final class StorageDiskResolver
{
    public function resolve(): FilesystemAdapter
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk((string) config('storage.default_disk'));

        return $disk;
    }
}

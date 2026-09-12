<?php

namespace App\Modules\Storage;

use App\Modules\Storage\Contracts\ObjectStorage;
use App\Modules\Storage\Infrastructure\Filesystem\LaravelFilesystemObjectStorage;
use Illuminate\Support\ServiceProvider;

final class StorageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ObjectStorage::class, LaravelFilesystemObjectStorage::class);
    }
}

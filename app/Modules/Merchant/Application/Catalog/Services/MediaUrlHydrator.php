<?php

namespace App\Modules\Merchant\Application\Catalog\Services;

use App\Modules\Merchant\Domain\Models\ProductMedia;
use App\Modules\Storage\Contracts\ObjectStorage;

/**
 * Hydrates a temporary download URL onto product media models so resources can
 * expose it without touching the storage abstraction themselves.
 */
final class MediaUrlHydrator
{
    public function __construct(
        private readonly ObjectStorage $storage,
    ) {}

    /**
     * @param  iterable<int, ProductMedia>  $media
     */
    public function hydrate(iterable $media): void
    {
        foreach ($media as $item) {
            $item->setAttribute('url', $this->url($item->storage_key));
        }
    }

    public function url(string $storageKey): ?string
    {
        try {
            return $this->storage->temporaryUrl(
                $storageKey,
                now()->addSeconds((int) config('storage.temporary_url.ttl', 300)),
            );
        } catch (\Throwable) {
            return null;
        }
    }
}

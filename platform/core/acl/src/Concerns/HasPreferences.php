<?php

namespace Kern\ACL\Concerns;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Kern\ACL\Models\UserMeta;
use Kern\Support\Services\Cache\Cache;

trait HasPreferences
{
    protected Collection $metaValues;

    protected bool $loadedMetaValues = false;

    public function meta(): HasMany
    {
        return $this->hasMany(UserMeta::class, 'user_id');
    }

    public function setMeta(string $key, mixed $value): bool
    {
        $meta = $this->meta()->firstOrCreate([
            'key' => $key,
        ]);

        return $meta->update(['value' => $value]);
    }

    public function getMeta(string $key, mixed $default = null): mixed
    {
        $this->loadMeta();

        $meta = $this->metaValues
            ->where('key', $key)
            ->first();

        if (! empty($meta)) {
            return Arr::get($meta, 'value');
        }

        return $default;
    }

    public function loadMeta(bool $force = false): void
    {
        if (! $this->loadedMetaValues || $force) {
            $cache = Cache::make(UserMeta::class);

            $cacheKey = 'user-meta-'.$this->getKey();

            $this->metaValues = collect($cache->remember(
                $cacheKey,
                600,
                fn (): array => $this->meta()->get()->toArray(),
            ));

            $this->loadedMetaValues = true;
        }
    }
}

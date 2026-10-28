<?php

namespace Kern\Support\Services\Cache;

use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Cache\CacheManager;
use Kern\Base\Facades\BaseHelper;

class Cache implements CacheInterface
{
    final public function __construct(
        protected CacheManager $cache,
        protected ?string $cacheGroup,
        protected array $config = []
    ) {
        $this->config += ['cache_time' => 600];
    }

    public static function make(string $group): static
    {
        return new static(app(CacheManager::class), $group);
    }

    public function generateCacheKey(string $key): string
    {
        return 'kern:'.md5($this->cacheGroup ?? '').':'.$key;
    }

    protected function indexKey(): string
    {
        return $this->generateCacheKey('__keys');
    }

    public function get(string $key): mixed
    {
        return $this->cache->get($this->generateCacheKey($key));
    }

    public function has(string $key): bool
    {
        return $this->cache->has($this->generateCacheKey($key));
    }

    public function put(string $key, mixed $value, Closure|DateTimeInterface|DateInterval|int|null $ttl = null): bool
    {
        $ttl = value($ttl) ?? $this->config['cache_time'];

        return $this->cache->lock($this->indexKey().':lock', 10)->block(5, function () use ($key, $value, $ttl): bool {
            $cacheKey = $this->generateCacheKey($key);
            $keys = $this->cache->get($this->indexKey(), []);
            $keys[$cacheKey] = true;
            $this->cache->forever($this->indexKey(), $keys);

            return $ttl === -1
                ? $this->cache->forever($cacheKey, $value)
                : $this->cache->put($cacheKey, $value, $ttl);
        });
    }

    public function forever(string $key, mixed $value): bool
    {
        return $this->put($key, $value, -1);
    }

    public function remember(string $key, Closure|DateTimeInterface|DateInterval|int|null $ttl, Closure $callback): mixed
    {
        $value = $this->get($key);
        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->put($key, $value, $ttl);

        return $value;
    }

    public function rememberForever(string $key, Closure $callback): mixed
    {
        return $this->remember($key, -1, $callback);
    }

    public function forget(string $key): bool
    {
        return $this->cache->forget($this->generateCacheKey($key));
    }

    public function flush(): bool
    {
        return $this->cache->lock($this->indexKey().':lock', 10)->block(5, function (): bool {
            foreach (array_keys($this->cache->get($this->indexKey(), [])) as $key) {
                $this->cache->forget($key);
            }
            $this->cache->forget($this->indexKey());

            return true;
        });
    }

    public function generateCacheKeyFromInput(): string
    {
        return hash('sha256', json_encode([request()->input(), BaseHelper::getHomepageUrl()], JSON_THROW_ON_ERROR));
    }
}

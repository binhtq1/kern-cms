<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache as LaravelCache;
use Illuminate\Support\Facades\File;
use Kern\Support\Services\Cache\Cache;
use Tests\TestCase;

class GroupedCacheTest extends TestCase
{
    public function test_group_invalidation_and_forget_work_with_serialized_file_cache(): void
    {
        $directory = sys_get_temp_dir().'/kern-cache-'.uniqid();
        config(['cache.default' => 'file', 'cache.stores.file.path' => $directory, 'cache.stores.file.lock_path' => $directory]);
        LaravelCache::purge('file');

        try {
            $pages = Cache::make('pages');
            $menus = Cache::make('menus');
            $pages->put('one', ['title' => 'Kern']);
            $pages->forever('two', false);
            $menus->put('one', 'Menu');
            $this->assertSame(['title' => 'Kern'], $pages->get('one'));
            $this->assertFalse($pages->remember('two', 60, fn () => 'unexpected'));

            $pages->forget('one');
            $this->assertFalse($pages->has('one'));
            $pages->flush();
            $this->assertNull($pages->get('two'));
            $this->assertSame('Menu', $menus->get('one'));
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_a_zero_ttl_removes_an_entry_and_expired_values_are_recomputed(): void
    {
        config(['cache.default' => 'array']);
        LaravelCache::purge('array');

        $cache = Cache::make('ttl');
        $cache->put('key', 'value', 1);
        $this->travel(2)->seconds();
        $this->assertSame('fresh', $cache->remember('key', 60, fn () => 'fresh'));
        $cache->put('key', 'removed', 0);
        $this->assertFalse($cache->has('key'));
    }

    public function test_group_invalidation_and_zero_ttl_work_on_the_configured_store(): void
    {
        $pages = Cache::make('configured-store-pages');
        $menus = Cache::make('configured-store-menus');

        try {
            $pages->forever('cached-false', false);
            $pages->put('expired', 'removed', 0);
            $menus->put('navigation', 'Keep menu');

            $this->assertFalse($pages->remember('cached-false', 60, fn () => 'unexpected'));
            $this->assertFalse($pages->has('expired'));
            $pages->flush();
            $this->assertNull($pages->get('cached-false'));
            $this->assertSame('Keep menu', $menus->get('navigation'));
        } finally {
            $pages->flush();
            $menus->flush();
        }
    }
}

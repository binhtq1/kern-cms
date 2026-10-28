<?php

namespace Kern\Base\GlobalSearch;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Kern\Base\Contracts\GlobalSearchableProvider as GlobalSearchableProviderContract;
use LogicException;

abstract class GlobalSearchableProvider implements GlobalSearchableProviderContract
{
    public function search(string $keyword): Collection
    {
        throw new LogicException('Please implement the search() method.');
    }

    protected function stringContains(?string $haystack, ?string $needle): bool
    {
        return Str::contains(Str::lower((string) $haystack), Str::lower((string) $needle));
    }
}

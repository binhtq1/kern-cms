<?php

namespace Kern\Blog\Repositories\Interfaces;

use Illuminate\Support\Collection;
use Kern\Support\Repositories\Interfaces\RepositoryContract;

interface TagInterface extends RepositoryContract
{
    public function getDataSiteMap(): Collection;

    public function getPopularTags(int $limit, array $with = ['slugable'], array $withCount = ['posts']): Collection;

    public function getAllTags(bool $active = true): Collection;
}

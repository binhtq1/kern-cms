<?php

namespace Kern\Page\Repositories\Interfaces;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Kern\Support\Repositories\Interfaces\RepositoryContract;

interface PageInterface extends RepositoryContract
{
    public function getDataSiteMap(): Collection;

    public function whereIn(array $array, array $select = []): Collection;

    public function getSearch(?string $query, int $limit = 10): Collection|LengthAwarePaginator;

    public function getAllPages(bool $active = true): Collection;
}

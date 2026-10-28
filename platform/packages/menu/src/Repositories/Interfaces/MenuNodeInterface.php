<?php

namespace Kern\Menu\Repositories\Interfaces;

use Illuminate\Database\Eloquent\Collection;
use Kern\Support\Repositories\Interfaces\RepositoryContract;

interface MenuNodeInterface extends RepositoryContract
{
    public function getByMenuId(int|string $menuId, int|string|null $parentId, array $select = ['*'], array $with = ['child']): Collection;
}

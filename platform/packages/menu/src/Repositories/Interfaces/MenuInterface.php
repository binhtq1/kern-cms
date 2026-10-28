<?php

namespace Kern\Menu\Repositories\Interfaces;

use Kern\Base\Models\BaseModel;
use Kern\Support\Repositories\Interfaces\RepositoryContract;

interface MenuInterface extends RepositoryContract
{
    public function findBySlug(string $slug, bool $active, array $select = [], array $with = []): ?BaseModel;

    public function createSlug(string $name): string;
}

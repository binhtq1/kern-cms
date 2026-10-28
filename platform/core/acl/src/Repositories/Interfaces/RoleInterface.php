<?php

namespace Kern\ACL\Repositories\Interfaces;

use Kern\Support\Repositories\Interfaces\RepositoryContract;

interface RoleInterface extends RepositoryContract
{
    public function createSlug(string $name, int|string $id): string;
}

<?php

namespace Kern\Block\Repositories\Interfaces;

use Kern\Support\Repositories\Interfaces\RepositoryContract;

interface BlockInterface extends RepositoryContract
{
    public function createSlug(?string $name, int|string|null $id): string;
}

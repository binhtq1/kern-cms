<?php

namespace Kern\Block\Repositories\Eloquent;

use Kern\Block\Models\Block;
use Kern\Block\Repositories\Interfaces\BlockInterface;
use Kern\Support\Repositories\Eloquent\RepositoryAbstract;

class BlockRepository extends RepositoryAbstract implements BlockInterface
{
    public function createSlug(?string $name, int|string|null $id): string
    {
        return Block::createSlug($name, $id, 'alias');
    }
}

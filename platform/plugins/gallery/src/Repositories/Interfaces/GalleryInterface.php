<?php

namespace Kern\Gallery\Repositories\Interfaces;

use Illuminate\Support\Collection;
use Kern\Support\Repositories\Interfaces\RepositoryContract;

interface GalleryInterface extends RepositoryContract
{
    public function getAll(array $with = ['slugable', 'user'], int $limit = 0): Collection;

    public function getDataSiteMap(): Collection;

    public function getFeaturedGalleries(int $limit, array $with = ['slugable', 'user']): Collection;
}

<?php

namespace Kern\Blog\Repositories\Interfaces;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Kern\Base\Enums\BaseStatusEnum;
use Kern\Base\Models\BaseModel;
use Kern\Blog\Models\Category;
use Kern\Support\Repositories\Interfaces\RepositoryContract;

interface CategoryInterface extends RepositoryContract
{
    public function getDataSiteMap(): Collection;

    public function getFeaturedCategories(?int $limit, array $with = []): Collection;

    public function getAllCategories(array $condition = [], array $with = []): Collection;

    public function getCategoryById(int|string|null $id): ?Category;

    public function getCategories(array $select, array $orderBy, array $conditions = ['status' => BaseStatusEnum::PUBLISHED]): Collection;

    public function getAllRelatedChildrenIds(int|string|null|BaseModel $id): array;

    public function getAllCategoriesWithChildren(array $condition = [], array $with = [], array $select = ['*']): Collection;

    public function getFilters(array $filters): LengthAwarePaginator;

    public function getPopularCategories(int $limit, array $with = ['slugable'], array $withCount = ['posts']): Collection;
}

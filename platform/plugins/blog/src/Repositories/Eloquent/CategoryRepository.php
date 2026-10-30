<?php

namespace Kern\Blog\Repositories\Eloquent;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Kern\Base\Enums\BaseStatusEnum;
use Kern\Base\Models\BaseModel;
use Kern\Blog\Models\Category;
use Kern\Blog\Repositories\Interfaces\CategoryInterface;
use Kern\Support\Repositories\Eloquent\RepositoryAbstract;

class CategoryRepository extends RepositoryAbstract implements CategoryInterface
{
    public function getDataSiteMap(): Collection
    {
        $data = $this->model
            ->with('slugable')
            ->wherePublished()
            ->select(['id', 'name', 'updated_at'])
            ->orderByDesc('created_at');

        return $this->applyBeforeExecuteQuery($data)->get();
    }

    public function getFeaturedCategories(?int $limit, array $with = []): Collection
    {
        $data = $this->model
            ->with(array_merge(['slugable'], $with))
            ->where([
                'status' => BaseStatusEnum::PUBLISHED,
                'is_featured' => 1,
            ])
            ->select([
                'id',
                'name',
                'description',
                'icon',
            ])
            ->oldest('order')->latest()
            ->limit($limit);

        return $this->applyBeforeExecuteQuery($data)->get();
    }

    public function getAllCategories(array $condition = [], array $with = []): Collection
    {
        $data = $this->model->with('slugable');
        if (! empty($condition)) {
            $data = $data->where($condition);
        }

        $data = $data
            ->wherePublished()
            ->orderBy('order')
            ->orderByDesc('created_at');

        if ($with) {
            $data = $data->with($with);
        }

        return $this->applyBeforeExecuteQuery($data)->get();
    }

    public function getCategoryById(int|string|null $id): ?Category
    {
        $data = $this->model->with('slugable')->where([
            'id' => $id,
            'status' => BaseStatusEnum::PUBLISHED,
        ]);

        return $this->applyBeforeExecuteQuery($data, true)->first();
    }

    public function getCategories(array $select, array $orderBy, array $conditions = ['status' => BaseStatusEnum::PUBLISHED]): Collection
    {
        $data = $this->model
            ->with('slugable')
            ->select($select);

        if ($conditions) {
            $data = $data->where($conditions);
        }

        foreach ($orderBy as $by => $direction) {
            $data = $data->oldest($by);
        }

        return $this->applyBeforeExecuteQuery($data)->get();
    }

    public function getAllRelatedChildrenIds(int|string|null|BaseModel $id): array
    {
        if ($id instanceof BaseModel) {
            $model = $id;
        } else {
            $model = $this->getFirstBy(['id' => $id]);
        }

        if (! $model) {
            return [];
        }

        $result = [];

        $children = $model->children()->select('id')->get();

        foreach ($children as $child) {
            $result[] = $child->id;
            $result = array_merge($this->getAllRelatedChildrenIds($child), $result);
        }

        $this->resetModel();

        return array_unique($result);
    }

    public function getAllCategoriesWithChildren(array $condition = [], array $with = [], array $select = ['*']): Collection
    {
        $data = $this->model
            ->where($condition)
            ->with($with)
            ->select($select);

        return $this->applyBeforeExecuteQuery($data)->get();
    }

    public function getFilters(array $filters): LengthAwarePaginator
    {
        $orderBy = $filters['order_by'] ?? 'created_at';

        $order = $filters['order'] ?? 'ASC';

        $data = $this->model->wherePublished()->orderBy($orderBy, $order);

        return $this->applyBeforeExecuteQuery($data)->paginate((int) $filters['per_page']);
    }

    public function getPopularCategories(int $limit, array $with = ['slugable'], array $withCount = ['posts']): Collection
    {
        $data = $this->model
            ->with($with)
            ->withCount($withCount)
            ->orderByDesc('posts_count')
            ->oldest('order')->latest()
            ->wherePublished()
            ->limit($limit);

        return $this->applyBeforeExecuteQuery($data)->get();
    }
}

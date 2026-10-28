<?php

namespace Kern\Page\Repositories\Eloquent;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Kern\Page\Repositories\Interfaces\PageInterface;
use Kern\Support\Repositories\Eloquent\RepositoryAbstract;

class PageRepository extends RepositoryAbstract implements PageInterface
{
    public function getDataSiteMap(): Collection
    {
        $data = $this->model
            ->wherePublished()
            ->orderByDesc('created_at')
            ->select(['id', 'name', 'updated_at'])
            ->with('slugable');

        return $this->applyBeforeExecuteQuery($data)->get();
    }

    public function whereIn(array $array, array $select = []): Collection
    {
        $pages = $this->model
            ->whereIn('id', $array)
            ->wherePublished();

        if (empty($select)) {
            $select = ['*'];
        }

        $data = $pages
            ->select($select)
            ->orderBy('created_at');

        return $this->applyBeforeExecuteQuery($data)->get();
    }

    public function getSearch(?string $query, int $limit = 10): Collection|LengthAwarePaginator
    {
        $pages = $this->model->wherePublished();
        foreach (explode(' ', $query) as $term) {
            $pages = $pages->where('name', 'LIKE', '%'.$term.'%');
        }

        $data = $pages
            ->orderByDesc('created_at')
            ->limit($limit);

        return $this->applyBeforeExecuteQuery($data)->get();
    }

    public function getAllPages(bool $active = true): Collection
    {
        $data = $this->model;

        if ($active) {
            $data = $data->wherePublished();
        }

        return $this->applyBeforeExecuteQuery($data)->get();
    }
}

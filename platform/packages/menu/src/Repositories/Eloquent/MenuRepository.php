<?php

namespace Kern\Menu\Repositories\Eloquent;

use Kern\Base\Models\BaseModel;
use Kern\Menu\Models\Menu;
use Kern\Menu\Repositories\Interfaces\MenuInterface;
use Kern\Support\Repositories\Eloquent\RepositoryAbstract;

class MenuRepository extends RepositoryAbstract implements MenuInterface
{
    public function findBySlug(string $slug, bool $active, array $select = [], array $with = []): ?BaseModel
    {
        $data = $this->model->where('slug', $slug);

        if ($active) {
            $data = $data->wherePublished();
        }

        if (! empty($select)) {
            $data = $data->select($select);
        }

        if (! empty($with)) {
            $data = $data->with($with);
        }

        $data = $this->applyBeforeExecuteQuery($data, true)->first();

        $this->resetModel();

        return $data;
    }

    public function createSlug(string $name): string
    {
        return Menu::createSlug($name, null);
    }
}

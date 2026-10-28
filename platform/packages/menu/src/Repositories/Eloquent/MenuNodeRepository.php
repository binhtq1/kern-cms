<?php

namespace Kern\Menu\Repositories\Eloquent;

use Illuminate\Database\Eloquent\Collection;
use Kern\Menu\Repositories\Interfaces\MenuNodeInterface;
use Kern\Support\Repositories\Eloquent\RepositoryAbstract;

class MenuNodeRepository extends RepositoryAbstract implements MenuNodeInterface
{
    public function getByMenuId(int|string $menuId, int|string|null $parentId, array $select = ['*'], array $with = ['child']): Collection
    {
        $data = $this->model
            ->with($with)
            ->where([
                'menu_id' => $menuId,
                'parent_id' => $parentId,
            ]);

        if (! empty($select)) {
            $data = $data->select($select);
        }

        $data = $data->orderBy('position')->get();

        $this->resetModel();

        return $data;
    }
}

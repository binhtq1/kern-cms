<?php

namespace Kern\Widget\Repositories\Eloquent;

use Illuminate\Database\Eloquent\Collection;
use Kern\Support\Repositories\Eloquent\RepositoryAbstract;
use Kern\Widget\Repositories\Interfaces\WidgetInterface;

class WidgetRepository extends RepositoryAbstract implements WidgetInterface
{
    public function getByTheme(string $theme): Collection
    {
        $data = $this->model->where('theme', $theme)->get();
        $this->resetModel();

        return $data;
    }
}

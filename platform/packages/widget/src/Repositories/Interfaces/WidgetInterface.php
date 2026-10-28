<?php

namespace Kern\Widget\Repositories\Interfaces;

use Illuminate\Database\Eloquent\Collection;
use Kern\Support\Repositories\Interfaces\RepositoryContract;

interface WidgetInterface extends RepositoryContract
{
    public function getByTheme(string $theme): Collection;
}

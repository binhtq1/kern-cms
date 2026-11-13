<?php

namespace Kern\Contact\Repositories\Interfaces;

use Illuminate\Database\Eloquent\Collection;
use Kern\Support\Repositories\Interfaces\RepositoryContract;

interface ContactInterface extends RepositoryContract
{
    public function getUnread(array $select = ['*']): Collection;

    public function countUnread(): int;
}

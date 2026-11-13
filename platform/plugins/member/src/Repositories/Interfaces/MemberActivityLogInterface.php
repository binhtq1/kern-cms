<?php

namespace Kern\Member\Repositories\Interfaces;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Kern\Support\Repositories\Interfaces\RepositoryContract;

interface MemberActivityLogInterface extends RepositoryContract
{
    public function getAllLogs(int|string $memberId, int $paginate = 10): LengthAwarePaginator;
}

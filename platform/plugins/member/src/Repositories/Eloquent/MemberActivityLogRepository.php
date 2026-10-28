<?php

namespace Kern\Member\Repositories\Eloquent;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Kern\Member\Repositories\Interfaces\MemberActivityLogInterface;
use Kern\Support\Repositories\Eloquent\RepositoryAbstract;

class MemberActivityLogRepository extends RepositoryAbstract implements MemberActivityLogInterface
{
    public function getAllLogs(int|string $memberId, int $paginate = 10): LengthAwarePaginator
    {
        return $this->model
            ->where('member_id', $memberId)
            ->latest('created_at')
            ->paginate($paginate);
    }
}

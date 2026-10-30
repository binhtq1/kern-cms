<?php

namespace Kern\Contact\Repositories\Eloquent;

use Illuminate\Database\Eloquent\Collection;
use Kern\Contact\Enums\ContactStatusEnum;
use Kern\Contact\Repositories\Interfaces\ContactInterface;
use Kern\Support\Repositories\Eloquent\RepositoryAbstract;

class ContactRepository extends RepositoryAbstract implements ContactInterface
{
    public function getUnread(array $select = ['*']): Collection
    {
        $data = $this->model
            ->where('status', ContactStatusEnum::UNREAD)
            ->select($select)->latest()
            ->get();

        $this->resetModel();

        return $data;
    }

    public function countUnread(): int
    {
        $data = $this->model->where('status', ContactStatusEnum::UNREAD)->count();
        $this->resetModel();

        return $data;
    }
}

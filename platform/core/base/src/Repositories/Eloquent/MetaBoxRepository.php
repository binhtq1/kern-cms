<?php

namespace Kern\Base\Repositories\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Kern\Base\Models\BaseModel;
use Kern\Base\Models\BaseQueryBuilder;
use Kern\Base\Models\MetaBox;
use Kern\Base\Repositories\Interfaces\MetaBoxInterface;
use Kern\Support\Repositories\Eloquent\RepositoryAbstract;

class MetaBoxRepository extends RepositoryAbstract implements MetaBoxInterface
{
    public function __construct(protected BaseModel|BaseQueryBuilder|Builder|Model $model)
    {
        parent::__construct(new MetaBox);
    }
}

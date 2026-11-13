<?php

namespace Kern\Language\Repositories\Interfaces;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Kern\Base\Models\BaseModel;
use Kern\Language\Models\Language;
use Kern\Support\Repositories\Interfaces\RepositoryContract;

interface LanguageInterface extends RepositoryContract
{
    public function getActiveLanguage(array $select = ['*']): Collection;

    public function getDefaultLanguage(array $select = ['*']): BaseModel|Model|Language|null;
}

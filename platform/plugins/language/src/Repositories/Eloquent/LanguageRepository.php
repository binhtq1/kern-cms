<?php

namespace Kern\Language\Repositories\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Kern\Base\Models\BaseModel;
use Kern\Language\Models\Language;
use Kern\Language\Repositories\Interfaces\LanguageInterface;
use Kern\Support\Repositories\Eloquent\RepositoryAbstract;

class LanguageRepository extends RepositoryAbstract implements LanguageInterface
{
    public function getActiveLanguage(array $select = ['*']): Collection
    {
        $data = $this->model->orderBy('lang_order')->select($select)->get();
        $this->resetModel();

        return $data;
    }

    public function getDefaultLanguage(array $select = ['*']): BaseModel|Model|Language|null
    {
        $data = $this->model->where('lang_is_default', 1)->select($select)->first();
        $this->resetModel();

        return $data;
    }
}

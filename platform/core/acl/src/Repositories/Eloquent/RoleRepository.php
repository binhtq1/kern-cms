<?php

namespace Kern\ACL\Repositories\Eloquent;

use Illuminate\Support\Str;
use Kern\ACL\Repositories\Interfaces\RoleInterface;
use Kern\Support\Repositories\Eloquent\RepositoryAbstract;

class RoleRepository extends RepositoryAbstract implements RoleInterface
{
    public function createSlug(string $name, int|string $id): string
    {
        $slug = Str::slug($name);
        $index = 1;
        $baseSlug = $slug;
        while ($this->model->where('slug', $slug)->where('id', '!=', $id)->exists()) {
            $slug = $baseSlug.'-'.$index++;
        }

        if (empty($slug)) {
            $slug = time();
        }

        $this->resetModel();

        return $slug;
    }
}

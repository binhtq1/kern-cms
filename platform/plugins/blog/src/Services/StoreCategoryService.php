<?php

namespace Kern\Blog\Services;

use Illuminate\Http\Request;
use Kern\Blog\Models\Post;
use Kern\Blog\Services\Abstracts\StoreCategoryServiceAbstract;

class StoreCategoryService extends StoreCategoryServiceAbstract
{
    public function execute(Request $request, Post $post): void
    {
        $post->categories()->sync($request->input('categories', []));
    }
}

<?php

namespace Kern\Blog\Services\Abstracts;

use Illuminate\Http\Request;
use Kern\Blog\Models\Post;

abstract class StoreTagServiceAbstract
{
    abstract public function execute(Request $request, Post $post): void;
}

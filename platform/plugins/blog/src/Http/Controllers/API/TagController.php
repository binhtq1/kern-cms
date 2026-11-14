<?php

namespace Kern\Blog\Http\Controllers\API;

use Illuminate\Http\Request;
use Kern\Base\Http\Controllers\BaseController;
use Kern\Blog\Http\Resources\TagResource;
use Kern\Blog\Models\Tag;

class TagController extends BaseController
{
    /**
     * List tags
     *
     * @group Blog
     */
    public function index(Request $request)
    {
        $data = Tag::query()
            ->wherePublished()
            ->with('slugable')
            ->paginate($request->integer('per_page', 10) ?: 10);

        return $this
            ->httpResponse()
            ->setData(TagResource::collection($data))
            ->toApiResponse();
    }
}

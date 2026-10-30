<?php

namespace Kern\Blog\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Kern\Blog\Models\Category;

/**
 * @mixin Category
 */
class CategoryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'url' => $this->url,
            'description' => $this->description,
        ];
    }
}

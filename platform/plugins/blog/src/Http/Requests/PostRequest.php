<?php

namespace Kern\Blog\Http\Requests;

use Illuminate\Validation\Rule;
use Kern\Base\Enums\BaseStatusEnum;
use Kern\Base\Rules\MediaImageRule;
use Kern\Base\Rules\OnOffRule;
use Kern\Blog\Models\Category;
use Kern\Blog\Supports\PostFormat;
use Kern\Member\Models\Member;
use Kern\Support\Http\Requests\Request;

class PostRequest extends Request
{
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:250'],
            'description' => ['nullable', 'string', 'max:400'],
            'content' => ['nullable', 'string', 'max:300000'],
            'tag' => ['nullable', 'string', 'max:400'],
            'categories' => ['sometimes', 'array'],
            'categories.*' => ['sometimes', Rule::exists((new Category)->getTable(), 'id')],
            'status' => Rule::in(BaseStatusEnum::values()),
            'is_featured' => [new OnOffRule],
            'image' => ['nullable', 'string', new MediaImageRule],
        ];

        if (is_plugin_active('member')) {
            $rules['author_id'] = ['nullable', Rule::exists(Member::class, 'id')];
        }

        $postFormats = PostFormat::getPostFormats(true);

        if (count($postFormats) > 1) {
            $rules['format_type'] = Rule::in(array_keys($postFormats));
        }

        return $rules;
    }
}

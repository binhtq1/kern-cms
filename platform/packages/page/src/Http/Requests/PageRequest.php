<?php

namespace Kern\Page\Http\Requests;

use Illuminate\Validation\Rule;
use Kern\Base\Enums\BaseStatusEnum;
use Kern\Base\Rules\MediaImageRule;
use Kern\Page\Supports\Template;
use Kern\Support\Http\Requests\Request;

class PageRequest extends Request
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:400'],
            'content' => ['nullable', 'string'],
            'template' => [Rule::in(array_keys(Template::getPageTemplates()))],
            'status' => [Rule::in(BaseStatusEnum::values())],
            'banner_image' => ['nullable', 'string', new MediaImageRule],
            'banner_image_input' => ['nullable', new MediaImageRule],
            'image' => ['nullable', 'string', new MediaImageRule],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[^<>\\s]*$/u'],
            'is_slug_editable' => ['sometimes', 'boolean'],
            'seo_meta' => ['nullable', 'array:seo_title,seo_description,seo_image,index'],
            'seo_meta.index' => ['nullable', Rule::in(['index', 'noindex'])],
            'seo_meta.seo_title' => ['nullable', 'string', 'max:255'],
            'seo_meta.seo_description' => ['nullable', 'string', 'max:1000'],
            'seo_meta.seo_image' => ['nullable', 'string', new MediaImageRule],
            'seo_meta_image' => ['nullable', 'string', new MediaImageRule],
            'gallery' => ['nullable', 'json', 'max:1000000'],
            'custom_fields' => ['nullable', 'json', 'max:1000000'],
            'ref_lang' => ['nullable', 'string', 'max:20'],
            'language' => ['nullable', 'string', 'max:20'],
        ];
    }
}

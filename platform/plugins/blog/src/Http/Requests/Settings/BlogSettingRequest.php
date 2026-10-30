<?php

namespace Kern\Blog\Http\Requests\Settings;

use Illuminate\Validation\Rule;
use Kern\Base\Rules\OnOffRule;
use Kern\Support\Http\Requests\Request;

class BlogSettingRequest extends Request
{
    public function rules(): array
    {
        return [
            'blog_post_schema_enabled' => new OnOffRule,
            'blog_post_schema_type' => [
                'nullable',
                'string',
                Rule::in(['NewsArticle', 'News', 'Article', 'BlogPosting']),
            ],
        ];
    }
}

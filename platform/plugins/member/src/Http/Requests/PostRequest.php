<?php

namespace Kern\Member\Http\Requests;

use Kern\Base\Rules\MediaImageRule;
use Kern\Blog\Http\Requests\PostRequest as BasePostRequest;

class PostRequest extends BasePostRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'image_input' => ['nullable', new MediaImageRule],
        ];
    }
}

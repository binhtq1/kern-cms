<?php

namespace Kern\Gallery\Http\Requests;

use Illuminate\Validation\Rule;
use Kern\Base\Enums\BaseStatusEnum;
use Kern\Base\Rules\MediaImageRule;
use Kern\Base\Rules\OnOffRule;
use Kern\Support\Http\Requests\Request;

class GalleryRequest extends Request
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:250'],
            'description' => ['required', 'string', 'max:10000'],
            'order' => ['required', 'integer', 'min:0', 'max:127'],
            'status' => [Rule::in(BaseStatusEnum::values())],
            'is_featured' => [new OnOffRule],
            'image' => ['nullable', 'string', new MediaImageRule],
        ];
    }
}

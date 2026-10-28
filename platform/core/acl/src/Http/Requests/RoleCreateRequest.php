<?php

namespace Kern\ACL\Http\Requests;

use Kern\Base\Rules\OnOffRule;
use Kern\Support\Http\Requests\Request;

class RoleCreateRequest extends Request
{
    public function rules(): array
    {
        return [
            'flags' => ['sometimes', 'array'],
            'flags.*' => ['string'],
            'name' => ['required', 'string', 'max:120', 'min:3'],
            'description' => ['nullable', 'string', 'string', 'max:255'],
            'is_default' => [new OnOffRule],
        ];
    }
}

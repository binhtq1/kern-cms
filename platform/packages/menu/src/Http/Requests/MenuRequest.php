<?php

namespace Kern\Menu\Http\Requests;

use Illuminate\Validation\Rule;
use Kern\Base\Enums\BaseStatusEnum;
use Kern\Menu\Facades\Menu;
use Kern\Support\Http\Requests\Request;

class MenuRequest extends Request
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'deleted_nodes' => ['nullable', 'string'],
            'menu_nodes' => ['nullable', 'string', 'json'],
            'locations' => ['nullable', 'array'],
            'locations.*' => ['string', Rule::in(array_keys(Menu::getMenuLocations()))],
            'status' => Rule::in(BaseStatusEnum::values()),
        ];
    }
}

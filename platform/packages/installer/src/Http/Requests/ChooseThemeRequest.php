<?php

namespace Kern\Installer\Http\Requests;

use Illuminate\Validation\Rule;
use Kern\Support\Http\Requests\Request;
use Kern\Theme\Facades\Manager;

class ChooseThemeRequest extends Request
{
    public function rules(): array
    {
        return [
            'theme' => ['required', 'string', Rule::in(array_keys(Manager::getThemes()))],
        ];
    }
}

<?php

namespace Kern\Widget\Http\Requests;

use Illuminate\Validation\Rule;
use Kern\Support\Http\Requests\Request;
use Kern\Widget\Events\RenderingWidgetSettings;
use Kern\Widget\Facades\WidgetGroup;

class WidgetRequest extends Request
{
    protected function prepareForValidation(): void
    {
        RenderingWidgetSettings::dispatch();
    }

    public function rules(): array
    {
        return [
            'sidebar_id' => ['required', 'string', Rule::in(array_keys(WidgetGroup::getGroups()))],
            'items' => [$this->isMethod('DELETE') ? 'sometimes' : 'present', 'array', 'max:100'],
            'position' => [$this->isMethod('DELETE') ? 'required' : 'sometimes', 'integer', 'min:0', 'max:126'],
            'widget_id' => [$this->isMethod('DELETE') ? 'required' : 'sometimes', 'string'],
        ];
    }
}

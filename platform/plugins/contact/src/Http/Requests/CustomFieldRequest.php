<?php

namespace Kern\Contact\Http\Requests;

use Illuminate\Validation\Rule;
use Kern\Base\Rules\OnOffRule;
use Kern\Contact\Enums\CustomFieldType;
use Kern\Contact\Models\CustomFieldOption;
use Kern\Support\Http\Requests\Request;

class CustomFieldRequest extends Request
{
    protected function prepareForValidation(): void
    {
        if (is_bool($this->input('required'))) {
            $this->merge(['required' => (int) $this->input('required')]);
        }

        $this->merge([
            'options' => $this->isMultipleField() ? $this->input('options', []) : [],
        ]);
    }

    public function rules(): array
    {
        $requiredIfRule = Rule::when($this->isMultipleField(), 'required', 'nullable');

        return [
            'type' => ['required', 'string', Rule::in(CustomFieldType::values())],
            'name' => ['required', 'string', 'max:255'],
            'placeholder' => ['nullable', 'string', 'max:255'],
            'required' => ['bail', 'integer', new OnOffRule],
            'order' => ['required', 'integer', 'min:0', 'max:999'],
            'options' => [$requiredIfRule, 'array'],
            'options.*.id' => ['sometimes', 'string', Rule::exists(CustomFieldOption::class, 'id')->where('custom_field_id', ($this->route('custom_field') ?? $this->route('custom-field'))?->getKey())],
            'options.*.label' => [$requiredIfRule, 'string'],
            'options.*.value' => [$requiredIfRule, 'string'],
            'options.*.order' => [$requiredIfRule, 'sometimes', 'numeric', 'min:0', 'max:999'],
        ];
    }

    protected function isMultipleField(): bool
    {
        return in_array($this->input('type'), [CustomFieldType::DROPDOWN, CustomFieldType::RADIO]);
    }
}

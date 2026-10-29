<?php

namespace Kern\Blog\Http\Requests;

use Illuminate\Validation\Rule;
use Kern\Base\Enums\BaseStatusEnum;
use Kern\Base\Rules\OnOffRule;
use Kern\Blog\Models\Category;
use Kern\Support\Http\Requests\Request;

class CategoryRequest extends Request
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'icon' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:400'],
            'status' => [Rule::in(BaseStatusEnum::values())],
            'is_default' => [new OnOffRule],
            'is_featured' => [new OnOffRule],
            'parent_id' => [
                'nullable',
                Rule::when($this->input('parent_id'), function () {
                    return Rule::exists('categories', 'id');
                }),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $category = $this->route('category');
                    $seen = [];
                    while ($value) {
                        if (($category && (string) $value === (string) $category->getKey()) || in_array((string) $value, $seen)) {
                            $fail('A category cannot be nested inside itself or its descendants.');
                            break;
                        }
                        $seen[] = (string) $value;
                        $value = Category::query()->whereKey($value)->value('parent_id');
                    }
                },
            ],
            'order' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ];
    }
}

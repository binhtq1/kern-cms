<?php

namespace Kern\CustomField\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Kern\Base\Enums\BaseStatusEnum;
use Kern\Base\Supports\AdminRegisteredTableData;
use Kern\CustomField\Facades\CustomField;
use Kern\CustomField\Forms\CustomFieldForm;
use Kern\CustomField\Models\FieldGroup;
use Kern\CustomField\Models\FieldItem;
use Kern\CustomField\Repositories\Interfaces\FieldGroupInterface;

class AdminFieldGroupData
{
    public const TYPES = ['text', 'number', 'email', 'password', 'textarea', 'select', 'radio', 'checkbox', 'date', 'time', 'color', 'image', 'file', 'wysiwyg', 'repeater'];

    public function index(): mixed
    {
        $filters = request()->validate(['search' => ['nullable', 'string', 'max:120'], 'status' => ['nullable', Rule::in(BaseStatusEnum::values())], 'sort' => ['nullable', Rule::in(['name', 'title', 'created_at', 'status', 'id'])], 'direction' => ['nullable', Rule::in(['asc', 'desc'])], 'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $query = FieldGroup::query()->when($filters['search'] ?? null, fn ($query, $search) => $query->whereLike('title', '%'.$search.'%'))->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status));
        $filters['advanced'] = app(AdminRegisteredTableData::class)->applyFilters('field-groups', $query, request());
        if ($sort = $filters['sort'] ?? null) {
            $query->orderBy($sort === 'name' ? 'title' : $sort, $filters['direction'] ?? 'asc')->orderBy('id');
        } else {
            $query->orderBy('order')->latest();
        }
        if (request()->has('_export')) {
            return app(AdminRegisteredTableData::class)->export('field-groups', $query, request());
        }
        $items = $query->paginate($filters['per_page'] ?? 20)->withQueryString()->through(fn ($item): array => ['id' => (string) $item->id, 'name' => $item->title, 'status' => $item->status->getValue(), 'status_label' => $item->status->label(), 'created_at' => $item->created_at?->toIso8601String(), 'edit_url' => route('custom-fields.edit', $item), 'delete_url' => route('custom-fields.destroy', $item), 'export_url' => route('custom-fields.export', $item)]);

        return Inertia::render('FieldGroups/Index', ['columns' => [['key' => 'id', 'label' => 'ID', 'sortable' => true], ['key' => 'title', 'label' => __('Name'), 'sortable' => true], ['key' => 'created_at', 'label' => __('Created At'), 'sortable' => true], ['key' => 'status', 'label' => __('Status'), 'sortable' => true]], 'tableButtons' => app(AdminRegisteredTableData::class)->buttons('field-groups'), 'tableVisibility' => app(AdminRegisteredTableData::class)->visibility('field-groups'), 'title' => trans('plugins/custom-field::base.page_title'), 'resource' => 'field-groups', 'items' => $items, 'filters' => $filters, 'statuses' => collect(BaseStatusEnum::labels())->map(fn ($label, $value): array => compact('label', 'value'))->values()->all(), 'permissions' => $this->permissions(), 'bulkFields' => app(AdminRegisteredTableData::class)->fields('field-groups'), 'urls' => ['bulk' => route('custom-fields.bulk'), 'index' => route('custom-fields.index'), 'create' => route('custom-fields.create'), 'export' => route('custom-fields.export'), 'import' => route('custom-fields.import')]]);
    }

    public function bulk(Request $request): mixed
    {
        return app(AdminRegisteredTableData::class)->bulk($request, 'field-groups', FieldGroup::class, $this->permissions());
    }

    public function fallbackReason(): ?string
    {
        if (FieldItem::query()->whereNotIn('type', self::TYPES)->exists()) {
            return 'Blade is required by unsupported field builder types.';
        }
        $model = request()->route('custom_field') ?? request()->route('custom-field');
        $form = CustomFieldForm::createFromModel($model instanceof FieldGroup ? $model : new FieldGroup)->prepareForSchema();
        if ($form->hasSchemaRenderingOutput()) {
            return 'Blade is required by field builder rendering output.';
        }
        $allowed = ['title' => 'TextField', 'status' => 'SelectField', 'order' => 'NumberField'];
        foreach ($form->getFields() as $name => $field) {
            if (($allowed[$name] ?? null) !== class_basename($field)) {
                return 'Blade is required by field builder form field: '.$name.'.';
            }
        }
        foreach (array_keys($form->getMetaBoxes()) as $key) {
            if (! in_array($key, ['rules', 'field-items-list'])) {
                return 'Blade is required by field builder meta box: '.$key.'.';
            }
        }

        return null;
    }

    public function importData(array $groups): array
    {
        Validator::make(compact('groups'), ['groups' => ['array', 'list', 'min:1', 'max:100'], 'groups.*' => ['array']])->validate();
        $normalized = [];
        foreach ($groups as $group) {
            $status = $group['status'] ?? 'published';
            if ($status instanceof BaseStatusEnum) {
                $status = $status->getValue();
            } elseif (is_array($status)) {
                Validator::make(compact('status'), ['status.value' => ['required', 'string', Rule::in(BaseStatusEnum::values())]])->validate();
                $status = $status['value'];
            }
            Validator::make([...$group, 'status' => $status], ['title' => ['required', 'string', 'max:250'], 'order' => ['required', 'integer', 'min:0', 'max:127'], 'status' => ['required', 'string', Rule::in(BaseStatusEnum::values())], 'rules' => ['required', 'json'], 'items' => ['array', 'list', 'max:100']])->validate();
            $convert = function (array $items, int $depth = 0) use (&$convert): array {
                if ($depth > 10 || count($items) > 100) {
                    throw ValidationException::withMessages(['json_data' => 'Too many nested fields.']);
                }

                return array_map(function ($item) use (&$convert, $depth): array {
                    if (! is_array($item)) {
                        throw ValidationException::withMessages(['json_data' => 'Invalid nested field.']);
                    }
                    $children = $item['children'] ?? $item['items'] ?? [];
                    Validator::make(compact('children'), ['children' => ['array', 'list', 'max:100']])->validate();
                    $options = $item['options'] ?? [];
                    if (is_string($options)) {
                        $options = json_decode($options, true);
                    }

                    return ['id' => null, 'title' => $item['title'] ?? '', 'slug' => $item['slug'] ?? '', 'type' => $item['type'] ?? '', 'instructions' => $item['instructions'] ?? '', 'options' => $options, 'items' => $convert($children, $depth + 1)];
                }, $items);
            };
            $items = $convert($group['items'] ?? []);
            $request = new Request(['rules' => $group['rules'], 'group_items' => json_encode($items)]);
            $this->validate($request);
            $export = function (array $items) use (&$export): array {
                return array_map(fn ($item, $index): array => [...Arr::except($item, ['id', 'items']), 'order' => $index + 1, 'options' => json_encode($item['options']), 'children' => $export($item['items'])], $items, array_keys($items));
            };
            $normalized[] = ['title' => $group['title'], 'order' => $group['order'], 'status' => $status, 'rules' => $request->input('rules'), 'items' => $export($items)];
        }

        return $normalized;
    }

    public function permissions(): array
    {
        return collect(['create', 'edit', 'delete'])->mapWithKeys(fn ($action): array => [$action => request()->user()->hasPermission('custom-fields.'.($action === 'delete' ? 'destroy' : $action))])->all();
    }

    public function form(?FieldGroup $group = null): mixed
    {
        $group ??= new FieldGroup;

        return Inertia::render('FieldGroups/Form', ['group' => ['id' => $group->exists ? (string) $group->getKey() : null, 'title' => $group->title ?: '', 'status' => $group->status?->getValue() ?: 'published', 'order' => $group->order ?: 0, 'rules' => json_decode($group->rules ?: '[]', true), 'items' => $group->exists ? app(FieldGroupInterface::class)->getFieldGroupItems($group->getKey()) : []], 'types' => self::TYPES, 'ruleSchema' => CustomField::getRuleSchema(), 'permissions' => $this->permissions(), 'urls' => ['index' => route('custom-fields.index'), 'submit' => $group->exists ? route('custom-fields.update', $group) : route('custom-fields.store'), 'export' => route('custom-fields.export', $group->exists ? $group->getKey() : [])]]);
    }

    public function validate(Request $request, ?FieldGroup $group = null): void
    {
        $items = json_decode($request->input('group_items', '[]'), true);
        $rules = json_decode($request->input('rules', '[]'), true);
        $deleted = json_decode($request->input('deleted_items', '[]') ?: '[]', true);
        Validator::make(compact('items', 'rules', 'deleted'), ['items' => ['array', 'max:100'], 'rules' => ['array', 'max:30'], 'deleted' => ['array', 'max:1000']])->validate();
        $owned = $group ? $group->fieldItems()->pluck('id')->map(fn ($id): string => (string) $id)->all() : [];
        $seen = [];
        $walk = function (array $items, int $depth = 0) use (&$walk, &$seen, $owned): void {
            if ($depth > 10 || count($items) > 100) {
                throw ValidationException::withMessages(['group_items' => 'Too many nested fields.']);
            }
            foreach ($items as $item) {
                Validator::make($item, ['id' => ['nullable', 'string'], 'title' => ['required', 'string', 'max:250'], 'slug' => ['nullable', 'string', 'max:250'], 'instructions' => ['nullable', 'string', 'max:10000'], 'type' => ['required', Rule::in(self::TYPES)], 'options' => ['array'], 'items' => ['array', 'max:100']])->validate();
                if (! empty($item['id']) && (! in_array((string) $item['id'], $owned) || in_array((string) $item['id'], $seen))) {
                    throw ValidationException::withMessages(['group_items' => 'A field does not belong to this group.']);
                }
                if (! empty($item['id'])) {
                    $seen[] = (string) $item['id'];
                }
                if ($item['type'] !== 'repeater' && ! empty($item['items'])) {
                    throw ValidationException::withMessages(['group_items' => 'Only repeaters can contain nested fields.']);
                }
                $walk($item['items'] ?? [], $depth + 1);
            }
        };
        $walk($items);
        foreach ($deleted as $id) {
            if (in_array((string) $id, $seen)) {
                throw ValidationException::withMessages(['deleted_items' => 'A field cannot be saved and deleted together.']);
            }
            if (! in_array((string) $id, $owned)) {
                throw ValidationException::withMessages(['deleted_items' => 'A field does not belong to this group.']);
            }
        }
        $schema = collect(CustomField::getRuleSchema())->flatMap(fn ($group) => $group['items'])->keyBy('slug');
        foreach ($rules as $and) {
            if (! is_array($and) || count($and) > 30) {
                throw ValidationException::withMessages(['rules' => 'Invalid display rules.']);
            }
            foreach ($and as $rule) {
                if (! is_array($rule)) {
                    throw ValidationException::withMessages(['rules' => 'Invalid display rule.']);
                }
                Validator::make($rule, ['name' => ['required', Rule::in($schema->keys()->all())], 'type' => ['required', Rule::in(['==', '!='])], 'value' => ['present', 'string', 'max:250']])->validate();
                if (! array_key_exists($rule['value'], $schema[$rule['name']]['data'])) {
                    throw ValidationException::withMessages(['rules' => 'Unknown display rule value.']);
                }
            }
        }
        $request->merge(['group_items' => json_encode($items), 'rules' => json_encode($rules), 'deleted_items' => json_encode($deleted)]);
    }
}

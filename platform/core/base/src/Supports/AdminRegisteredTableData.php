<?php

namespace Kern\Base\Supports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Kern\ACL\Tables\RoleTable;
use Kern\ACL\Tables\UserTable;
use Kern\Api\Tables\SanctumTokenTable;
use Kern\AuditLog\Tables\AuditLogTable;
use Kern\Base\Events\UpdatedContentEvent;
use Kern\Base\Exceptions\DisabledInDemoModeException;
use Kern\Base\Facades\BaseHelper;
use Kern\Base\Http\Actions\DeleteResourceAction;
use Kern\Block\Tables\BlockTable;
use Kern\Blog\Tables\CategoryTable;
use Kern\Blog\Tables\PostTable;
use Kern\Blog\Tables\TagTable;
use Kern\Contact\Tables\ContactTable;
use Kern\Contact\Tables\CustomFieldTable;
use Kern\Gallery\Tables\GalleryTable;
use Kern\Language\Facades\Language;
use Kern\LanguageAdvanced\Supports\LanguageAdvancedManager;
use Kern\Member\Tables\MemberTable;
use Kern\Menu\Tables\MenuTable;
use Kern\Page\Tables\PageTable;
use Kern\RequestLog\Tables\RequestLogTable;
use Kern\Table\Abstracts\TableAbstract;

class AdminRegisteredTableData
{
    protected const TABLES = [
        'pages' => PageTable::class,
        'users' => UserTable::class,
        'roles' => RoleTable::class,
        'posts' => PostTable::class,
        'categories' => CategoryTable::class,
        'tags' => TagTable::class,
        'blocks' => BlockTable::class,
        'galleries' => GalleryTable::class,
        'members' => MemberTable::class,
        'contacts' => ContactTable::class,
        'contact-fields' => CustomFieldTable::class,
        'field-groups' => \Kern\CustomField\Tables\CustomFieldTable::class,
        'menus' => MenuTable::class,
        'api-tokens' => SanctumTokenTable::class,
        'audit-logs' => AuditLogTable::class,
        'request-logs' => RequestLogTable::class,
    ];

    public function table(string $module): ?TableAbstract
    {
        return isset(self::TABLES[$module]) ? app(self::TABLES[$module]) : null;
    }

    public function buttons(string $module): array
    {
        return array_values(array_intersect($this->table($module)?->getDefaultButtons() ?? [], ['reload', 'visibility', 'export']));
    }

    public function visibility(string $module): array
    {
        $table = $this->table($module)::class;
        $saved = json_decode(request()->user()->getMeta('datatable_columns_visibility', '[]'), true) ?: [];

        return ['table' => $table, 'columns' => in_array('visibility', $this->buttons($module), true) ? array_map(fn ($value): int => (int) $value, $saved[$table] ?? []) : [], 'url' => route('table.update-columns-visibility')];
    }

    public function export(string $module, Builder $query, Request $request): mixed
    {
        $input = $request->validate(['_export' => ['required', Rule::in(['csv', 'xlsx'])]]);
        abort_unless(in_array('export', $this->buttons($module), true), 403);
        $table = $this->table($module);
        $table->queryUsing(function (Builder $builder) use ($query): void {
            $builder->setQuery(clone $query->getQuery())->with($query->getEagerLoads());
        });
        $request->replace(['action' => $input['_export'] === 'xlsx' ? 'excel' : 'csv']);
        $request->query->replace([]);

        return $input['_export'] === 'xlsx' ? $table->excel() : $table->csv();
    }

    protected function registered(string $module): array
    {
        $request = request();
        $key = 'admin.registered-table.'.hash('sha256', json_encode([$module, app()->getLocale(), $request->input('ref_lang'), $request->user()?->getAuthIdentifier()]));
        if ($request->isMethod('GET') && $request->attributes->has($key)) {
            return $request->attributes->get($key);
        }
        $fields = [];
        foreach ($this->table($module)?->getAllBulkChanges() ?? [] as $name => $field) {
            if ($module === 'users' && $name === 'status') {
                continue;
            }
            if (! in_array($name, ['username', 'name', 'title', 'first_name', 'last_name', 'email', 'phone', 'created_at', 'status', 'category', 'template'], true) || ! in_array($field['type'], ['text', 'date', 'datePicker', 'customSelect', 'select-search'], true)) {
                continue;
            }
            $field['choices'] = isset($field['callback']) ? call_user_func($field['callback']) : ($field['choices'] ?? []);
            $field['type'] = in_array($field['type'], ['date', 'datePicker'], true) ? 'date' : (in_array($field['type'], ['customSelect', 'select-search'], true) ? 'select' : ($name === 'email' ? 'email' : 'text'));
            $fields[$name] = $field;
        }
        if ($request->isMethod('GET')) {
            $request->attributes->set($key, $fields);
        }

        return $fields;
    }

    public function fields(string $module, ?Model $model = null, ?string $language = null): array
    {
        $translated = $language && $language !== Language::getDefaultLocaleCode();

        return collect($this->registered($module))->map(function (array $field, string $name) use ($translated, $model): array {
            return ['name' => $name, 'label' => strip_tags($field['title']), 'type' => $field['type'], 'choices' => collect($field['choices'])->map(fn ($label, $value): array => ['value' => (string) $value, 'label' => strip_tags((string) $label)])->values()->all(), 'operators' => $field['type'] === 'date' ? ['=', '>=', '<='] : ($field['type'] === 'select' ? ['='] : ['like', '=']), 'editable' => ! $translated || ($model && in_array($name, LanguageAdvancedManager::getTranslatableColumns($model), true))];
        })->values()->all();
    }

    protected function rules(array $field): array|string
    {
        return $field['type'] === 'select' ? ['required', Rule::in(array_keys($field['choices']))] : ($field['validate'] ?: ['required', 'string', 'max:255']);
    }

    public function validateValue(string $module, string $field, string $value, array $ids, string $modelClass): void
    {
        $schema = $this->registered($module)[$field] ?? null;
        if (($module === 'users' && ! in_array($field, ['username', 'email', 'created_at'], true)) || ($module === 'roles' && $field !== 'name')) {
            $schema = null;
        }
        if (! $schema) {
            throw ValidationException::withMessages(['field' => 'Unknown bulk change field.']);
        }
        Validator::make(['value' => $value], ['value' => $this->rules($schema)])->validate();
        if (($module === 'users' && in_array($field, ['username', 'email'], true)) || ($module === 'members' && $field === 'email')) {
            if (count($ids) !== 1) {
                throw ValidationException::withMessages(['value' => 'Change one account at a time.']);
            }
            $rules = [Rule::unique($modelClass, $field)->ignore($ids[0])];
            if ($field === 'username') {
                $rules = [...$rules, 'alpha_dash', 'min:4', 'max:30'];
            }
            Validator::make(['value' => $value], ['value' => $rules])->validate();
        }
    }

    public function applyFilters(string $module, Builder $query, Request $request, ?string $language = null): array
    {
        $input = $request->validate(['advanced' => ['nullable', 'array', 'list', 'max:10'], 'advanced.*' => ['array:field,operator,value'], 'advanced.*.field' => ['required', 'string'], 'advanced.*.operator' => ['required', 'string'], 'advanced.*.value' => ['required', 'string', 'max:255']]);
        $fields = collect($this->fields($module))->keyBy('name');
        foreach ($input['advanced'] ?? [] as $index => $filter) {
            $field = $fields->get($filter['field']);
            if (! $field || ! in_array($filter['operator'], $field['operators'], true)) {
                throw ValidationException::withMessages(['advanced.'.$index => 'Unknown filter field or operator.']);
            }
            $rules = $field['type'] === 'date' ? ['date'] : ($field['type'] === 'select' ? [Rule::in(array_column($field['choices'], 'value'))] : ['string', 'max:255']);
            Validator::make($request->all(), ['advanced.'.$index.'.value' => $rules])->validate();
            if ($language && $language !== Language::getDefaultLocaleCode() && in_array($filter['field'], LanguageAdvancedManager::getTranslatableColumns($query->getModel()), true)) {
                $column = DB::raw('COALESCE(admin_translation.'.$filter['field'].', '.$query->getModel()->getTable().'.'.$filter['field'].')');
                $query->where($column, $filter['operator'], $filter['operator'] === 'like' ? '%'.$filter['value'].'%' : $filter['value']);
            } else {
                $this->table($module)->applyFilterCondition($query, $filter['field'], $filter['operator'], $filter['value']);
            }
        }

        return $input['advanced'] ?? [];
    }

    public function indexQuery(Request $request, ?string $language = null): array
    {
        $query = Validator::make($request->query(), [
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'max:30'],
            'template' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', 'string', 'max:120'],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'ref_lang' => ['nullable', 'string', 'max:20'],
            'advanced' => ['nullable', 'array', 'list', 'max:10'],
            'advanced.*' => ['array:field,operator,value'],
            'advanced.*.field' => ['required', 'string', 'max:120'],
            'advanced.*.operator' => ['required', 'string', 'max:30'],
            'advanced.*.value' => ['required', 'string', 'max:255'],
        ])->validate();
        if ($language) {
            $query['ref_lang'] = $language;
        }

        return $query;
    }

    public function bulk(Request $request, string $module, string $modelClass, array $permissions, ?string $language = null): mixed
    {
        if (BaseHelper::hasDemoModeEnabled()) {
            throw new DisabledInDemoModeException;
        }
        $indexQuery = $this->indexQuery($request, $language);
        $input = $request->validate(['ids' => ['required', 'array', 'list', 'min:1', 'max:100'], 'ids.*' => ['required', 'integer', 'distinct', Rule::exists($modelClass, 'id')], 'action' => ['required', Rule::in(['delete', 'status', 'change'])], 'field' => ['required_if:action,change', 'string'], 'value' => ['required_if:action,change', 'nullable', 'string', 'max:10000'], 'status' => ['required_if:action,status', 'string']]);
        abort_unless($permissions[$input['action'] === 'delete' ? 'delete' : 'edit'], 403);
        $field = $input['action'] === 'status' ? 'status' : ($input['field'] ?? '');
        $value = $input['action'] === 'status' ? $input['status'] : ($input['value'] ?? '');
        if ($input['action'] !== 'delete') {
            try {
                $this->validateValue($module, $field, $value, $input['ids'], $modelClass);
            } catch (ValidationException $exception) {
                if ($input['action'] !== 'status') {
                    throw $exception;
                }
                throw ValidationException::withMessages(['status' => collect($exception->errors())->flatten()->all()]);
            }
            if ($language && $language !== Language::getDefaultLocaleCode() && ! in_array($field, LanguageAdvancedManager::getTranslatableColumns($modelClass), true)) {
                throw ValidationException::withMessages([$input['action'] === 'status' ? 'status' : 'field' => 'Only translated fields can be changed in this language.']);
            }
        }
        $request->replace([]);
        $request->query->replace([]);
        DB::transaction(function () use ($request, $input, $module, $modelClass, $field, $value, $language): void {
            $models = $modelClass::query()->whereKey($input['ids'])->lockForUpdate()->get();
            abort_unless($models->count() === count($input['ids']), 422);
            foreach ($models as $model) {
                if ($input['action'] === 'delete') {
                    $response = DeleteResourceAction::make($model)->execute();
                    $deleted = ! $model->exists || (method_exists($model, 'trashed') && $model->trashed());
                    if ($response->isError() || ! $deleted) {
                        throw ValidationException::withMessages(['ids' => $response->isError() ? $response->getMessage() : trans('core/base::notices.delete_failed_message')]);
                    }
                } elseif ($language && $language !== Language::getDefaultLocaleCode()) {
                    $columns = LanguageAdvancedManager::getTranslatableColumns($model);
                    $stored = (array) DB::table($model->getTable().'_translations')->where($model->getTable().'_id', $model->getKey())->where('lang_code', $language)->first();
                    $data = [];
                    foreach ($columns as $column) {
                        $data[$column] = $stored[$column] ?? $model->getRawOriginal($column);
                    }
                    $request->replace([...$data, $field => $value, 'language' => $language]);
                    LanguageAdvancedManager::save($model, $request);
                    UpdatedContentEvent::dispatch($modelClass, $request, $model);
                }
            }
            if ($input['action'] !== 'delete' && (! $language || $language === Language::getDefaultLocaleCode())) {
                if (! $this->table($module)->saveBulkChanges($input['ids'], $field, $value)) {
                    throw ValidationException::withMessages(['field' => 'Unknown bulk change field.']);
                }
            }
        });

        return redirect()->route(preg_replace('/\.bulk$/', '.index', $request->route()->getName()), $indexQuery)->with('success_msg', trans('core/base::notices.update_success_message'));
    }
}

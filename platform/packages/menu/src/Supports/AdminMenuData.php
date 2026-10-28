<?php

namespace Kern\Menu\Supports;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Kern\Base\Enums\BaseStatusEnum;
use Kern\Base\Supports\AdminRegisteredTableData;
use Kern\Base\Supports\RepositoryHelper;
use Kern\Language\Facades\Language;
use Kern\LanguageAdvanced\Supports\LanguageAdvancedManager;
use Kern\Menu\Facades\Menu;
use Kern\Menu\Forms\MenuForm;
use Kern\Menu\Forms\MenuNodeForm;
use Kern\Menu\Models\Menu as MenuModel;
use Kern\Menu\Models\MenuNode;

class AdminMenuData
{
    public function fallbackReason(): ?string
    {
        foreach ([[MenuForm::create(), ['name', 'status'], ['structure']], [MenuNodeForm::create(), ['menu_id', 'title', 'url', 'icon_font', 'icon_image', 'css_class', 'target'], []]] as [$form, $fields, $boxes]) {
            $form->prepareForSchema();
            if ($form->hasSchemaRenderingOutput() || array_diff(array_keys($form->getFields()), $fields) || array_diff(array_keys($form->getMetaBoxes()), $boxes)) {
                return 'Blade is required by additional menu fields.';
            }
        }

        return null;
    }

    public function index(): mixed
    {
        $localization = app(AdminMenuLanguage::class);
        $language = $localization->props();
        $locale = $localization->routeParameters();
        request()->validate(['search' => ['nullable', 'string', 'max:120']]);
        $search = mb_substr((string) request('search', ''), 0, 120);
        $status = in_array(request('status'), BaseStatusEnum::values(), true) ? request('status') : '';
        $sort = in_array(request('sort'), ['id', 'name', 'created_at'], true) ? request('sort') : 'id';
        $direction = request('direction') === 'asc' ? 'asc' : 'desc';
        $perPage = in_array((int) request('per_page'), [10, 20, 50, 100], true) ? (int) request('per_page') : 20;
        $query = $localization->indexQuery(MenuModel::query()->with('locations')->withCount('menuNodes'))->when($search, fn ($query) => $query->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($search).'%']))
            ->when($status, fn ($query) => $query->where('status', $status))->orderBy($sort, $direction);
        $advanced = app(AdminRegisteredTableData::class)->applyFilters('menus', $query, request());
        if (request()->has('_export')) {
            return app(AdminRegisteredTableData::class)->export('menus', $query, request());
        }
        $items = $query->paginate($perPage)->withQueryString()->through(fn (MenuModel $menu): array => [
            'id' => (string) $menu->getKey(), 'name' => $menu->name, 'locations_display' => $menu->locations->map(fn ($location) => Menu::getMenuLocations()[$location->location] ?? $location->location)->implode(', '), 'items_count' => $menu->menu_nodes_count, 'status' => $menu->status->getValue(),
            'status_label' => $menu->status->label(), 'created_at' => $menu->created_at?->toIso8601String(),
            'edit_url' => route('menus.edit', $menu), 'delete_url' => route('menus.destroy', $menu),
        ]);

        return Inertia::render('Menu/Index', [
            'columns' => [['key' => 'id', 'label' => 'ID', 'sortable' => true], ['key' => 'name', 'label' => __('Name'), 'sortable' => true], ['key' => 'locations_display', 'label' => __('Locations'), 'sortable' => false], ['key' => 'items_count', 'label' => __('Items'), 'sortable' => false], ['key' => 'created_at', 'label' => __('Created At'), 'sortable' => true], ['key' => 'status', 'label' => __('Status'), 'sortable' => false]],
            'bulkFields' => app(AdminRegisteredTableData::class)->fields('menus'),
            'tableButtons' => app(AdminRegisteredTableData::class)->buttons('menus'),
            'tableVisibility' => app(AdminRegisteredTableData::class)->visibility('menus'),
            'title' => trans('packages/menu::menu.name'), 'resource' => 'menus', 'items' => $items,
            'filters' => compact('search', 'status', 'sort', 'direction', 'advanced') + ['per_page' => $perPage] + $locale,
            'language' => $language, 'languages' => $language['choices'] ?? [],
            'statuses' => $this->options(BaseStatusEnum::labels()),
            'permissions' => collect(['create', 'edit', 'delete'])->mapWithKeys(fn ($action): array => [$action => request()->user()->hasPermission('menus.'.($action === 'delete' ? 'destroy' : $action))])->all(),
            'urls' => ['index' => route('menus.index', $locale), 'create' => route('menus.create', ($locale['ref_lang'] ?? null) === 'all' ? [] : $locale), 'bulk' => route('menus.bulk', $locale)],
        ]);
    }

    public function form(?MenuModel $menu = null): Response
    {
        $menu ??= new MenuModel;
        $localization = app(AdminMenuLanguage::class);
        $language = $localization->props($menu);
        $source = ! $menu->exists ? $localization->source(request()) : null;
        $display = $source ?: $menu;
        $cloning = (bool) $source;
        $locale = $localization->routeParameters();
        $nodes = $display->exists ? $display->menuNodes()->with(['reference', 'metadata'])->orderBy('position')->get() : collect();
        $nodeForm = MenuNodeForm::create()->prepareForSchema();
        $referencePage = $this->references();

        return Inertia::render('Menu/Form', [
            'title' => $menu->exists ? trans('core/base::forms.edit_item', ['name' => $menu->name]) : trans('packages/menu::menu.create'),
            'language' => $language,
            'menu' => [
                'id' => $menu->getKey(),
                'name' => $display->name ?? '', 'status' => $display->status?->getValue() ?? BaseStatusEnum::PUBLISHED,
                'locations' => $display->exists ? $display->locations()->pluck('location')->all() : [],
                'nodes' => $nodes->map(fn (MenuNode $node): array => [
                    'key' => ($cloning ? 'copy-' : '').$node->getKey(), ...($cloning ? [] : ['id' => (string) $node->getKey()]), 'parent' => $node->parent_id ? (($cloning ? 'copy-' : '').$node->parent_id) : '',
                    'title' => $node->title ?? '', 'url' => $node->url ?? '', 'reference_type' => $node->reference_type ?? '',
                    'reference_id' => (string) ($node->reference_id ?? ''), 'icon_font' => $node->icon_font ?? '', 'icon_image' => $node->getMetaData('icon_image', true) ?: '', 'css_class' => $node->css_class ?? '', 'target' => $node->target ?? '_self',
                ])->values()->all(),
            ],
            'statuses' => $this->options(BaseStatusEnum::labels()), 'locations' => $this->options(Menu::getMenuLocations()), 'references' => $referencePage['data'], 'referencePage' => $referencePage,
            'referenceTypes' => collect(Menu::getMenuOptionModels())->map(fn (string $model): array => ['value' => $model, 'label' => class_basename($model)])->values()->all(),
            'iconImageEnabled' => array_key_exists('icon_image', $nodeForm->getFields()),
            'media' => request()->user()->hasPermission('media.index') ? ['list' => route('media.picker'), 'upload' => route('media.picker-upload')] : null,
            'urls' => ['references' => route('menus.references'), 'index' => route('menus.index', $locale), 'submit' => route($menu->exists ? 'menus.update' : 'menus.store', [...($menu->exists ? ['menu' => $menu->getKey()] : []), ...$locale])],
        ]);
    }

    public function references(): array
    {
        $input = request()->validate([
            'type' => ['nullable', 'string', Rule::in(Menu::getMenuOptionModels())],
            'search' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $modelClass = $input['type'] ?? (Menu::getMenuOptionModels()[0] ?? null);
        if (! $modelClass) {
            return ['data' => [], 'current_page' => 1, 'last_page' => 1, 'total' => 0, 'type' => ''];
        }
        $model = new $modelClass;
        $table = $model->getTable();
        $query = $modelClass::query()->select($table.'.*')->where($table.'.status', BaseStatusEnum::PUBLISHED);
        $nameColumn = $table.'.name';
        $language = is_plugin_active('language') ? Language::getCurrentAdminLocaleCode() : null;
        if (is_plugin_active('language-advanced') && LanguageAdvancedManager::isSupported($model) && $language && $language !== Language::getDefaultLocaleCode()) {
            $query->leftJoin($table.'_translations as menu_translation', fn ($join) => $join->on('menu_translation.'.$table.'_id', '=', $table.'.id')->where('menu_translation.lang_code', $language));
            $nameColumn = DB::raw('COALESCE(menu_translation.name, '.$table.'.name)');
        }
        if (method_exists($model, 'slugable')) {
            $query->with('slugable');
        }
        $query = RepositoryHelper::applyBeforeExecuteQuery($query, $model);
        if ($search = $input['search'] ?? null) {
            $query->whereLike($nameColumn, '%'.$search.'%');
        }
        $page = $query->orderBy($nameColumn)->orderBy($table.'.id')->paginate(20);

        return [
            'data' => $page->getCollection()->map(fn (Model $reference): array => [
                'value' => $modelClass.':'.$reference->getKey(), 'label' => class_basename($modelClass).' · '.$reference->name,
                'type' => $modelClass, 'id' => (string) $reference->getKey(), 'title' => $reference->name, 'url' => (string) $reference->url,
            ])->all(),
            'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'type' => $modelClass,
        ];
    }

    protected function options(array $values): array
    {
        return collect($values)->map(fn ($label, $value): array => ['value' => (string) $value, 'label' => strip_tags((string) $label)])->values()->all();
    }

    public function validateTree(array $tree, ?MenuModel $menu): array
    {
        $owned = $menu?->exists ? $menu->menuNodes()->pluck('id')->map(fn ($id): string => (string) $id)->all() : [];
        $seen = [];
        $count = 0;
        $validate = function (array $nodes, int $depth = 1) use (&$validate, &$seen, &$count, $owned): array {
            if ($depth > 5 && $nodes) {
                throw ValidationException::withMessages(['menu_nodes' => __('Menu depth cannot exceed five levels.')]);
            }
            $result = [];
            foreach ($nodes as $position => $node) {
                if (++$count > 200 || ! is_array($node) || ! is_array($node['menuItem'] ?? null) || ! is_array($node['children'] ?? []) || ! array_is_list($node['children'] ?? [])) {
                    throw ValidationException::withMessages(['menu_nodes' => __('Invalid menu structure (maximum 200 items).')]);
                }
                $item = $this->validateItem($node['menuItem']);
                if (! empty($item['id'])) {
                    $id = (string) $item['id'];
                    if (! in_array($id, $owned, true) || isset($seen[$id])) {
                        throw ValidationException::withMessages(['menu_nodes' => __('A menu item is duplicated or belongs to another menu.')]);
                    }
                    $seen[$id] = true;
                }
                $item['position'] = $position;
                $result[] = ['menuItem' => $item, 'children' => $validate($node['children'] ?? [], $depth + 1)];
            }

            return $result;
        };

        return $validate($tree);
    }

    public function validateItem(array $item): array
    {
        $data = Validator::make($item, [
            'id' => ['nullable', 'integer', 'min:1'], 'title' => ['nullable', 'string', 'max:120'],
            'url' => ['nullable', 'string', 'max:2000', function (string $attribute, mixed $value, Closure $fail): void {
                $value = trim(html_entity_decode($value));
                if (preg_match('/[\x00-\x20]/u', $value) || (preg_match('/^[a-z][a-z0-9+.-]*:/i', $value) && ! preg_match('/^(https?:|mailto:|tel:)/i', $value)) || str_starts_with($value, '//')) {
                    $fail(__('Use a relative URL, HTTPS, HTTP, mailto or tel link.'));
                }
            }],
            'reference_type' => ['nullable', 'string', Rule::in(['custom-link', '', ...Menu::getMenuOptionModels()])],
            'reference_id' => ['nullable', 'integer', 'min:0'], 'icon_font' => ['nullable', 'string', 'max:50'],
            'icon_image' => ['nullable', 'string', 'max:2000'],
            'css_class' => ['nullable', 'string', 'max:120'], 'target' => ['nullable', Rule::in(['_self', '_blank'])],
        ])->validate();
        if (! empty($data['reference_type']) && $data['reference_type'] !== 'custom-link' && (! ($data['reference_type'])::query()->whereKey($data['reference_id'] ?? 0)->exists())) {
            throw ValidationException::withMessages(['menu_nodes' => __('Linked content no longer exists.')]);
        }
        $data['target'] ??= '_self';

        return $data;
    }
}

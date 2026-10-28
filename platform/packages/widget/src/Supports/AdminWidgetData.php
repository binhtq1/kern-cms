<?php

namespace Kern\Widget\Supports;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Kern\Language\Supports\AdminAppearanceLanguages;
use Kern\Widget\AbstractWidget;
use Kern\Widget\Facades\Widget as WidgetRegistry;
use Kern\Widget\Facades\WidgetGroup;
use Kern\Widget\Forms\WidgetForm;
use Kern\Widget\Models\Widget;
use Kern\Widget\Widgets\CoreSimpleMenu;
use Kern\Widget\Widgets\Text;

class AdminWidgetData
{
    public function registry(): array
    {
        return collect(WidgetRegistry::getWidgets())->filter(fn (AbstractWidget $widget): bool => $widget->isAvailable())->keyBy(fn (AbstractWidget $widget): string => $widget->getId())->all();
    }

    public function fallbackReason(): ?string
    {
        foreach ($this->registry() as $widget) {
            if ($this->schema($widget) === null) {
                return 'Blade is required by widget configuration: '.$widget->getId().'.';
            }
        }
        $unknown = Widget::query()->where('theme', Widget::getThemeName())->whereNotIn('widget_id', array_keys($this->registry()))->exists();

        return $unknown ? 'Blade is required by an unavailable saved widget.' : null;
    }

    public function schema(AbstractWidget $widget): ?array
    {
        if ($widget instanceof Text) {
            return [['name' => 'name', 'label' => __('Name'), 'type' => 'text', 'choices' => [], 'multiple' => false], ['name' => 'content', 'label' => __('Content'), 'type' => 'textarea', 'choices' => [], 'multiple' => false]];
        }
        if ($widget instanceof CoreSimpleMenu) {
            $fields = $widget->adminConfig()['fields'] ?? [];
            if (array_diff(array_column(array_column($fields, 'attributes'), 'name'), ['label', 'url', 'attributes', 'is_open_new_tab'])) {
                return null;
            }

            return [['name' => 'name', 'label' => __('Name'), 'type' => 'text', 'choices' => [], 'multiple' => false], ['name' => 'items', 'label' => __('Links'), 'type' => 'links', 'choices' => [], 'multiple' => false]];
        }
        $form = $widget->getAdminForm();
        if ($form instanceof WidgetForm) {
            $form->prepareForSchema();
        }
        if (! $form instanceof WidgetForm || $form->getMetaBoxes() || $form->hasSchemaRenderingOutput()) {
            return null;
        }
        $schema = [];
        foreach ($form->getFields() as $field) {
            $type = match (class_basename($field)) {
                'TextField' => 'text', 'NumberField' => 'number', 'TextareaField' => 'textarea',
                'SelectField' => 'select', 'HtmlField' => 'info', default => null,
            };
            if ($type === null) {
                return null;
            }
            $options = $field->getOptions();
            $choices = $options['choices'] ?? [];
            $schema[] = [
                'name' => preg_replace('/\[\]$/', '', $field->getName()), 'label' => strip_tags((string) (($options['label'] ?? null) ?: $field->getName())),
                'type' => $type, 'multiple' => (bool) ($options['attr']['multiple'] ?? $options['multiple'] ?? false),
                'choices' => collect(is_callable($choices) ? $choices() : $choices)->map(fn ($label, $value): array => ['value' => (string) $value, 'label' => strip_tags((string) $label)])->values()->all(),
                'text' => $type === 'info' ? strip_tags((string) ($options['html'] ?? $options['content'] ?? '')) : null,
            ];
        }

        return $schema;
    }

    public function index(): Response
    {
        $languages = is_plugin_active('language') ? app(AdminAppearanceLanguages::class)->props('widgets.index') : ['language' => null, 'languages' => []];
        $registry = $this->registry();
        $widgets = Widget::query()->where('theme', Widget::getThemeName())->orderBy('position')->get();

        return Inertia::render('Widgets/Index', [
            ...$languages,
            'title' => trans('packages/widget::widget.name'),
            'registry' => collect($registry)->map(fn (AbstractWidget $widget): array => [
                'id' => $widget->getId(), 'name' => $widget->getConfig('name'), 'description' => $widget->getConfig('description'),
                'defaults' => $widget->getConfig(), 'fields' => $this->schema($widget),
            ])->values()->all(),
            'groups' => collect(WidgetGroup::getGroups())->map(fn ($group): array => [
                'id' => $group->getId(), 'name' => $group->getName(), 'description' => $group->getDescription(),
                'items' => $widgets->where('sidebar_id', $group->getId())->map(fn (Widget $widget): array => ['key' => (string) $widget->getKey(), 'id' => $widget->widget_id, 'data' => $widget->data])->values()->all(),
            ])->values()->all(),
            'urls' => ['save' => route('widgets.save_all', request()->only('ref_lang'))],
        ]);
    }

    public function validateItems(string $sidebar, array $items): array
    {
        Validator::make(['sidebar_id' => $sidebar, 'items' => $items], [
            'sidebar_id' => ['required', Rule::in(array_keys(WidgetGroup::getGroups()))], 'items' => ['array', 'max:100'],
        ])->validate();
        $registry = $this->registry();
        $result = [];
        foreach ($items as $index => $item) {
            if (is_string($item)) {
                parse_str($item, $data);
            } elseif (is_array($item)) {
                $data = $item;
            } else {
                throw ValidationException::withMessages(['items.'.$index => __('Invalid widget data.')]);
            }
            $id = $data['id'] ?? null;
            if (! is_string($id) || ! isset($registry[$id])) {
                throw ValidationException::withMessages(['items.'.$index => __('The widget is not registered or is unavailable.')]);
            }
            $schema = $this->schema($registry[$id]);
            if ($schema !== null) {
                $rules = [];
                foreach ($schema as $field) {
                    $name = $field['name'];
                    $rules[$name] = match ($field['type']) {
                        'number' => ['nullable', 'integer', 'min:0', 'max:100'],
                        'select' => $field['multiple'] ? ['nullable', 'array'] : ['nullable', Rule::in(array_column($field['choices'], 'value'))],
                        'links' => ['nullable', 'array', 'max:100'],
                        'info' => ['exclude'],
                        default => ['nullable', 'string', 'max:'.($field['type'] === 'textarea' ? 100000 : 1000)],
                    };
                    if ($field['type'] === 'select' && $field['multiple']) {
                        $rules[$name.'.*'] = [Rule::in(array_column($field['choices'], 'value'))];
                    }
                    if ($field['type'] === 'links') {
                        if (($data[$name] ?? null) === '[]') {
                            $data[$name] = [];
                        }
                        $rules[$name.'.*'] = ['array'];
                        $rules[$name.'.*.*.key'] = ['required', Rule::in(['label', 'url', 'attributes', 'is_open_new_tab'])];
                        $rules[$name.'.*.*.value'] = ['nullable', 'string', 'max:2000'];
                    }
                }
                $validated = Validator::make($data, $rules)->validate();
                foreach ($schema as $field) {
                    if ($field['type'] !== 'links') {
                        continue;
                    }
                    foreach ($validated[$field['name']] ?? [] as $row) {
                        $link = collect($row)->pluck('value', 'key')->all();
                        $url = trim(html_entity_decode((string) ($link['url'] ?? '')));
                        if (preg_match('/[\x00-\x20]/u', $url) || (preg_match('/^[a-z][a-z0-9+.-]*:/i', $url) && ! preg_match('/^(https?:|mailto:|tel:)/i', $url)) || str_starts_with($url, '//')) {
                            throw ValidationException::withMessages(['items.'.$index => __('Use a relative URL, HTTPS, HTTP, mailto or tel link.')]);
                        }
                        $attributes = (string) ($link['attributes'] ?? '');
                        if (! preg_match('/^(?:\s*(?:rel|class|title|aria-[a-z-]+|data-[a-z-]+)\s*=\s*(?:"[^"<>`]*"|\'[^\'<>`]*\'))*\s*$/u', $attributes)) {
                            throw ValidationException::withMessages(['items.'.$index => __('Only quoted rel, class, title, aria and data attributes are supported.')]);
                        }
                    }
                }
                $data = ['id' => $id, ...$validated];
            }
            $result[] = $data;
        }

        return $result;
    }

    public function save(string $sidebar, array $items): void
    {
        $theme = Widget::getThemeName();
        DB::transaction(function () use ($theme, $sidebar, $items): void {
            Widget::query()->where(['theme' => $theme, 'sidebar_id' => $sidebar])->lockForUpdate()->get();
            Widget::query()->where(['theme' => $theme, 'sidebar_id' => $sidebar])->delete();
            foreach ($items as $position => $data) {
                Widget::query()->create(['sidebar_id' => $sidebar, 'widget_id' => $data['id'], 'theme' => $theme, 'position' => $position, 'data' => $data]);
            }
        });
        WidgetGroup::load(true);
    }
}

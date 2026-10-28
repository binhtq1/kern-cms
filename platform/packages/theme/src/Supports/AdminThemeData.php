<?php

namespace Kern\Theme\Supports;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Kern\Base\Forms\FormAbstract;
use Kern\Language\Supports\AdminAppearanceLanguages;
use Kern\Theme\Events\RenderingThemeOptionSettings;
use Kern\Theme\Facades\ThemeOption;
use Kern\Theme\Forms\CustomCSSForm;
use Kern\Theme\Forms\CustomHTMLForm;
use Kern\Theme\Forms\CustomJSForm;
use Kern\Theme\Forms\RobotsTxtEditorForm;

class AdminThemeData
{
    public function bootOptions(bool $forSchema = true): void
    {
        if (is_plugin_active('language')) {
            app(AdminAppearanceLanguages::class)->context();
        }
        if (! request()->attributes->get('admin_theme_options_booted')) {
            request()->attributes->set('admin_theme_options_booted', true);
            ob_start();
            try {
                RenderingThemeOptionSettings::dispatch();
                do_action(RENDERING_THEME_OPTIONS_PAGE);
            } finally {
                request()->attributes->set('admin_theme_options_output', (string) ob_get_clean());
            }
        }
        if (! $forSchema && request()->attributes->get('admin_theme_options_output')) {
            echo request()->attributes->get('admin_theme_options_output');
            request()->attributes->set('admin_theme_options_output', '');
        }
    }

    public function fallbackReason(string $module): ?string
    {
        if ($module === 'theme-options') {
            $this->bootOptions();
            if (trim(request()->attributes->get('admin_theme_options_output', '')) !== '') {
                return 'Blade is required by theme option rendering extensions.';
            }
            foreach (ThemeOption::constructSections() as $section) {
                foreach ($section['fields'] as $field) {
                    if ($this->optionField($field) === null) {
                        return 'Blade is required by theme option field: '.$field['id'].'.';
                    }
                }
            }
        }
        if ($module === 'theme-assets') {
            $forms = [
                'theme.custom-css' => CustomCSSForm::class,
                'theme.custom-js' => CustomJSForm::class,
                'theme.custom-html' => CustomHTMLForm::class,
                'theme.robots-txt' => RobotsTxtEditorForm::class,
            ];
            $name = preg_replace('/\.post$/', '', (string) request()->route()?->getName());
            if (isset($forms[$name]) && $this->editorFields($forms[$name]::create()) === null) {
                return 'Blade is required by theme editor extensions.';
            }
        }

        return null;
    }

    public function optionField(array $field, bool $useStoredValue = true): ?array
    {
        $attributes = $field['attributes'] ?? [];
        $type = match ($field['type']) {
            'text', 'email', 'url', 'number', 'textarea', 'hidden' => $field['type'],
            'customColor' => 'color', 'mediaImage' => 'image', 'mediaFile' => 'file',
            'select', 'customSelect', 'customRadio', 'uiSelector' => 'select',
            'googleFonts' => 'font', 'coreIcon' => 'icon', 'repeater' => 'repeater',
            'onOff', 'onOffCheckbox' => 'checkbox', 'multiCheckList' => 'multiselect',
            default => null,
        };
        if ($type === null) {
            return null;
        }
        $name = $attributes['name'] ?? $field['id'];
        $value = $useStoredValue ? ThemeOption::getOption($name, $attributes['value'] ?? '') : ($attributes['value'] ?? '');
        $children = [];
        if ($type === 'repeater') {
            foreach ($attributes['fields'] ?? [] as $child) {
                $schema = $this->optionField($child, false);
                if ($schema === null || in_array($schema['type'], ['repeater', 'multiselect'], true)) {
                    return null;
                }
                $children[] = $schema;
            }
            $value = is_array($value) ? $value : (json_decode($value ?: '[]', true) ?: []);
            $value = $this->normalizeRepeaterRows($value, $children);
            if ($value === null) {
                return null;
            }
        }
        if ($type === 'multiselect') {
            $value = is_array($value) ? $value : (json_decode($value ?: '[]', true) ?: []);
        }
        $choices = $attributes['list'] ?? $attributes['values'] ?? $attributes['choices'] ?? [];
        if ($type === 'font') {
            $choices = array_combine($fonts = json_decode(file_get_contents(core_path('base/resources/data/google-fonts.json')), true), $fonts);
        }
        if ($type === 'select' && ($value === '' || $value === null) && $choices && ! array_key_exists('', $choices)) {
            $value = (string) array_key_first($choices);
        }

        return [
            'name' => $name, 'label' => strip_tags((string) ($field['label'] ?? $name)), 'type' => $type,
            'value' => $value ?? '', 'description' => strip_tags((string) ($field['helper'] ?? '')),
            'choices' => collect($choices)->map(fn ($label, $key): array => ['value' => (string) $key, 'label' => strip_tags(is_array($label) ? ($label['label'] ?? (string) $key) : (string) $label)])->values()->all(),
            'children' => $children,
            'iconsUrl' => $type === 'icon' ? route('core-icons') : null,
            'placeholder' => (string) ($attributes['placeholder'] ?? ''),
            'disabled' => (bool) ($attributes['disabled'] ?? false),
            'readonly' => (bool) ($attributes['readonly'] ?? false),
        ];
    }

    public function options(?string $id): array
    {
        $languages = is_plugin_active('language') ? app(AdminAppearanceLanguages::class)->props('theme.options', $id ? ['id' => $id] : []) : ['language' => null, 'languages' => []];
        $this->bootOptions();
        $locale = request()->only('ref_lang');
        $sections = ThemeOption::constructSections();
        $current = $id ?: (Arr::first($sections)['id'] ?? null);
        abort_unless($current && collect($sections)->contains('id', $current), 404);

        return [
            ...$languages,
            'sections' => collect($sections)->map(fn (array $section): array => ['id' => $section['id'], 'title' => strip_tags($section['title']), 'url' => route('theme.options', ['id' => $section['id'], ...$locale]), 'fields' => array_values(array_map(fn (array $field): array => $this->optionField($field), $section['fields']))])->values()->all(),
            'current' => $current, 'submit' => route('theme.options.post', ['id' => $current, ...$locale]),
            'media' => ['list' => route('media.picker'), 'upload' => route('media.picker-upload')],
        ];
    }

    public function validateOptions(Request $request): array
    {
        $this->bootOptions();
        $registered = [];
        foreach (ThemeOption::constructSections() as $section) {
            foreach ($section['fields'] as $field) {
                $registered[$field['attributes']['name'] ?? $field['id']] = $field;
            }
        }
        $input = $request->except(['_token', 'ref_lang', 'ref_from', 'submitter']);
        if (array_diff(array_keys($input), array_keys($registered))) {
            throw ValidationException::withMessages(['options' => 'Unknown theme option.']);
        }
        $rules = [];
        foreach ($input as $name => $value) {
            $field = $registered[$name];
            $schema = $this->optionField($field);
            if (($schema['disabled'] ?? false) || ($schema['readonly'] ?? false)) {
                unset($input[$name]);

                continue;
            }
            if (in_array($schema['type'] ?? null, ['repeater', 'multiselect'], true) && is_string($value)) {
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    $input[$name] = $value = $decoded;
                }
            }
            $rules[$name] = $this->optionRules($schema, $value);
            if (($schema['type'] ?? null) === 'multiselect') {
                $rules[$name.'.*'] = [Rule::in(array_column($schema['choices'], 'value'))];
            }
            if (($schema['type'] ?? null) === 'repeater' && is_array($value)) {
                $normalized = $this->normalizeRepeaterRows($value, $schema['children']);
                if ($normalized === null) {
                    throw ValidationException::withMessages([$name => 'Invalid repeater fields.']);
                }
                $input[$name] = $value = $normalized;
                foreach ($value as $index => $row) {
                    foreach ($schema['children'] as $childIndex => $child) {
                        $entry = $row[$childIndex];
                        if (! is_array($entry) || ($entry['key'] ?? null) !== $child['name'] || array_diff(array_keys($entry), ['key', 'value'])) {
                            throw ValidationException::withMessages([$name => 'Invalid repeater field.']);
                        }
                        if (($child['disabled'] ?? false) || ($child['readonly'] ?? false)) {
                            $input[$name][$index][$childIndex]['value'] = $schema['value'][$index][$childIndex]['value'] ?? $child['value'];
                        }
                        $path = $name.'.'.$index.'.'.$childIndex.'.value';
                        $rules[$name.'.'.$index.'.'.$childIndex.'.key'] = ['required', Rule::in([$child['name']])];
                        $rules[$path] = $child['type'] === 'checkbox' ? ['nullable', 'boolean'] : $this->optionRules($child, $entry['value'] ?? null);
                    }
                }
            }
        }

        return Validator::make($input, $rules)->validate();
    }

    protected function normalizeRepeaterRows(array $rows, array $children): ?array
    {
        if (! array_is_list($rows)) {
            return null;
        }
        $names = array_column($children, 'name');
        $normalized = [];
        foreach ($rows as $row) {
            if (! is_array($row) || ! array_is_list($row)) {
                return null;
            }
            $entries = [];
            foreach ($row as $entry) {
                $key = is_array($entry) ? ($entry['key'] ?? null) : null;
                if (! is_string($key) || ! in_array($key, $names, true) || array_key_exists($key, $entries) || array_diff(array_keys($entry), ['key', 'value'])) {
                    return null;
                }
                $entries[$key] = $entry;
            }
            $normalized[] = array_map(fn (array $child): array => ['key' => $child['name'], 'value' => array_key_exists('value', $entries[$child['name']] ?? []) ? $entries[$child['name']]['value'] : $child['value']], $children);
        }

        return $normalized;
    }

    protected function optionRules(?array $schema, mixed $value): array
    {
        return match ($schema['type'] ?? null) {
            'number' => ['nullable', 'numeric'], 'email' => ['nullable', 'email', 'max:255'],
            'url' => ['nullable', 'url', 'max:2048'], 'color' => $value === 'transparent' ? ['nullable', Rule::in(['transparent'])] : ['nullable', 'hex_color'],
            'select' => ['nullable', Rule::in(array_column($schema['choices'], 'value'))],
            'repeater', 'multiselect' => ['nullable', 'array', 'max:100'],
            default => $schema === null && is_array($value) ? ['array', 'max:100'] : ['nullable', 'string', 'max:100000'],
        };
    }

    public function editorFields(FormAbstract $form): ?array
    {
        $form->prepareForSchema();
        if ($form->hasSchemaRenderingOutput()) {
            return null;
        }
        $fields = [];
        foreach ($form->getFields() as $name => $field) {
            $options = $field->getOptions();
            $type = class_basename($field);
            if ($type === 'AlertField') {
                $fields[] = ['name' => $name, 'label' => '', 'value' => '', 'type' => 'notice', 'description' => strip_tags($options['content'] ?? '')];

                continue;
            }
            if ($type !== 'CodeEditorField' && $field->getType() !== 'file') {
                return null;
            }
            $fields[] = ['name' => $name, 'label' => strip_tags($options['label'] ?? $name), 'value' => $options['value'] ?? '', 'type' => $field->getType() === 'file' ? 'file' : 'code', 'mode' => $options['attr']['mode'] ?? 'htmlmixed', 'description' => strip_tags($options['help_block']['text'] ?? '')];
        }
        if ($form->getMetaBoxes()) {
            return null;
        }

        return $fields;
    }
}

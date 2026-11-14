<?php

namespace Kern\Translation\Supports;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Kern\Base\Supports\Language;
use Kern\Translation\Manager;
use Kern\Translation\Services\GetGroupedTranslationsService;

class AdminTranslationData
{
    public const MAX_ROWS = 50000;

    public const MAX_BYTES = 20971520;

    public function fallbackReason(): ?string
    {
        if (! request()->routeIs('translations.theme-translations')) {
            return null;
        }
        $groups = $this->locales();
        $locale = request()->input('ref_lang', app()->getLocale());
        $group = collect($groups)->firstWhere('locale', $locale) ?: Language::getDefaultLanguage();
        if (filled(apply_filters('translation_theme_translation_header', null, $groups, $group))) {
            return 'Blade is required by theme translation rendering extensions.';
        }

        return null;
    }

    public function locales(): array
    {
        return Language::getAvailableLocales() ?: ['en' => Language::getDefaultLanguage()];
    }

    public function records(string $locale, bool $theme): array
    {
        if ($theme) {
            return collect(app(Manager::class)->getThemeTranslations($locale))->map(fn ($value, $key): array => ['group' => 'theme', 'key' => (string) $key, 'original' => (string) $key, 'value' => is_scalar($value) ? (string) $value : ''])->values()->all();
        }

        return app(GetGroupedTranslationsService::class)->handle()->map(function ($item) use ($locale): array {
            $lookup = Str::of($item['group'])->replaceLast(DIRECTORY_SEPARATOR, '::')->append('.'.$item['key'])->toString();
            $value = trans($lookup, [], $locale);

            return ['group' => $item['group'], 'key' => $item['key'], 'original' => (string) $item['value'], 'value' => is_scalar($value) ? (string) $value : ''];
        })->all();
    }

    public function index(bool $theme = false): mixed
    {
        $filters = request()->validate(['ref_lang' => ['nullable', Rule::in(array_keys($this->locales()))], 'search' => ['nullable', 'string', 'max:120'], 'group' => ['nullable', 'string', 'max:250'], 'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $locale = $filters['ref_lang'] ?? (isset($this->locales()[app()->getLocale()]) ? app()->getLocale() : array_key_first($this->locales()));
        $rows = collect($this->records($locale, $theme))->when($filters['group'] ?? null, fn ($rows, $group) => $rows->where('group', $group))->when($filters['search'] ?? null, fn ($rows, $search) => $rows->filter(fn ($row): bool => Str::contains(mb_strtolower($row['original'].' '.$row['value'].' '.$row['key']), mb_strtolower($search))))->values();
        $perPage = $filters['per_page'] ?? 50;
        $page = $filters['page'] ?? 1;
        $items = (new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page, ['path' => request()->url()]))->withQueryString();
        $permission = $theme ? 'theme-translations' : 'other-translations';

        return Inertia::render('Translations/Index', ['theme' => $theme, 'locale' => $locale, 'locales' => array_values($this->locales()), 'groups' => array_values(app(GetGroupedTranslationsService::class)->getGroups()), 'instruction' => trans('plugins/translation::translation.theme_translations_instruction'), 'reimportDescription' => trans('plugins/translation::translation.import_translations_description'), 'items' => $items, 'filters' => $filters, 'permissions' => ['export' => request()->user()->hasPermission($permission.'.export'), 'import' => request()->user()->hasPermission($permission.'.import')], 'urls' => ['dataExport' => route('tools.data-synchronize.export.'.($theme ? 'theme-translations' : 'other-translations').'.index'), 'dataImport' => route('tools.data-synchronize.import.'.($theme ? 'theme-translations' : 'other-translations').'.index'), 'reimport' => $theme ? route('translations.theme-translations.re-import') : null, 'index' => route($theme ? 'translations.theme-translations' : 'translations.index'), 'update' => route($theme ? 'translations.theme-translations.post' : 'translations.group.edit'), 'admin' => route('translations.index'), 'theme' => route('translations.theme-translations'), 'locales' => route('translations.locales'), 'export' => route('translations.react.export', ['theme' => (int) $theme, 'ref_lang' => $locale]), 'import' => route('translations.react.import', ['theme' => (int) $theme, 'ref_lang' => $locale])]]);
    }

    public function validateUpdate(Request $request, bool $theme = false): void
    {
        $locale = $theme ? $request->input('pk') : explode('|', $request->input('name', ''), 2)[0];
        $key = $theme ? $request->input('name') : (explode('|', $request->input('name', ''), 2)[1] ?? '');
        $group = $theme ? 'theme' : $request->input('group');
        Validator::make(['locale' => $locale, 'key' => $key, 'group' => $group, 'value' => $request->input('value')], ['locale' => ['required', Rule::in(array_keys($this->locales()))], 'key' => ['required', 'string', 'max:10000'], 'group' => ['required', Rule::in($theme ? ['theme'] : array_values(app(GetGroupedTranslationsService::class)->getGroups()))], 'value' => ['nullable', 'string', 'max:10000']])->validate();
        if (! collect($this->records($locale, $theme))->contains(fn ($row): bool => $row['group'] === $group && $row['key'] === $key)) {
            throw ValidationException::withMessages(['name' => 'Unknown translation key.']);
        }
    }

    public function export(Request $request): mixed
    {
        $theme = $request->boolean('theme');
        abort_unless($request->user()->hasPermission(($theme ? 'theme-translations' : 'other-translations').'.export'), 403);
        $locale = $request->validate(['ref_lang' => ['required', Rule::in(array_keys($this->locales()))]])['ref_lang'];

        $rows = $this->records($locale, $theme);
        $this->validateSize($rows);

        return response()->json($rows, headers: ['Content-Disposition' => 'attachment; filename="translations-'.$locale.'.json"']);
    }

    protected function validateSize(array $rows): void
    {
        if (count($rows) > self::MAX_ROWS || strlen(json_encode($rows, JSON_THROW_ON_ERROR)) > self::MAX_BYTES) {
            throw ValidationException::withMessages(['rows' => 'Translation files may contain up to 50,000 rows and 20 MB.']);
        }
    }

    public function import(Request $request): mixed
    {
        $theme = $request->boolean('theme');
        abort_unless($request->user()->hasPermission(($theme ? 'theme-translations' : 'other-translations').'.import'), 403);
        $input = $request->validate(['ref_lang' => ['required', Rule::in(array_keys($this->locales()))], 'rows' => ['required', 'array', 'max:'.self::MAX_ROWS], 'rows.*' => ['array:group,key,original,value'], 'rows.*.group' => ['required', 'string'], 'rows.*.key' => ['required', 'string', 'max:10000'], 'rows.*.value' => ['nullable', 'string', 'max:10000']]);
        $this->validateSize($input['rows']);
        $allowed = collect($this->records($input['ref_lang'], $theme))->keyBy(fn ($row): string => $row['group'].'|'.$row['key']);
        foreach ($input['rows'] as $row) {
            if (! $allowed->has($row['group'].'|'.$row['key'])) {
                throw ValidationException::withMessages(['rows' => 'Unknown translation key.']);
            }
        }
        $manager = app(Manager::class);
        if ($theme) {
            $translations = $manager->getThemeTranslations($input['ref_lang']);
            foreach ($input['rows'] as $row) {
                $translations[$row['key']] = $row['value'] ?? '';
            }
            $manager->saveThemeTranslations($input['ref_lang'], $translations);
        } else {
            foreach (collect($input['rows'])->groupBy('group') as $group => $rows) {
                $manager->updateTranslation($input['ref_lang'], $group, $rows->pluck('value', 'key')->all());
            }
        }

        return redirect()->route($theme ? 'translations.theme-translations' : 'translations.index', ['ref_lang' => $input['ref_lang']])->with('success_msg', trans('core/base::notices.update_success_message'));
    }
}

<?php

namespace Kern\Menu\Supports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Kern\Language\Facades\Language;
use Kern\Language\Models\LanguageMeta;
use Kern\Menu\Http\Requests\MenuRequest;
use Kern\Menu\Models\Menu as MenuModel;

class AdminMenuLanguage
{
    protected function languages(): array
    {
        return is_plugin_active('language') ? Language::getActiveLanguage()->keyBy('lang_code')->all() : [];
    }

    protected function defaultCode(): ?string
    {
        $languages = collect($this->languages());

        return ($languages->first(fn ($language): bool => (bool) $language->lang_is_default) ?: $languages->first())?->lang_code;
    }

    protected function queryCode(): ?string
    {
        return request()->validate(['ref_lang' => ['nullable', 'string', 'max:20']])['ref_lang'] ?? null;
    }

    protected function assertCode(?string $code, bool $allowAll = false): void
    {
        if ($allowAll && $code === 'all') {
            return;
        }
        if ($code !== null && ! isset($this->languages()[$code])) {
            throw ValidationException::withMessages(['language' => __('The selected language is unavailable.')]);
        }
    }

    protected function meta(MenuModel $menu): ?LanguageMeta
    {
        return LanguageMeta::query()->where('reference_type', MenuModel::class)->where('reference_id', $menu->getKey())->first();
    }

    public function source(Request $request): ?MenuModel
    {
        if (! $this->languages() || ! $request->filled('ref_from')) {
            return null;
        }
        $input = $request->validate(['ref_from' => ['required', 'integer', 'min:1', 'exists:menus,id']]);

        return MenuModel::query()->findOrFail($input['ref_from']);
    }

    public function props(?MenuModel $menu = null): ?array
    {
        $languages = $this->languages();
        if (! $languages) {
            return null;
        }
        $listing = str_ends_with((string) request()->route()?->getName(), '.index');
        $creating = str_ends_with((string) request()->route()?->getName(), '.create') || str_ends_with((string) request()->route()?->getName(), '.store');
        $source = $this->source(request());
        $modelMeta = $menu?->exists ? $this->meta($menu) : null;
        $code = $creating ? ($this->queryCode() ?: $this->defaultCode()) : ($modelMeta?->lang_meta_code ?: $this->queryCode() ?: $this->defaultCode());
        $this->assertCode($code, str_ends_with((string) request()->route()?->getName(), '.index'));
        Language::setCurrentAdminLocale($code);
        request()->attributes->set('admin.menu.language', $code);
        $reference = $source ?: ($menu?->exists ? $menu : null);
        $origin = $reference ? $this->meta($reference)?->lang_meta_origin : null;
        $related = $origin ? LanguageMeta::query()->where('reference_type', MenuModel::class)->where('lang_meta_origin', $origin)->pluck('reference_id', 'lang_meta_code')->all() : [];
        if ($reference) {
            $related[$this->meta($reference)?->lang_meta_code ?: $this->defaultCode()] = $reference->getKey();
        }
        $refFrom = $creating ? $source?->getKey() : null;
        $choices = $listing ? [['code' => 'all', 'name' => __('All languages'), 'flag' => '', 'active' => $code === 'all', 'exists' => true, 'url' => route('menus.index', ['ref_lang' => 'all'])]] : [];
        foreach ($languages as $languageCode => $language) {
            if ($listing) {
                $choices[] = ['code' => $languageCode, 'name' => $language->lang_name, 'flag' => $language->lang_flag, 'active' => $languageCode === $code, 'exists' => true, 'url' => route('menus.index', $languageCode === $this->defaultCode() ? [] : ['ref_lang' => $languageCode])];

                continue;
            }
            $existing = $related[$languageCode] ?? null;
            $canVisit = request()->user()?->hasPermission($existing ? 'menus.edit' : 'menus.create');
            $url = null;
            if ($canVisit) {
                $url = $existing ? route('menus.edit', $existing) : route('menus.create', ['ref_lang' => $languageCode, ...($reference ? ['ref_from' => $reference->getKey()] : [])]);
            }
            $choices[] = ['code' => $languageCode, 'name' => $language->lang_name, 'flag' => $language->lang_flag, 'active' => $languageCode === $code, 'exists' => (bool) $existing, 'url' => $url];
        }

        return ['code' => $code, 'refFrom' => $refFrom ? (string) $refFrom : null, 'choices' => $choices];
    }

    public function indexQuery(Builder $query): Builder
    {
        if (! $this->languages()) {
            return $query;
        }
        $code = $this->queryCode() ?: $this->defaultCode();
        $this->assertCode($code, true);
        Language::setCurrentAdminLocale($code);
        request()->attributes->set('admin.menu.language', $code);
        if ($code === 'all') {
            return $query;
        }

        return $query->where(function (Builder $query) use ($code): void {
            $query->whereHas('languageMeta', fn (Builder $query) => $query->where('lang_meta_code', $code));
            if ($code === $this->defaultCode()) {
                $query->orWhereDoesntHave('languageMeta');
            }
        });
    }

    public function validate(MenuRequest $request): void
    {
        if (! $this->languages()) {
            return;
        }
        $request->validate(['language' => ['nullable', 'string', 'max:20'], 'ref_lang' => ['nullable', 'string', 'max:20']]);
        $target = $request->route('menu');
        $target = $target instanceof MenuModel ? $target : null;
        $targetMeta = $target ? $this->meta($target) : null;
        $code = $request->input('language') ?: $request->input('ref_lang') ?: $targetMeta?->lang_meta_code ?: $this->defaultCode();
        $this->assertCode($code);
        if ($request->filled('ref_lang') && $request->input('ref_lang') !== $code) {
            throw ValidationException::withMessages(['language' => __('The language must match the editing locale.')]);
        }
        if ($target && $code !== ($targetMeta?->lang_meta_code ?: $this->defaultCode())) {
            throw ValidationException::withMessages(['language' => __('Create a separate menu to translate it.')]);
        }
        $source = $this->source($request);
        $sourceMeta = $source ? $this->meta($source) : null;
        if ($source && $target && ($source->getKey() !== $target->getKey()) && (! $targetMeta || ! $sourceMeta || $targetMeta->lang_meta_origin !== $sourceMeta->lang_meta_origin)) {
            throw ValidationException::withMessages(['ref_from' => __('The source menu belongs to another translation group.')]);
        }
        $origin = $sourceMeta?->lang_meta_origin ?: $targetMeta?->lang_meta_origin;
        if ($origin && LanguageMeta::query()->where('reference_type', MenuModel::class)->where('lang_meta_origin', $origin)->where('lang_meta_code', $code)->when($target, fn ($query) => $query->where('reference_id', '!=', $target->getKey()))->exists()) {
            throw ValidationException::withMessages(['language' => __('A menu already exists in this language.')]);
        }
        Language::setCurrentAdminLocale($code);
        request()->attributes->set('admin.menu.language', $code);
        $request->merge(['language' => $code]);
    }

    public function routeParameters(): array
    {
        if (! $this->languages()) {
            return [];
        }
        $target = request()->route('menu');
        $target = $target instanceof MenuModel ? $target : (is_scalar($target) ? MenuModel::query()->find($target) : null);
        $code = request('language') ?: $this->queryCode() ?: request()->attributes->get('admin.menu.language') ?: ($target ? $this->meta($target)?->lang_meta_code : null) ?: $this->defaultCode();
        $this->assertCode($code, str_ends_with((string) request()->route()?->getName(), '.index') || str_ends_with((string) request()->route()?->getName(), '.bulk'));

        return $code && $code !== $this->defaultCode() ? ['ref_lang' => $code] : [];
    }
}

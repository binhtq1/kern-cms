<?php

namespace Kern\Page\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Kern\Base\Events\UpdatedContentEvent;
use Kern\Base\Rules\MediaImageRule;
use Kern\LanguageAdvanced\Supports\LanguageAdvancedManager;
use Kern\Page\Forms\PageForm;
use Kern\Page\Http\Requests\PageRequest;
use Kern\Page\Models\Page;
use Kern\Page\Supports\AdminPageData;
use Kern\Slug\Events\UpdatedSlugEvent;
use Kern\Slug\Facades\SlugHelper;
use Kern\Slug\Models\Slug;

class SavePageService
{
    public function __construct(protected AdminPageData $data) {}

    public function execute(PageRequest $request, ?Page $page = null): Page
    {
        $language = $this->data->language();
        $translation = $this->data->isTranslation();
        if ($translation && ! $page?->exists) {
            throw ValidationException::withMessages(['language' => 'Create the default language page before translating it.']);
        }
        if ($request->filled('language') && $request->input('language') !== $language) {
            throw ValidationException::withMessages(['language' => 'The language must match the editing locale.']);
        }
        $this->validateCustomFields($request, $page ?: new Page);
        if ($request->filled('gallery')) {
            if (! is_plugin_active('gallery')) {
                throw ValidationException::withMessages(['gallery' => 'Gallery is unavailable.']);
            }
            $images = json_decode($request->input('gallery'), true);
            Validator::make(['gallery' => $images], [
                'gallery' => ['array', 'max:100'],
                'gallery.*' => ['array:img,description'],
                'gallery.*.img' => ['required', 'string', new MediaImageRule],
                'gallery.*.description' => ['nullable', 'string', 'max:1000'],
            ])->validate();
        }

        return DB::transaction(function () use ($request, $page, $translation, $language): Page {
            if ($translation) {
                $request->merge(['language' => $language, 'is_slug_editable' => 0]);
                LanguageAdvancedManager::save($page, $request);
                do_action(LANGUAGE_ADVANCED_ACTION_SAVED, $page, $request);
                $slug = Slug::query()->where('reference_type', Page::class)->where('reference_id', $page->getKey())->first();
                if ($slug && $request->has('slug')) {
                    $translatedSlug = $request->input('slug') ?: Str::slug($request->input('name'));
                    $prefix = SlugHelper::getPrefix(Page::class);
                    $duplicate = DB::table('slugs_translations')->where('lang_code', $language)->where('key', $translatedSlug)->where('prefix', $prefix)->where('slugs_id', '!=', $slug->getKey())->exists();
                    if ($duplicate) {
                        throw ValidationException::withMessages(['slug' => 'This URL is already used in this language.']);
                    }
                    DB::table('slugs_translations')->updateOrInsert(['lang_code' => $language, 'slugs_id' => $slug->getKey()], ['key' => $translatedSlug, 'prefix' => $prefix]);
                    UpdatedSlugEvent::dispatch($page, $slug);
                }
                UpdatedContentEvent::dispatch('form', $request, $page);

                return $page;
            }

            $form = $page ? PageForm::createFromModel($page) : PageForm::create();
            $form->setRequest($request)->onlyValidatedData()->save();

            return $form->getModel();
        });
    }

    protected function validateCustomFields(PageRequest $request, Page $page): void
    {
        if (! $request->filled('custom_fields')) {
            return;
        }
        if (! is_plugin_active('custom-field')) {
            throw ValidationException::withMessages(['custom_fields' => 'Custom fields are unavailable.']);
        }
        $submitted = json_decode($request->input('custom_fields'), true);
        if (! is_array($submitted)) {
            throw ValidationException::withMessages(['custom_fields' => 'Invalid custom fields.']);
        }
        $normalize = function (array $schema, array $values, int $depth = 0) use (&$normalize): array {
            if ($depth > 10 || count($values) > 1000) {
                throw ValidationException::withMessages(['custom_fields' => 'Too many nested custom fields.']);
            }
            $allowed = collect($schema)->keyBy(fn (array $item): string => (string) $item['id']);
            $result = [];
            foreach ($values as $item) {
                if (! is_array($item) || ! ($field = $allowed->get((string) ($item['id'] ?? '')))) {
                    throw ValidationException::withMessages(['custom_fields' => 'An unknown custom field was submitted.']);
                }
                $value = $item['value'] ?? '';
                if ($field['type'] === 'repeater') {
                    if (! is_array($value) || count($value) > 100) {
                        throw ValidationException::withMessages(['custom_fields' => 'Invalid repeater value.']);
                    }
                    $value = array_map(fn ($row): array => $normalize($field['items'], (array) $row, $depth + 1), $value);
                } elseif ($field['type'] === 'checkbox') {
                    if (! is_array($value) || array_filter($value, fn ($item): bool => ! is_scalar($item))) {
                        throw ValidationException::withMessages(['custom_fields' => 'Invalid checkbox value.']);
                    }
                } elseif (! is_scalar($value) && $value !== null) {
                    throw ValidationException::withMessages(['custom_fields' => 'Invalid custom field value.']);
                }
                $result[] = [...Arr::only($field, ['id', 'slug', 'type']), 'value' => $value ?? ''];
            }

            return $result;
        };
        $groups = collect($this->data->customFields($page))->keyBy(fn (array $group): string => (string) $group['id']);
        $safe = [];
        foreach ($submitted as $group) {
            if (! is_array($group) || ! ($schema = $groups->get((string) ($group['id'] ?? '')))) {
                throw ValidationException::withMessages(['custom_fields' => 'An unknown field group was submitted.']);
            }
            $safe[] = ['id' => $schema['id'], 'items' => $normalize($schema['items'], (array) ($group['items'] ?? []))];
        }
        $request->merge(['custom_fields' => json_encode($safe)]);
    }
}

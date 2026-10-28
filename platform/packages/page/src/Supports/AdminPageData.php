<?php

namespace Kern\Page\Supports;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Kern\Base\Enums\BaseStatusEnum;
use Kern\Base\Facades\BaseHelper;
use Kern\Base\Facades\MetaBox;
use Kern\Gallery\Facades\Gallery;
use Kern\Gallery\Models\GalleryMeta;
use Kern\Language\Facades\Language;
use Kern\LanguageAdvanced\Supports\LanguageAdvancedManager;
use Kern\Page\Forms\PageForm;
use Kern\Page\Models\Page;
use Kern\Shortcode\Events\ShortcodeAdminConfigRendering;
use Kern\Shortcode\Forms\ShortcodeForm;
use Kern\Slug\Facades\SlugHelper;
use Kern\Slug\Models\Slug;

class AdminPageData
{
    public function permalink(Model $model, ?Slug $slug): array
    {
        $prefix = apply_filters(FILTER_SLUG_PREFIX, SlugHelper::getPrefix($model::class), $model);

        return [
            'pattern' => url($prefix).'/'.config('packages.slug.general.pattern'),
            'ending' => SlugHelper::getPublicSingleEndingURL() ?: '',
            'preview' => SlugHelper::canPreview($model::class) && in_array((string) $model->status, [BaseStatusEnum::DRAFT, BaseStatusEnum::PENDING], true),
            'homepage' => $model instanceof Page && $model->exists && BaseHelper::isHomepage($model->getKey()) ? BaseHelper::getHomepageUrl() : null,
            'generateUrl' => route('slug.create'),
            'slugId' => (string) ($slug?->getKey() ?: 0),
            'model' => $model::class,
        ];
    }

    public function statuses(): array
    {
        return collect(BaseStatusEnum::labels())->map(fn ($label, $value): array => compact('label', 'value'))->values()->all();
    }

    public function templates(): array
    {
        return collect(Template::getPageTemplates())->map(fn ($label, $value): array => compact('label', 'value'))->values()->all();
    }

    public function permissions(Request $request): array
    {
        return [
            'create' => $request->user()->hasPermission('pages.create'),
            'edit' => $request->user()->hasPermission('pages.edit'),
            'delete' => $request->user()->hasPermission('pages.destroy'),
        ];
    }

    public function language(): ?string
    {
        if (! is_plugin_active('language') || ! is_plugin_active('language-advanced') || ! LanguageAdvancedManager::isSupported(Page::class)) {
            return null;
        }

        if (Language::getActiveLanguage()->isEmpty()) {
            return null;
        }

        $language = request()->input('ref_lang') ?: Language::getDefaultLocaleCode();
        abort_unless(Language::getActiveLanguage()->contains('lang_code', $language), 404);
        Language::setCurrentAdminLocale($language);

        return $language;
    }

    public function isTranslation(): bool
    {
        return ($language = $this->language()) && $language !== Language::getDefaultLocaleCode();
    }

    public function customFields(Page $page): array
    {
        if (! is_plugin_active('custom-field')) {
            return [];
        }

        add_custom_fields_rules_to_check([
            Page::class => $page->getKey(),
            'model_name' => Page::class,
            'logged_in_user' => auth()->id(),
            'logged_in_user_has_role' => auth()->user()->roles()->pluck('roles.id')->all(),
            'page_template' => request()->input('template', $page->template ?: 'default'),
        ]);

        return get_custom_field_boxes(Page::class, $page->getKey());
    }

    public function row(Page $page): array
    {
        $badges = [];
        if (BaseHelper::isHomepage($page->getKey())) {
            $badges[] = trans('packages/page::pages.front_page');
        }
        if (is_plugin_active('blog') && $page->getKey() == theme_option('blog_page_id', setting('blog_page_id'))) {
            $badges[] = trans('plugins/blog::base.blog_page');
        }
        if (is_plugin_active('gallery') && $page->getKey() == theme_option('galleries_page_id')) {
            $badges[] = trans('plugins/gallery::gallery.galleries_page');
        }

        return [
            'id' => (string) $page->getKey(),
            'name' => $page->name,
            'status' => $page->status->getValue(),
            'status_label' => $page->status->label(),
            'template' => $page->template,
            'template_label' => get_page_templates()[$page->template ?: 'default'] ?? $page->template,
            'created_at' => $page->created_at?->toIso8601String(),
            'badges' => $badges,
            'edit_url' => route('pages.edit', $page).($this->language() ? '?'.http_build_query(['ref_lang' => $this->language()]) : ''),
            'delete_url' => route('pages.destroy', $page).($this->language() ? '?'.http_build_query(['ref_lang' => $this->language()]) : ''),
        ];
    }

    public function shortcodes(): array
    {
        if (request()->attributes->has('admin.shortcodes')) {
            return request()->attributes->get('admin.shortcodes');
        }
        $result = [];
        if (function_exists('shortcode')) {
            ShortcodeAdminConfigRendering::dispatch();
            foreach (shortcode()->getAll() as $key => $item) {
                $config = $item['admin_config'] ?? null;
                if (is_callable($config)) {
                    $config = $config([], null);
                    if ($modifier = $item['admin_config_modifier'] ?? null) {
                        $config = $modifier($config, [], null);
                    }
                }
                $fields = [];
                $supported = ! $config || $config instanceof ShortcodeForm;
                if ($config instanceof ShortcodeForm) {
                    foreach ($config->getAdminSchemaFields() as $field) {
                        $type = match (class_basename($field)) {
                            'TextField' => 'text', 'NumberField' => 'number', 'ColorField' => 'color',
                            'TextareaField' => 'textarea', 'SelectField', 'MultiCheckListField' => 'select',
                            'CheckboxField', 'OnOffField', 'OnOffCheckboxField' => 'checkbox',
                            'MediaFileField' => 'file', 'MediaImageField' => 'image', 'HiddenField' => 'hidden',
                            default => $field->getType(),
                        };
                        if (! in_array($type, ['text', 'number', 'color', 'textarea', 'select', 'checkbox', 'hidden', 'file', 'image'])) {
                            $supported = false;
                            break;
                        }
                        $options = $field->getOptions();
                        $multiple = class_basename($field) === 'MultiCheckListField' || ! empty($options['attr']['multiple']) || ! empty($options['multiple']);
                        $choices = $options['choices'] ?? [];
                        $value = $options['value'] ?? $options['selected'] ?? $options['default_value'] ?? '';
                        if ($type === 'select' && ! $multiple && $choices && ! array_key_exists((string) $value, $choices)) {
                            $value = (string) array_key_first($choices);
                        }
                        $fields[] = [
                            'name' => preg_replace('/(?:\[\])+$/', '', $field->getName()), 'type' => $type,
                            'label' => strip_tags($options['label'] ?: $field->getName()),
                            'description' => strip_tags($options['help_block']['text'] ?? ''),
                            'value' => $value,
                            'choices' => collect($choices)->map(fn ($label, $value): array => ['label' => strip_tags($label), 'value' => (string) $value])->values()->all(),
                            'multiple' => $multiple,
                        ];
                    }
                }
                $result[] = ['key' => $key, 'name' => $item['name'] ?? $key, 'description' => strip_tags($item['description'] ?? ''), 'previewImage' => ($item['previewImage'] ?? '') ?: asset('vendor/core/packages/shortcode/images/placeholder-code.jpg'), 'code' => '['.$key.'][/'.$key.']', 'fields' => $fields, 'supported' => $supported, 'previewUrl' => Route::has('public.ajax.render-ui-block') ? route('public.ajax.render-ui-block') : null];
            }
        }
        request()->attributes->set('admin.shortcodes', $result);

        return $result;
    }

    public function form(Page $page): array
    {
        $language = $this->language();
        $translation = $this->isTranslation();
        $fields = ['name', 'description', 'content'];
        $translated = $translation && $page->exists
            ? DB::table('pages_translations')->where('pages_id', $page->getKey())->where('lang_code', $language)->first()
            : null;
        $slug = $page->exists ? Slug::query()->where('reference_type', Page::class)->where('reference_id', $page->getKey())->first() : null;
        $slugKey = $slug?->key;
        if ($translation && $slug) {
            $slugKey = DB::table('slugs_translations')->where('slugs_id', $slug->getKey())->where('lang_code', $language)->value('key') ?: $slugKey;
        }

        $languages = [];
        if ($language && $page->exists) {
            $languages = Language::getActiveLanguage()->map(fn ($item): array => [
                'code' => $item->lang_code,
                'name' => $item->lang_name,
                'active' => $item->lang_code === $language,
                'url' => route('pages.edit', $page).'?'.http_build_query(['ref_lang' => $item->lang_code]),
            ])->values()->all();
        }

        $galleryEnabled = is_plugin_active('gallery') && Gallery::isEnabledGalleryImagesMetaBox() && in_array(Page::class, Gallery::getSupportedModules());
        $gallery = $galleryEnabled && $page->exists ? gallery_meta_data($page) : [];
        if ($galleryEnabled && $translation && $page->exists) {
            $meta = GalleryMeta::query()->where('reference_type', Page::class)->where('reference_id', $page->getKey())->first();
            if ($meta) {
                $translatedGallery = DB::table('gallery_meta_translations')->where('gallery_meta_id', $meta->getKey())->where('lang_code', $language)->value('images');
                $gallery = $translatedGallery ? json_decode($translatedGallery, true) : $gallery;
            }
        }

        return [
            'bannerEnabled' => PageForm::createFromModel($page)->has('banner_image'),
            'galleryEnabled' => $galleryEnabled,
            'gallery' => $gallery,
            'revisions' => $page->exists && ! $translation ? app(AdminPageRevisionData::class)->forPage($page) : [],
            'page' => [
                'id' => $page->exists ? (string) $page->getKey() : null,
                'name' => $translated?->name ?? $page->name ?? '',
                'description' => $translated?->description ?? $page->description ?? '',
                'content' => $translated?->content ?? $page->content ?? '',
                'template' => $page->template ?: 'default',
                'status' => $page->status?->getValue() ?: 'published',
                'image' => $page->image ?: '',
                'banner_image' => MetaBox::getMetaData($page, 'banner_image', true) ?: '',
                'slug' => $slugKey ?: '',
                'seo_meta' => MetaBox::getMetaData($page, 'seo_meta', true) ?: [],
            ],
            'templates' => $this->templates(),
            'statuses' => $this->statuses(),
            'permissions' => $this->permissions(request()),
            'method' => 'post',
            'language' => $language,
            'languages' => $languages,
            'translatable' => $translation,
            'translatableFields' => $fields,
            'customFieldGroups' => $this->customFields($page),
            'permalink' => $this->permalink($page, $slug),
            'shortcodes' => $this->shortcodes(),
            'urls' => [
                'index' => route('pages.index').($language ? '?'.http_build_query(['ref_lang' => $language]) : ''),
                'submit' => ($page->exists ? route('pages.update', $page) : route('pages.store')).($language ? '?'.http_build_query(['ref_lang' => $language]) : ''),
                'media' => route('media.picker'),
                'mediaUpload' => route('media.picker-upload'),
            ],
        ];
    }
}

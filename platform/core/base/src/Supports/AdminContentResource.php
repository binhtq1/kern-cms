<?php

namespace Kern\Base\Supports;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Kern\ACL\Models\User;
use Kern\Base\Enums\BaseStatusEnum;
use Kern\Base\Events\UpdatedContentEvent;
use Kern\Base\Exceptions\DisabledInDemoModeException;
use Kern\Base\Facades\BaseHelper;
use Kern\Base\Facades\MetaBox;
use Kern\Base\Forms\FormAbstract;
use Kern\Base\Http\Actions\DeleteResourceAction;
use Kern\Base\Rules\MediaImageRule;
use Kern\Block\Forms\BlockForm;
use Kern\Block\Models\Block;
use Kern\Blog\Forms\CategoryForm;
use Kern\Blog\Forms\PostForm;
use Kern\Blog\Forms\TagForm;
use Kern\Blog\Models\Category;
use Kern\Blog\Models\Post;
use Kern\Blog\Models\Tag;
use Kern\Contact\Enums\ContactStatusEnum;
use Kern\Contact\Forms\ContactForm;
use Kern\Contact\Forms\CustomFieldForm;
use Kern\Contact\Models\Contact;
use Kern\Contact\Models\CustomField;
use Kern\CustomField\Models\FieldItem;
use Kern\Gallery\Facades\Gallery;
use Kern\Gallery\Forms\GalleryForm;
use Kern\Gallery\Models\GalleryMeta;
use Kern\Language\Facades\Language;
use Kern\LanguageAdvanced\Supports\LanguageAdvancedManager;
use Kern\Media\Facades\RvMedia;
use Kern\Member\Forms\MemberForm;
use Kern\Member\Models\Member;
use Kern\Page\Supports\AdminPageData;
use Kern\SeoHelper\Facades\SeoHelper;
use Kern\Slug\Events\UpdatedSlugEvent;
use Kern\Slug\Facades\SlugHelper;
use Kern\Slug\Models\Slug;

class AdminContentResource
{
    /** @return array<string, array{class: class-string<Model>, form: class-string<FormAbstract>, route: string, screen: string, title: string}> */
    public function modules(): array
    {
        return [
            'posts' => ['class' => Post::class, 'form' => PostForm::class, 'route' => 'posts', 'screen' => 'Posts', 'title' => trans('plugins/blog::posts.menu_name')],
            'categories' => ['class' => Category::class, 'form' => CategoryForm::class, 'route' => 'categories', 'screen' => 'Categories', 'title' => trans('plugins/blog::categories.menu')],
            'tags' => ['class' => Tag::class, 'form' => TagForm::class, 'route' => 'tags', 'screen' => 'Tags', 'title' => trans('plugins/blog::tags.menu')],
            'blocks' => ['class' => Block::class, 'form' => BlockForm::class, 'route' => 'block', 'screen' => 'Blocks', 'title' => trans('plugins/block::block.menu')],
            'galleries' => ['class' => \Kern\Gallery\Models\Gallery::class, 'form' => GalleryForm::class, 'route' => 'galleries', 'screen' => 'Galleries', 'title' => trans('plugins/gallery::gallery.galleries')],
            'members' => ['class' => Member::class, 'form' => MemberForm::class, 'route' => 'member', 'screen' => 'Members', 'title' => trans('plugins/member::member.menu_name')],
            'contacts' => ['class' => Contact::class, 'form' => ContactForm::class, 'route' => 'contacts', 'screen' => 'Contacts', 'title' => trans('plugins/contact::contact.menu')],
            'contact-fields' => ['class' => CustomField::class, 'form' => CustomFieldForm::class, 'route' => 'contacts.custom-fields', 'screen' => 'ContactFields', 'title' => trans('plugins/contact::contact.custom_field.name')],
        ];
    }

    public function fallbackReason(string $module): ?string
    {
        if (! isset($this->modules()[$module])) {
            return null;
        }
        $options = $this->modules()[$module];
        $model = collect(request()->route()?->parameters() ?? [])->first(fn ($item): bool => $item instanceof $options['class']);
        $model ??= new $options['class'];
        if ($module === 'contacts' && ! $model->exists) {
            $model->id = 0;
            $model->created_at = now();
        }
        $form = $this->makeForm($module, $model);
        if ($form->hasSchemaRenderingOutput()) {
            return 'Blade is required by '.$module.' rendering output.';
        }
        foreach ($form->getFields() as $field) {
            if ($this->fieldType($field) === null) {
                return 'Blade is required by '.$module.' form field: '.$field->getName().'.';
            }
        }
        foreach (array_keys($form->getMetaBoxes()) as $key) {
            if (! in_array($key, ['information', 'replies', 'contact-custom-field-options'])) {
                return 'Blade is required by '.$module.' meta box: '.$key.'.';
            }
        }
        foreach (app(AdminPageData::class)->shortcodes() as $shortcode) {
            if (! $shortcode['supported']) {
                return 'Blade is required by shortcode configuration: '.$shortcode['key'].'.';
            }
        }
        if (is_plugin_active('custom-field') && $this->hasUnsupportedCustomFields()) {
            return 'Blade is required by unsupported content custom fields.';
        }
        if (is_plugin_active('language') && ! is_plugin_active('language-advanced') && in_array($module, ['posts', 'categories', 'tags', 'blocks', 'galleries'])) {
            return 'Blade is required by legacy content language mode.';
        }

        return null;
    }

    protected function hasUnsupportedCustomFields(): bool
    {
        $request = request();
        $key = 'admin.content.unsupported-custom-fields';
        if ($request->isMethod('GET') && $request->attributes->has($key)) {
            return $request->attributes->get($key);
        }
        $unsupported = FieldItem::query()->whereNotIn('type', ['text', 'number', 'email', 'password', 'textarea', 'select', 'radio', 'checkbox', 'date', 'time', 'color', 'image', 'file', 'wysiwyg', 'repeater'])->exists();
        if ($request->isMethod('GET')) {
            $request->attributes->set($key, $unsupported);
        }

        return $unsupported;
    }

    protected function makeForm(string $module, Model $model): FormAbstract
    {
        $class = $this->modules()[$module]['form'];
        $cache = 'admin.content.form.'.$module.'.'.($model->getKey() ?: 'new');
        if (request()->attributes->has($cache)) {
            return request()->attributes->get($cache);
        }
        $form = $class::createFromModel($model);
        $form->prepareForSchema();
        request()->attributes->set($cache, $form);

        return $form;
    }

    protected function fieldType(object $field): ?string
    {
        return match (class_basename($field)) {
            'TextField', 'TagField' => 'text', 'CoreIconField' => 'icon', 'GoogleFontsField' => 'font',
            'NumberField' => 'number', 'HiddenField', 'PermalinkField' => 'hidden',
            'TextareaField' => 'textarea', 'CodeEditorField' => 'code', 'EditorField' => 'editor',
            'SelectField' => 'select', 'RadioField' => 'radio', 'TreeCategoryField' => 'categories',
            'OnOffField', 'CheckboxField', 'OnOffCheckboxField' => 'checkbox',
            'MediaImageField' => 'image', 'MediaFileField' => 'file', 'DatePickerField' => 'date',
            default => in_array($field->getType(), ['password', 'email', 'text', 'hidden', 'number']) ? $field->getType() : null,
        };
    }

    public function language(Model $model): ?string
    {
        if (! is_plugin_active('language') || ! is_plugin_active('language-advanced') || ! LanguageAdvancedManager::isSupported($model) || Language::getActiveLanguage()->isEmpty()) {
            return null;
        }
        $language = request()->input('ref_lang') ?: Language::getDefaultLocaleCode();
        abort_unless(Language::getActiveLanguage()->contains('lang_code', $language), 404);
        Language::setCurrentAdminLocale($language);

        return $language;
    }

    protected function url(string $name, Model|array|null $model = null, ?string $language = null): string
    {
        return route($name, $model ?: []).($language ? '?'.http_build_query(['ref_lang' => $language]) : '');
    }

    public function index(string $module): mixed
    {
        $options = $this->modules()[$module];
        $model = new $options['class'];
        $language = $this->language($model);
        $columns = $this->listColumns($module);
        $sorts = array_column(array_filter($columns, fn (array $column): bool => $column['sortable']), 'key');
        $filters = request()->validate([
            'search' => ['nullable', 'string', 'max:120'], 'status' => ['nullable', 'string', 'max:30'],
            'sort' => ['nullable', Rule::in($sorts)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'ref_lang' => ['nullable', 'string', 'max:20'],
        ]);
        $table = $model->getTable();
        $query = $model::query()->select($table.'.*');
        if ($module === 'posts') {
            $query->with(['categories', 'author']);
        } elseif ($module === 'members') {
            $query->with('avatar');
        }
        $name = $module === 'members' ? 'first_name' : 'name';
        if ($language && $language !== Language::getDefaultLocaleCode()) {
            $query->leftJoin($table.'_translations as admin_translation', fn ($join) => $join->on('admin_translation.'.$table.'_id', '=', $table.'.id')->where('admin_translation.lang_code', $language))
                ->addSelect(DB::raw('COALESCE(admin_translation.name, '.$table.'.name) as name'));
        }
        if ($search = $filters['search'] ?? null) {
            $query->where(function ($query) use ($search, $name, $table, $module, $language): void {
                $column = $language && $language !== Language::getDefaultLocaleCode() ? DB::raw('COALESCE(admin_translation.name, '.$table.'.name)') : $table.'.'.$name;
                $query->whereLike($column, '%'.$search.'%');
                if (in_array($module, ['members', 'contacts'])) {
                    $query->orWhereLike($table.'.email', '%'.$search.'%');
                }
                if ($module === 'posts') {
                    $query->orWhereHas('categories', fn ($categories) => $categories->whereLike('categories.name', '%'.$search.'%'));
                    $authors = [User::class];
                    if (is_plugin_active('member')) {
                        $authors[] = Member::class;
                    }
                    $query->orWhereHasMorph('author', $authors, function ($author) use ($search): void {
                        $authorTable = $author->getModel()->getTable();
                        $grammar = $author->getQuery()->getGrammar();
                        $firstName = $grammar->wrap($authorTable.'.first_name');
                        $lastName = $grammar->wrap($authorTable.'.last_name');
                        $fullName = $author->getConnection()->getDriverName() === 'sqlite'
                            ? $firstName." || ' ' || ".$lastName
                            : 'CONCAT('.$firstName.", ' ', ".$lastName.')';
                        $author->where(fn ($names) => $names->whereLike($authorTable.'.first_name', '%'.$search.'%')
                            ->orWhereLike($authorTable.'.last_name', '%'.$search.'%')
                            ->orWhereLike(DB::raw($fullName), '%'.$search.'%'));
                    });
                }
                if ($module === 'members') {
                    $grammar = $query->getQuery()->getGrammar();
                    $firstName = $grammar->wrap($table.'.first_name');
                    $lastName = $grammar->wrap($table.'.last_name');
                    $fullName = $query->getConnection()->getDriverName() === 'sqlite'
                        ? $firstName." || ' ' || ".$lastName
                        : 'CONCAT('.$firstName.", ' ', ".$lastName.')';
                    $query->orWhereLike($table.'.last_name', '%'.$search.'%')
                        ->orWhereLike(DB::raw($fullName), '%'.$search.'%');
                }
            });
        }
        if (! in_array($module, ['contact-fields', 'members']) && ($status = $filters['status'] ?? null)) {
            $query->where($table.'.status', $status);
        }
        $filters['advanced'] = app(AdminRegisteredTableData::class)->applyFilters($module, $query, request(), $language);
        $sort = $filters['sort'] ?? 'created_at';
        if ($sort === 'name') {
            $sort = $name;
        }
        $sortColumn = $sort === 'name' && $language && $language !== Language::getDefaultLocaleCode() ? DB::raw('COALESCE(admin_translation.name, '.$table.'.name)') : $table.'.'.$sort;
        $query->orderBy($sortColumn, $filters['direction'] ?? 'desc')->orderBy($table.'.id');
        if (request()->has('_export')) {
            return app(AdminRegisteredTableData::class)->export($module, $query, request());
        }
        $items = $query->paginate($filters['per_page'] ?? 20)->withQueryString()->through(fn (Model $item): array => [
            'id' => (string) $item->getKey(), 'name' => $item->name,
            'status' => is_object($item->status) ? $item->status->getValue() : $item->status, 'status_label' => is_object($item->status) ? $item->status->label() : (BaseStatusEnum::labels()[$item->status] ?? $item->status),
            'email' => in_array($module, ['members', 'contacts']) ? $item->email : null,
            ...match ($module) {
                'posts' => ['image' => RvMedia::getImageUrl($item->image, 'thumb', false, RvMedia::getDefaultImage()), 'categories' => $item->categories->sortBy('name')->map(fn ($category): array => ['name' => $category->name, 'url' => request()->user()->hasPermission('categories.edit') ? $this->url('categories.edit', $category, $language) : null])->values()->all(), 'author' => $item->author_name, 'author_url' => $item->author_url],
                'blocks' => ['alias' => $item->alias, 'shortcode' => function_exists('shortcode') ? shortcode()->generateShortcode('static-block', ['alias' => $item->alias]) : $item->alias],
                'galleries' => ['image' => RvMedia::getImageUrl($item->image, 'thumb', false, RvMedia::getDefaultImage()), 'order' => $item->order],
                'members' => ['image' => $item->avatar_thumb_url, 'confirmed_at' => (bool) $item->confirmed_at],
                'contacts' => ['phone' => $item->phone],
                'contact-fields' => ['type' => $item->type->getValue(), 'type_label' => $item->type->label()],
                default => [],
            },
            'created_at' => $item->created_at?->toIso8601String(),
            'edit_url' => $this->url($options['route'].'.edit', $item, $language),
            'delete_url' => $this->url($options['route'].'.destroy', $item, $language),
        ]);

        return Inertia::render($options['screen'].'/Index', [
            'title' => $options['title'], 'resource' => $module, 'items' => $items, 'filters' => $filters, 'columns' => $columns,
            'tableButtons' => app(AdminRegisteredTableData::class)->buttons($module),
            'tableVisibility' => app(AdminRegisteredTableData::class)->visibility($module),
            'statuses' => $this->choices($module === 'contacts' ? ContactStatusEnum::labels() : BaseStatusEnum::labels()),
            'permissions' => $this->permissions($module), 'bulkFields' => app(AdminRegisteredTableData::class)->fields($module, $model, $language),
            'urls' => ['index' => $this->url($options['route'].'.index', language: $language), 'create' => $module !== 'contacts' ? route($options['route'].'.create') : null, 'bulk' => route($options['route'].'.bulk'), ...($module === 'contacts' ? ['export' => route('contacts.export')] : [])],
        ]);
    }

    protected function listColumns(string $module): array
    {
        $columns = [
            ['key' => 'id', 'label' => 'ID', 'sortable' => true],
        ];
        if (in_array($module, ['posts', 'galleries', 'members'])) {
            $columns[] = ['key' => 'image', 'label' => $module === 'members' ? trans('plugins/member::member.avatar') : trans('core/base::tables.image'), 'sortable' => false];
        }
        $columns[] = ['key' => 'name', 'label' => trans('core/base::tables.name'), 'sortable' => $module !== 'members'];
        if ($module === 'contact-fields') {
            $columns[] = ['key' => 'type', 'label' => trans('plugins/contact::contact.custom_field.type'), 'sortable' => true];
        }
        if ($module === 'blocks') {
            $columns[] = ['key' => 'alias', 'label' => trans('core/base::tables.shortcode'), 'sortable' => false];
        }
        if ($module === 'posts') {
            $columns[] = ['key' => 'categories', 'label' => trans('plugins/blog::posts.categories'), 'sortable' => false];
            $columns[] = ['key' => 'author', 'label' => trans('plugins/blog::posts.author'), 'sortable' => false];
        }
        if (in_array($module, ['members', 'contacts'])) {
            $columns[] = ['key' => 'email', 'label' => trans('core/base::tables.email'), 'sortable' => true];
        }
        if ($module === 'contacts') {
            $columns[] = ['key' => 'phone', 'label' => trans('plugins/contact::contact.sender_phone'), 'sortable' => true];
        }
        if ($module === 'galleries') {
            $columns[] = ['key' => 'order', 'label' => trans('core/base::tables.order'), 'sortable' => true];
        }
        $columns[] = ['key' => 'created_at', 'label' => trans('core/base::tables.created_at'), 'sortable' => true];
        if (! in_array($module, ['members', 'contact-fields'])) {
            $columns[] = ['key' => 'status', 'label' => trans('core/base::tables.status'), 'sortable' => true];
        }
        if ($module === 'members' && setting('verify_account_email', false)) {
            $columns[] = ['key' => 'confirmed_at', 'label' => trans('plugins/member::member.email_verified'), 'sortable' => true];
        }

        return $columns;
    }

    public function permissions(string $module): array
    {
        $route = $this->modules()[$module]['route'];

        return collect(['create', 'edit', 'delete'])->mapWithKeys(fn ($action): array => [$action => request()->user()->hasPermission($module === 'contact-fields' ? 'contact.custom-fields' : $route.'.'.($action === 'delete' ? 'destroy' : $action))])->all();
    }

    protected function choices(iterable $choices): array
    {
        return collect($choices)->map(fn ($label, $value): array => ['value' => (string) $value, 'label' => strip_tags((string) $label)])->values()->all();
    }

    protected function fieldText(mixed $value): string
    {
        if (! is_string($value) && is_callable($value)) {
            $value = $value();
        }
        if (is_array($value)) {
            $value = $value['text'] ?? '';
            if (! is_string($value) && is_callable($value)) {
                $value = $value();
            }
        }

        return is_scalar($value) || $value instanceof \Stringable ? strip_tags((string) $value) : '';
    }

    public function form(string $module, ?Model $model = null): mixed
    {
        return Inertia::render($this->modules()[$module]['screen'].'/Form', $this->formData($module, $model));
    }

    public function isEmbeddedCategoryRequest(): bool
    {
        return request()->query('embedded') === '1' && request()->expectsJson() && ! request()->header('X-Inertia');
    }

    public function formData(string $module, ?Model $model = null): array
    {
        $options = $this->modules()[$module];
        $model ??= new $options['class'];
        $language = $this->language($model);
        $translation = $language && $language !== Language::getDefaultLocaleCode();
        $translatable = $language ? LanguageAdvancedManager::getTranslatableColumns($model) : [];
        $translated = $translation && $model->exists ? DB::table($model->getTable().'_translations')->where($model->getTable().'_id', $model->getKey())->where('lang_code', $language)->first() : null;
        $form = $this->makeForm($module, $model);
        $fields = [];
        $values = collect($model->only(['name', 'description', 'content', 'status', 'order', 'alias', 'raw_content', 'icon', 'is_default', 'is_featured', 'parent_id', 'format_type', 'type', 'required', 'placeholder', 'image']))->map(fn ($value) => is_object($value) && method_exists($value, 'getValue') ? $value->getValue() : $value)->all();
        foreach ($form->getFields() as $field) {
            $name = preg_replace('/\[\]$/', '', $field->getName());
            $settings = $field->getOptions();
            $type = $this->fieldType($field);
            $value = $translated?->{$name} ?? $model->getAttribute($name) ?? $field->getValue() ?? $field->getDefaultValue();
            if (! is_string($value) && is_callable($value)) {
                $value = $value();
            }
            if (is_object($value) && method_exists($value, 'getValue')) {
                $value = $value->getValue();
            }
            $choices = $settings['choices'] ?? [];
            if (is_callable($choices)) {
                $choices = $choices();
            }
            $categoryChoices = null;
            if ($type === 'categories') {
                $categoryChoices = Category::query()
                    ->wherePublished()
                    ->orderBy('parent_id')
                    ->orderBy('order')
                    ->orderByDesc('created_at')
                    ->get(['id', 'name', 'parent_id']);
                $selected = $settings['selected'] ?? ($model->exists ? $model->categories()->pluck('categories.id')->all() : Category::query()->wherePublished()->where('is_default', 1)->pluck('id')->all());
                $value = array_map(fn ($id): string => (string) $id, (array) $selected);
            }
            if ($module === 'posts' && $name === 'author_id') {
                $value = $model->author_type === Member::class ? $model->author_id : '';
                $choices = ['' => trans('plugins/member::member.select_author')] + (array) $choices;
            }
            if ($module === 'posts' && $name === 'tag') {
                $type = 'tags';
                $choices = Tag::query()->orderBy('name')->pluck('name', 'name')->all();
                $value = $settings['selected'] ?? ($model->exists ? $model->tags()->pluck('name')->implode(', ') : '');
            }
            if ($type === 'date' && $value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d');
            }
            if ($type === 'password') {
                $value = '';
            }
            if ($name === 'avatar_image') {
                $value = $model->exists ? $model->avatar?->url : '';
            }
            if ($type === 'select' && ($settings['selected'] ?? null) !== null && ! ($module === 'posts' && $name === 'author_id')) {
                $value = $settings['selected'];
            }
            if (! is_string($value) && is_callable($value)) {
                $value = $value();
            }
            if (is_object($value) && method_exists($value, 'getValue')) {
                $value = $value->getValue();
            }
            if ($type === 'select' && ($settings['empty_value'] ?? null)) {
                $choices = ['' => $settings['empty_value']] + collect($choices)->all();
            }
            $fieldChoices = $type === 'categories'
                ? $categoryChoices->map(fn (Category $category): array => [
                    'value' => (string) $category->getKey(),
                    'label' => strip_tags((string) $category->name),
                    'parent_id' => $category->parent_id ? (string) $category->parent_id : null,
                ])->values()->all()
                : $this->choices($choices);
            if ($type === 'select' && ($value === null || $value === '') && ! collect($fieldChoices)->contains('value', (string) $value)) {
                $value = $fieldChoices[0]['value'] ?? '';
            }
            if ($type === 'checkbox') {
                $value = in_array($value, [true, 1, '1'], true) ? 1 : 0;
            }
            $values[$name] = $value ?? ($type === 'checkbox' ? 0 : '');
            $fields[] = ['name' => $name, 'label' => strip_tags((string) (($settings['label'] ?? null) ?: $name)), 'type' => $type, 'required' => (bool) ($settings['attr']['required'] ?? false), 'choices' => $fieldChoices, 'readonly' => (bool) ($settings['attr']['readonly'] ?? $settings['attr']['disabled'] ?? false) || ($translation && ! in_array($name, $translatable)), 'placeholder' => $this->fieldText($settings['attr']['placeholder'] ?? ''), 'description' => $this->fieldText($settings['help_block'] ?? '') ?: $this->fieldText($settings['description'] ?? ''), 'maxLength' => $settings['attr']['maxlength'] ?? null, 'mode' => $settings['attr']['mode'] ?? 'htmlmixed', 'iconsUrl' => $type === 'icon' ? route('core-icons') : null];
        }
        $slugEnabled = SlugHelper::isSupportedModel($model::class);
        $slug = $slugEnabled && $model->exists ? Slug::query()->where('reference_type', $model::class)->where('reference_id', $model->getKey())->first() : null;
        $values['slug'] = $translation && $slug ? (DB::table('slugs_translations')->where('slugs_id', $slug->getKey())->where('lang_code', $language)->value('key') ?: $slug->key) : ($slug?->key ?: '');
        $values['seo_meta'] = MetaBox::getMetaData($model, 'seo_meta', true) ?: [];
        $values['is_slug_editable'] = 1;
        $galleryEnabled = is_plugin_active('gallery') && in_array($model::class, Gallery::getSupportedModules()) && ($module === 'galleries' || Gallery::isEnabledGalleryImagesMetaBox());
        $gallery = $galleryEnabled && $model->exists ? gallery_meta_data($model) : [];
        if ($translation && $galleryEnabled && $model->exists) {
            $meta = GalleryMeta::query()->where('reference_type', $model::class)->where('reference_id', $model->getKey())->first();
            if ($meta) {
                $images = DB::table('gallery_meta_translations')->where('gallery_meta_id', $meta->getKey())->where('lang_code', $language)->value('images');
                $gallery = $images ? json_decode($images, true) : $gallery;
            }
        }
        $groups = $this->customFields($model);
        $fieldOptions = $module === 'contact-fields' ? $model->options()->orderBy('order')->get(['id', 'label', 'value', 'order'])->map(fn ($option): array => ['id' => (string) $option->getKey(), 'label' => $option->label, 'value' => $option->value, 'order' => $option->order])->all() : [];
        if ($translation && $module === 'contact-fields') {
            foreach ($fieldOptions as &$option) {
                $translatedOption = DB::table('contact_custom_field_options_translations')->where('contact_custom_field_options_id', $option['id'])->where('lang_code', $language)->first(['label', 'value']);
                foreach (['label', 'value'] as $column) {
                    $option[$column] = $translatedOption?->{$column} ?: $option[$column];
                }
            }
            unset($option);
        }
        $languages = $language && $model->exists ? Language::getActiveLanguage()->map(fn ($item): array => ['name' => $item->lang_name, 'active' => $item->lang_code === $language, 'url' => $this->url($options['route'].'.edit', $model, $item->lang_code)])->values()->all() : [];

        return [
            'title' => $options['title'], 'resource' => $module, 'id' => $model->exists ? (string) $model->getKey() : null,
            'values' => $values, 'fields' => $fields, 'language' => $language, 'languages' => $languages, 'translatable' => (bool) $translation,
            'slugEnabled' => $slugEnabled, 'seoEnabled' => in_array($model::class, SeoHelper::supportedModules()),
            'permalink' => $slugEnabled ? app(AdminPageData::class)->permalink($model, $slug) : null,
            'galleryEnabled' => $galleryEnabled, 'gallery' => $gallery, 'customFieldGroups' => $groups,
            'shortcodes' => app(AdminPageData::class)->shortcodes(), 'permissions' => $this->permissions($module),
            'contact' => $module === 'contacts' ? $model->only(['name', 'email', 'phone', 'address', 'subject', 'content', 'custom_fields', 'created_at']) : null,
            'replies' => $module === 'contacts' ? $model->replies()->oldest()->get(['id', 'message', 'created_at'])->toArray() : [],
            'fieldOptions' => $fieldOptions,
            'urls' => ['index' => $this->url($options['route'].'.index', language: $language), 'submit' => $this->url($options['route'].($model->exists ? '.update' : '.store'), $model->exists ? $model : null, $language),
                'media' => Route::has('media.picker') ? route('media.picker') : route('pages.media'), 'mediaUpload' => Route::has('media.picker-upload') ? route('media.picker-upload') : route('pages.media-upload'),
                'reply' => $module === 'contacts' ? route('contacts.reply', $model) : null,
                'verify' => $module === 'members' && $model->exists && ! $model->confirmed_at ? route('member.verify-email', $model) : null],
        ];
    }

    public function customFields(Model $model): array
    {
        if (! is_plugin_active('custom-field')) {
            return [];
        }
        add_custom_fields_rules_to_check([$model::class => $model->getKey(), 'model_name' => $model::class, 'logged_in_user' => auth()->id(), 'logged_in_user_has_role' => auth()->user()->roles()->pluck('roles.id')->all()]);
        if ($model instanceof Post) {
            add_custom_fields_rules_to_check([
                $model::class.'_post_with_related_category' => $model->categories()->allRelatedIds()->all(),
                $model::class.'_post_format' => $model->format_type,
            ]);
        }

        return get_custom_field_boxes($model::class, $model->getKey());
    }

    public function prepare(string $module, Request $request, ?Model $model = null): RedirectResponse|JsonResponse|null
    {
        if ((! $request->header('X-Inertia') && ! ($module === 'categories' && $this->isEmbeddedCategoryRequest()))) {
            return null;
        }
        $model ??= new ($this->modules()[$module]['class']);
        $language = $this->language($model);
        Validator::make($request->all(), [
            'slug' => ['nullable', 'string', 'max:250', 'regex:/^[\pL\pN_-]*$/u'],
            'seo_meta' => ['nullable', 'array:seo_title,seo_description,seo_image,index'],
            'seo_meta.seo_title' => ['nullable', 'string', 'max:250'], 'seo_meta.seo_description' => ['nullable', 'string', 'max:1000'],
            'seo_meta.seo_image' => ['nullable', 'string', new MediaImageRule], 'seo_meta.index' => ['nullable', Rule::in(['index', 'noindex'])],
            'gallery' => ['nullable', 'json'], 'custom_fields' => ['nullable', 'json'],
        ])->validate();
        if ($request->has('gallery')) {
            Validator::make(['gallery' => json_decode($request->input('gallery'), true)], ['gallery' => ['array', 'max:100'], 'gallery.*' => ['array:img,description'], 'gallery.*.img' => ['required', 'string', new MediaImageRule], 'gallery.*.description' => ['nullable', 'string', 'max:1000']])->validate();
        }
        if ($request->filled('custom_fields')) {
            $this->validateCustomFields($request, $model);
        }
        if ($language && $language !== Language::getDefaultLocaleCode()) {
            if (! $model->exists) {
                throw ValidationException::withMessages(['language' => 'Create the default language item before translating it.']);
            }
            $request->merge(['language' => $language, 'is_slug_editable' => 0]);
            DB::transaction(function () use ($model, $request, $module, $language): void {
                LanguageAdvancedManager::save($model, $request);
                $slug = Slug::query()->where('reference_type', $model::class)->where('reference_id', $model->getKey())->first();
                if ($slug && $request->has('slug')) {
                    $translatedSlug = $request->input('slug') ?: Str::slug($request->input('name'));
                    $prefix = SlugHelper::getPrefix($model::class);
                    $duplicate = DB::table('slugs_translations')->where('lang_code', $language)->where('key', $translatedSlug)->where('prefix', $prefix)->where('slugs_id', '!=', $slug->getKey())->exists();
                    if ($duplicate) {
                        throw ValidationException::withMessages(['slug' => 'This URL is already used in this language.']);
                    }
                    DB::table('slugs_translations')->updateOrInsert(['lang_code' => $language, 'slugs_id' => $slug->getKey()], ['key' => $translatedSlug, 'prefix' => $prefix]);
                    UpdatedSlugEvent::dispatch($model, $slug);
                }
                if ($module === 'contact-fields') {
                    foreach ($request->input('options', []) as $option) {
                        $existing = $model->options()->findOrFail($option['id']);
                        LanguageAdvancedManager::save($existing, new Request(['language' => $language, 'label' => $option['label'], 'value' => $option['value']]));
                    }
                }
                do_action(LANGUAGE_ADVANCED_ACTION_SAVED, $model, $request);
                UpdatedContentEvent::dispatch('form', $request, $model);
            });

            return $this->redirect($module, $model);
        }

        return null;
    }

    protected function validateCustomFields(Request $request, Model $model): void
    {
        $normalize = function (array $schema, array $values, int $depth = 0) use (&$normalize): array {
            if ($depth > 10 || count($values) > 1000) {
                throw ValidationException::withMessages(['custom_fields' => 'Too many nested custom fields.']);
            }
            $allowed = collect($schema)->keyBy(fn ($item): string => (string) $item['id']);
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
                $result[] = [...Arr::only($field, ['id', 'slug', 'type']), 'value' => $value];
            }

            return $result;
        };
        $schemas = collect($this->customFields($model))->keyBy(fn ($group): string => (string) $group['id']);
        $safe = [];
        $submitted = json_decode($request->input('custom_fields'), true);
        if (! is_array($submitted)) {
            throw ValidationException::withMessages(['custom_fields' => 'Invalid custom fields.']);
        }
        foreach ($submitted as $group) {
            if (! is_array($group) || ! ($schema = $schemas->get((string) ($group['id'] ?? '')))) {
                throw ValidationException::withMessages(['custom_fields' => 'An unknown field group was submitted.']);
            }
            $safe[] = ['id' => $schema['id'], 'items' => $normalize($schema['items'], (array) ($group['items'] ?? []))];
        }
        $request->merge(['custom_fields' => json_encode($safe)]);
    }

    public function redirect(string $module, Model $model): RedirectResponse|JsonResponse|null
    {
        if ($module === 'categories' && $this->isEmbeddedCategoryRequest()) {
            request()->attributes->remove('admin.content.form.'.$module.'.'.$model->getKey());

            return response()->json(['error' => false, 'message' => trans('core/base::notices.update_success_message'), 'data' => ['form' => $this->formData($module, $model->fresh())]]);
        }

        if (! request()->header('X-Inertia')) {
            return null;
        }
        $route = $this->modules()[$module]['route'];
        $language = $this->language($model);

        return redirect()->to(request()->input('submitter') === 'save' ? $this->url($route.'.index', language: $language) : $this->url($route.'.edit', $model, $language))->with('success_msg', trans('core/base::notices.update_success_message'));
    }

    public function bulk(Request $request): mixed
    {
        if (BaseHelper::hasDemoModeEnabled()) {
            throw new DisabledInDemoModeException;
        }
        $module = app(AdminRenderer::class)->routeModule();
        abort_unless(isset($this->modules()[$module]), 404);
        $options = $this->modules()[$module];
        $language = $this->language(new $options['class']);
        $indexQuery = app(AdminRegisteredTableData::class)->indexQuery($request, $language);
        if (app(AdminRegisteredTableData::class)->table($module) && ! ($module === 'members' && $request->input('action') === 'status')) {
            return app(AdminRegisteredTableData::class)->bulk($request, $module, $options['class'], $this->permissions($module), $language);
        }
        $input = $request->validate(['ids' => ['required', 'array', 'list', 'min:1', 'max:100'], 'ids.*' => ['required', 'integer', 'distinct', Rule::exists($options['class'], 'id')], 'action' => ['required', Rule::in(['delete', 'status'])], 'status' => ['required_if:action,status', Rule::in(array_keys($module === 'contacts' ? ContactStatusEnum::labels() : BaseStatusEnum::labels()))]]);
        abort_unless($this->permissions($module)[$input['action'] === 'delete' ? 'delete' : 'edit'], 403);
        if ($input['action'] === 'status' && $language && $language !== Language::getDefaultLocaleCode()) {
            throw ValidationException::withMessages(['status' => 'Only translated fields can be changed in this language.']);
        }
        $request->replace($input['action'] === 'status' ? ['status' => $input['status']] : []);
        $request->query->replace([]);
        DB::transaction(function () use ($input, $options, $request): void {
            $models = $options['class']::query()->whereKey($input['ids'])->lockForUpdate()->get();
            abort_unless($models->count() === count($input['ids']), 422);
            foreach ($models as $model) {
                if ($input['action'] === 'delete') {
                    $result = DeleteResourceAction::make($model)->execute()->toArray();
                    if ($result['error']) {
                        throw ValidationException::withMessages(['ids' => $result['message']]);
                    }
                } else {
                    $options['form']::createFromModel($model)->setRequest($request)->saving(function ($form) use ($input): void {
                        $model = $form->getModel();
                        $model->status = $input['status'];
                        $model->save();
                    });
                }
            }
        });

        return redirect()->route($options['route'].'.index', $indexQuery)->with('success_msg', trans('core/base::notices.update_success_message'));
    }

    public function destroy(string $module, Model $model): mixed
    {
        $action = DeleteResourceAction::make($model);
        if (! request()->header('X-Inertia')) {
            return $action;
        }
        $language = $this->language($model);
        $result = $action->execute()->toArray();

        return redirect()->to($this->url($this->modules()[$module]['route'].'.index', language: $language))->with($result['error'] ? 'error_msg' : 'success_msg', $result['message']);
    }
}

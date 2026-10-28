<?php

namespace Kern\Base\Forms;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Tappable;
use Kern\Base\Contracts\BaseModel;
use Kern\Base\Contracts\Builders\Extensible as ExtensibleContract;
use Kern\Base\Events\BeforeCreateContentEvent;
use Kern\Base\Events\BeforeEditContentEvent;
use Kern\Base\Events\BeforeUpdateContentEvent;
use Kern\Base\Events\CreatedContentEvent;
use Kern\Base\Events\FormRendering;
use Kern\Base\Events\UpdatedContentEvent;
use Kern\Base\Facades\Assets;
use Kern\Base\Forms\Fields\AutocompleteField;
use Kern\Base\Forms\Fields\ColorField;
use Kern\Base\Forms\Fields\DatePickerField;
use Kern\Base\Forms\Fields\DatetimeField;
use Kern\Base\Forms\Fields\EditorField;
use Kern\Base\Forms\Fields\HtmlField;
use Kern\Base\Forms\Fields\MediaFileField;
use Kern\Base\Forms\Fields\MediaImageField;
use Kern\Base\Forms\Fields\MediaImagesField;
use Kern\Base\Forms\Fields\MultiCheckListField;
use Kern\Base\Forms\Fields\OnOffCheckboxField;
use Kern\Base\Forms\Fields\OnOffField;
use Kern\Base\Forms\Fields\RadioField;
use Kern\Base\Forms\Fields\RepeaterField;
use Kern\Base\Forms\Fields\SelectField;
use Kern\Base\Forms\Fields\TagField;
use Kern\Base\Forms\Fields\TimeField;
use Kern\Base\Models\BaseModel as BaseModelInstance;
use Kern\Base\Supports\Builders\Extensible;
use Kern\Base\Supports\Builders\RenderingExtensible;
use Kern\Base\Traits\Forms\HasColumns;
use Kern\Base\Traits\Forms\HasFieldset;
use Kern\Base\Traits\Forms\HasMetaBoxes;
use Kern\Base\Traits\Forms\HasMetadata;
use Kern\Base\Traits\Forms\HasTabs;
use Kern\JsValidation\Facades\JsValidator;
use Kern\JsValidation\Javascript\JavascriptValidator;
use Kris\LaravelFormBuilder\Fields\FormField;

abstract class FormAbstract extends Form implements ExtensibleContract
{
    use Conditionable;
    use Extensible;
    use HasColumns;
    use HasFieldset;
    use HasMetaBoxes;
    use HasMetadata;
    use HasTabs;
    use RenderingExtensible;
    use Tappable;

    protected array $options = [];

    protected string $title = '';

    protected string $validatorClass = '';

    protected string $actionButtons = '';

    protected string $breakFieldPoint = '';

    protected bool $useInlineJs = false;

    protected string $wrapperClass = 'form-body';

    protected bool $onlyValidatedData = false;

    protected bool $preparedForSchema = false;

    protected string $schemaRenderingOutput = '';

    protected bool $withoutActionButtons = false;

    protected bool $disabledPermalinkField = false;

    public function __construct()
    {
        $this->setMethod('POST');
        $this->template('core/base::forms.form');
        $this->setFormOption('id', strtolower(Str::slug(Str::snake(static::class))));
        $this->setFormOption('class', 'js-base-form');
    }

    public function setup(): void {}

    public function buildForm(): void
    {
        $this->withCustomFields();

        $this->setup();

        if (! $this->model) {
            $this->model = new BaseModelInstance;
        }

        $this->setupExtended();
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function setOptions(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getActionButtons(): string
    {
        if ($this->actionButtons === '') {
            return view('core/base::forms.partials.form-actions')->render();
        }

        return $this->actionButtons;
    }

    public function setActionButtons(string $actionButtons): static
    {
        $this->actionButtons = $actionButtons;

        return $this;
    }

    public function removeActionButtons(): static
    {
        $this->actionButtons = '';

        return $this;
    }

    public function getBreakFieldPoint(): string
    {
        return $this->breakFieldPoint;
    }

    public function withoutActionButtons(bool $withoutActionButtons = true): static
    {
        $this->withoutActionButtons = $withoutActionButtons;

        return $this;
    }

    public function isWithoutActionButtons(): bool
    {
        return $this->withoutActionButtons;
    }

    public function setBreakFieldPoint(string $breakFieldPoint): static
    {
        $this->breakFieldPoint = $breakFieldPoint;

        return $this;
    }

    public function isUseInlineJs(): bool
    {
        return $this->useInlineJs;
    }

    public function setUseInlineJs(bool $useInlineJs): static
    {
        $this->useInlineJs = $useInlineJs;

        return $this;
    }

    public function getWrapperClass(): string
    {
        return $this->wrapperClass;
    }

    public function setWrapperClass(string $wrapperClass): static
    {
        $this->wrapperClass = $wrapperClass;

        return $this;
    }

    public function withCustomFields(): static
    {
        $customFields = [
            'customSelect' => SelectField::class,
            'editor' => EditorField::class,
            'onOff' => OnOffField::class,
            'onOffCheckbox' => OnOffCheckboxField::class,
            'customRadio' => RadioField::class,
            'mediaImage' => MediaImageField::class,
            'mediaImages' => MediaImagesField::class,
            'mediaFile' => MediaFileField::class,
            'customColor' => ColorField::class,
            'time' => TimeField::class,
            'datePicker' => DatePickerField::class,
            'datetime' => DatetimeField::class,
            'autocomplete' => AutocompleteField::class,
            'html' => HtmlField::class,
            'repeater' => RepeaterField::class,
            'tags' => TagField::class,
            'multiCheckList' => MultiCheckListField::class,
        ];

        foreach ($customFields as $key => $field) {
            $this->addCustomField($key, $field);
        }

        return apply_filters('form_custom_fields', $this, $this->getFormHelper());
    }

    public function addCustomField(string $name, string $class): static
    {
        if ($this->rebuilding && $this->formHelper->hasCustomField($name)) {
            return $this;
        }

        if (! $this->formHelper->hasCustomField($name)) {
            $this->formHelper->addCustomField($name, $class);
        }

        return $this;
    }

    public function hasMainFields(): bool
    {
        if (! $this->breakFieldPoint) {
            return ! empty($this->fields);
        }

        $mainFields = [];

        /**
         * @var FormField $field
         */
        foreach ($this->fields as $field) {
            if ($field->getName() == $this->breakFieldPoint) {
                break;
            }

            $mainFields[] = $field;
        }

        return ! empty($mainFields);
    }

    public function disableFields(): static
    {
        parent::disableFields();

        return $this;
    }

    protected function addField(FormField $field, bool $modify = false): static
    {
        if (! $modify && ! $this->rebuilding) {
            $this->preventDuplicate($field->getRealName());
        }

        if ($field->getType() == 'file') {
            $this->hasFiles();
        }

        $this->fields[$field->getRealName()] = $field;

        return $this;
    }

    public function prepareForSchema(bool $forSchema = true): static
    {
        if ($this->preparedForSchema) {
            return $this;
        }
        $this->preparedForSchema = true;
        $previous = request()->attributes->get('admin_schema_rendering');
        if ($forSchema) {
            request()->attributes->set('admin_schema_rendering', true);
            ob_start();
        }
        try {
            $model = $this->getModel();
            $this->dispatchBeforeRendering();
            FormRendering::dispatch($this);
            if ($this->getModel() instanceof BaseModel) {
                apply_filters(BASE_FILTER_BEFORE_RENDER_FORM, $this, $this->getModel());
            }
            $this->setupMetadataFields();
            if ($model instanceof BaseModel) {
                if ($model->getKey()) {
                    event(new BeforeEditContentEvent($this->request, $model));
                } else {
                    event(new BeforeCreateContentEvent($this->request, $model));
                }
            }
            apply_filters(BASE_FILTER_AFTER_RENDER_FORM, $this, $this->getModel());
        } finally {
            if ($forSchema) {
                $this->schemaRenderingOutput = (string) ob_get_clean();
                request()->attributes->set('admin_schema_rendering', $previous);
            }
        }

        return $this;
    }

    public function hasSchemaRenderingOutput(): bool
    {
        return trim($this->schemaRenderingOutput) !== '';
    }

    public function renderForm(array $options = [], bool $showStart = true, bool $showFields = true, bool $showEnd = true): string
    {
        Assets::addScripts(['form-validation', 'are-you-sure']);

        $class = $this->getFormOption('class');
        $this->setFormOption('class', $class.' dirty-check');

        $this->prepareForSchema(false);
        $schemaOutput = $this->schemaRenderingOutput;
        $this->schemaRenderingOutput = '';

        return tap(
            $schemaOutput.parent::renderForm($options, $showStart, $showFields, $showEnd),
            fn ($rendered) => $this->dispatchAfterRendering($rendered)
        );
    }

    public function renderValidatorJs(): string|JavascriptValidator
    {
        return JsValidator::formRequest($this->getValidatorClass(), $this->getDomSelector());
    }

    public function getDomSelector(): ?string
    {
        $element = null;
        if ($formId = $this->getFormOption('id')) {
            $element = '#'.$formId;
        } elseif ($formClass = $this->getFormOption('class')) {
            $element = '.'.$formClass;
        }

        return $element;
    }

    public function getValidatorClass(): string
    {
        return $this->validatorClass;
    }

    public function setValidatorClass(string $validatorClass): static
    {
        $this->validatorClass = $validatorClass;

        return $this;
    }

    public function setupModel($model): static
    {
        if (! $this->model) {
            $this->model = $model;
            $this->setupNamedModel();
        }

        return $this;
    }

    public function model($model): static
    {
        if (is_string($model)) {
            $model = new $model;
        }

        $this->setupModel($model);

        return $this;
    }

    public function setFormOptions(array $formOptions): static
    {
        parent::setFormOptions($formOptions);

        if (isset($formOptions['template'])) {
            $this->template($formOptions['template']);
        }

        return $this;
    }

    public function add(string $name, string $type = 'text', array|Arrayable $options = [], bool $modify = false): static
    {
        if ($options instanceof Arrayable) {
            $options = $options->toArray();
        }

        if (Assets::hasVueJs()) {
            $options['attr']['v-pre'] = 1;
        }

        parent::add($name, $type, $options, $modify);

        return $this;
    }

    public function template(string $template): static
    {
        $this->setFormOption('template', $template);

        return $this;
    }

    public function contentOnly(): static
    {
        $this->template('core/base::forms.form-content-only');

        return $this;
    }

    public function setUrl($url): static
    {
        $this->setFormOption('url', $url);

        return $this;
    }

    public function onlyValidatedData(bool $onlyValidatedData = true): static
    {
        $this->onlyValidatedData = $onlyValidatedData;

        return $this;
    }

    public function getRequestData(): array
    {
        $request = $this->request;

        if ($this->onlyValidatedData && $request instanceof FormRequest) {
            return $request->validated();
        }

        return $request->input();
    }

    public static function beforeSaving(callable|Closure $callback, int $priority = 100): void
    {
        if (static::class === FormAbstract::class) {
            add_action(BASE_FILTER_BEFORE_SAVE_FORM, $callback, $priority, 2);

            return;
        }

        add_action(static::getFilterPrefix().'_before_saving', $callback, $priority, 2);
    }

    public static function afterSaving(callable|Closure $callback, int $priority = 100): void
    {
        if (static::class === FormAbstract::class) {
            add_action(BASE_FILTER_AFTER_SAVE_FORM, $callback, $priority, 2);

            return;
        }

        add_action(static::getFilterPrefix().'_after_saving', $callback, $priority, 2);
    }

    public function save(): static
    {
        $this->saving(function (FormAbstract $form): void {
            $model = $form->getModel();

            $model->fill($form->getRequestData())
                ->save();

            $form->setModel($model);
        });

        return $this;
    }

    public function saveOnlyValidatedData(): void
    {
        $this->onlyValidatedData()->save();
    }

    public function saving(callable|Closure $callback): void
    {
        $model = $this->getModel();
        $request = $this->request;

        if ($model instanceof BaseModel) {
            if ($model->getKey()) {
                BeforeUpdateContentEvent::dispatch($request, $model);
            } else {
                BeforeCreateContentEvent::dispatch($request, $model);
            }
        }

        $this->dispatchBeforeSaving();

        call_user_func($callback, $this);

        $this->saveMetadataFields();

        $this->dispatchAfterSaving();

        $model = $this->getModel();

        if ($model instanceof Model && $model->exists) {
            $this->fireModelEvents($model);
        }
    }

    public function fireModelEvents(Model $model): void
    {
        if ($model->wasRecentlyCreated) {
            CreatedContentEvent::dispatch('form', $this->request, $model);
        } else {
            UpdatedContentEvent::dispatch('form', $this->request, $model);
        }
    }

    protected function dispatchBeforeSaving(): void
    {
        do_action(BASE_FILTER_BEFORE_SAVE_FORM, $this);
        do_action(static::getFilterPrefix().'_before_saving', $this);
    }

    protected function dispatchAfterSaving(): void
    {
        do_action(BASE_FILTER_AFTER_SAVE_FORM, $this);
        do_action(static::getFilterPrefix().'_after_saving', $this);
    }

    public static function getFilterPrefix(): string
    {
        return sprintf(
            'base_form_%s',
            Str::of(static::class)->snake()->lower()->replace('\\', '')->toString()
        );
    }

    public static function getGlobalClassName(): string
    {
        return FormAbstract::class;
    }

    public static function hasGlobalExtend(): bool
    {
        return true;
    }

    public static function globalExtendFilterName(): string
    {
        return BASE_FILTER_EXTENDED_FORM;
    }

    public static function hasGlobalRendering(): bool
    {
        return true;
    }

    public static function globalBeforeRenderingFilterName(): string
    {
        return BASE_FILTER_BEFORE_RENDER_FORM;
    }

    public static function globalAfterRenderingFilterName(): string
    {
        return BASE_FILTER_AFTER_RENDER_FORM;
    }

    public static function create(array $options = [], array $data = [])
    {
        return app(FormBuilder::class)->create(static::class, $options, $data);
    }

    public static function createFromArray(array $object, array $options = [], array $data = []): static
    {
        return static::create([...$options, 'model' => $object], $data);
    }

    public static function createFromModel(BaseModel|Model $model, array $options = [], array $data = []): static
    {
        return static::create([...$options, 'model' => $model], $data);
    }

    public function hasFiles(bool $hasFiles = true): static
    {
        $this->setFormOption('files', $hasFiles);

        return $this;
    }

    public function formClass(string $class, bool $override = false): static
    {
        if ($override) {
            $this->setFormOption('class', $class);

            return $this;
        }

        $this->setFormOption('class', $this->getFormOption('class').' '.$class);

        return $this;
    }

    public function disablePermalinkField(bool $disabled = true): static
    {
        $this->disabledPermalinkField = $disabled;

        return $this;
    }

    public function isDisabledPermalinkField(): bool
    {
        return $this->disabledPermalinkField;
    }
}

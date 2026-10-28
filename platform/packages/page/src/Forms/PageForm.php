<?php

namespace Kern\Page\Forms;

use Kern\Base\Forms\FieldOptions\ContentFieldOption;
use Kern\Base\Forms\FieldOptions\DescriptionFieldOption;
use Kern\Base\Forms\FieldOptions\MediaImageFieldOption;
use Kern\Base\Forms\FieldOptions\NameFieldOption;
use Kern\Base\Forms\FieldOptions\SelectFieldOption;
use Kern\Base\Forms\FieldOptions\StatusFieldOption;
use Kern\Base\Forms\Fields\EditorField;
use Kern\Base\Forms\Fields\MediaImageField;
use Kern\Base\Forms\Fields\SelectField;
use Kern\Base\Forms\Fields\TextareaField;
use Kern\Base\Forms\Fields\TextField;
use Kern\Base\Forms\FormAbstract;
use Kern\Page\Http\Requests\PageRequest;
use Kern\Page\Models\Page;
use Kern\Page\Supports\Template;

class PageForm extends FormAbstract
{
    public function setup(): void
    {
        $this
            ->model(Page::class)
            ->setValidatorClass(PageRequest::class)
            ->add('name', TextField::class, NameFieldOption::make()->maxLength(120)->required())
            ->add('description', TextareaField::class, DescriptionFieldOption::make())
            ->add('content', EditorField::class, ContentFieldOption::make()->allowedShortcodes())
            ->add('status', SelectField::class, StatusFieldOption::make())
            ->when(Template::getPageTemplates(), function (PageForm $form, array $templates) {
                return $form
                    ->add(
                        'template',
                        SelectField::class,
                        SelectFieldOption::make()
                            ->label(trans('core/base::forms.template'))
                            ->required()
                            ->choices($templates)
                    );
            })
            ->add('image', MediaImageField::class, MediaImageFieldOption::make())
            ->setBreakFieldPoint('status');
    }
}

<?php

namespace Kern\Blog\Forms;

use Kern\Base\Forms\FieldOptions\DescriptionFieldOption;
use Kern\Base\Forms\FieldOptions\NameFieldOption;
use Kern\Base\Forms\FieldOptions\StatusFieldOption;
use Kern\Base\Forms\Fields\SelectField;
use Kern\Base\Forms\Fields\TextareaField;
use Kern\Base\Forms\Fields\TextField;
use Kern\Base\Forms\FormAbstract;
use Kern\Blog\Http\Requests\TagRequest;
use Kern\Blog\Models\Tag;

class TagForm extends FormAbstract
{
    public function setup(): void
    {
        $this
            ->model(Tag::class)
            ->setValidatorClass(TagRequest::class)
            ->add('name', TextField::class, NameFieldOption::make()->required()->maxLength(120))
            ->add('description', TextareaField::class, DescriptionFieldOption::make())
            ->add('status', SelectField::class, StatusFieldOption::make())
            ->setBreakFieldPoint('status');
    }
}

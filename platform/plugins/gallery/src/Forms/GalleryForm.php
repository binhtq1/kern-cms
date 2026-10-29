<?php

namespace Kern\Gallery\Forms;

use Kern\Base\Forms\FieldOptions\DescriptionFieldOption;
use Kern\Base\Forms\FieldOptions\IsFeaturedFieldOption;
use Kern\Base\Forms\FieldOptions\MediaImageFieldOption;
use Kern\Base\Forms\FieldOptions\NameFieldOption;
use Kern\Base\Forms\FieldOptions\SortOrderFieldOption;
use Kern\Base\Forms\FieldOptions\StatusFieldOption;
use Kern\Base\Forms\Fields\EditorField;
use Kern\Base\Forms\Fields\MediaImageField;
use Kern\Base\Forms\Fields\NumberField;
use Kern\Base\Forms\Fields\OnOffField;
use Kern\Base\Forms\Fields\SelectField;
use Kern\Base\Forms\Fields\TextField;
use Kern\Base\Forms\FormAbstract;
use Kern\Gallery\Http\Requests\GalleryRequest;
use Kern\Gallery\Models\Gallery;

class GalleryForm extends FormAbstract
{
    public function setup(): void
    {
        $this
            ->model(Gallery::class)
            ->setValidatorClass(GalleryRequest::class)
            ->add('name', TextField::class, NameFieldOption::make()->required())
            ->add(
                'description',
                EditorField::class,
                DescriptionFieldOption::make()
                    ->required()
            )
            ->add('order', NumberField::class, SortOrderFieldOption::make())
            ->add(
                'is_featured',
                OnOffField::class,
                IsFeaturedFieldOption::make()
            )
            ->add('status', SelectField::class, StatusFieldOption::make())
            ->add('image', MediaImageField::class, MediaImageFieldOption::make())
            ->setBreakFieldPoint('status');
    }
}

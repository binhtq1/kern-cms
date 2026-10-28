<?php

namespace Kern\Block\Forms;

use Kern\Base\Forms\FieldOptions\CodeEditorFieldOption;
use Kern\Base\Forms\FieldOptions\ContentFieldOption;
use Kern\Base\Forms\FieldOptions\DescriptionFieldOption;
use Kern\Base\Forms\FieldOptions\NameFieldOption;
use Kern\Base\Forms\FieldOptions\StatusFieldOption;
use Kern\Base\Forms\FieldOptions\TextFieldOption;
use Kern\Base\Forms\Fields\CodeEditorField;
use Kern\Base\Forms\Fields\EditorField;
use Kern\Base\Forms\Fields\SelectField;
use Kern\Base\Forms\Fields\TextareaField;
use Kern\Base\Forms\Fields\TextField;
use Kern\Base\Forms\FormAbstract;
use Kern\Block\Http\Requests\BlockRequest;
use Kern\Block\Models\Block;

class BlockForm extends FormAbstract
{
    public function setup(): void
    {
        $this
            ->model(Block::class)
            ->setValidatorClass(BlockRequest::class)
            ->add('name', TextField::class, NameFieldOption::make()->required())
            ->add(
                'alias',
                TextField::class,
                TextFieldOption::make()
                    ->label(trans('core/base::forms.alias'))
                    ->placeholder(trans('core/base::forms.alias_placeholder'))
                    ->required()
                    ->maxLength(120)
            )
            ->add('description', TextareaField::class, DescriptionFieldOption::make())
            ->add(
                'content',
                EditorField::class,
                ContentFieldOption::make()
                    ->helperText(trans('plugins/block::block.content_helper_text'))
            )
            ->add(
                'raw_content',
                CodeEditorField::class,
                CodeEditorFieldOption::make()
                    ->label(trans('plugins/block::block.raw_content'))
                    ->placeholder(trans('plugins/block::block.raw_content_placeholder'))
                    ->helperText(trans('plugins/block::block.raw_content_helper_text'))
                    ->mode('html')
                    ->rows(4)
            )
            ->add('status', SelectField::class, StatusFieldOption::make())
            ->setBreakFieldPoint('status');
    }
}

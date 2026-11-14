<?php

namespace Kern\Member\Forms;

use Kern\Base\Forms\FieldOptions\DatePickerFieldOption;
use Kern\Base\Forms\FieldOptions\DescriptionFieldOption;
use Kern\Base\Forms\FieldOptions\EmailFieldOption;
use Kern\Base\Forms\FieldOptions\MediaImageFieldOption;
use Kern\Base\Forms\FieldOptions\OnOffFieldOption;
use Kern\Base\Forms\FieldOptions\StatusFieldOption;
use Kern\Base\Forms\FieldOptions\TextFieldOption;
use Kern\Base\Forms\Fields\DatePickerField;
use Kern\Base\Forms\Fields\MediaImageField;
use Kern\Base\Forms\Fields\OnOffField;
use Kern\Base\Forms\Fields\SelectField;
use Kern\Base\Forms\Fields\TextareaField;
use Kern\Base\Forms\Fields\TextField;
use Kern\Base\Forms\FormAbstract;
use Kern\Member\Http\Requests\MemberCreateRequest;
use Kern\Member\Models\Member;

class MemberForm extends FormAbstract
{
    public function setup(): void
    {
        $this
            ->model(Member::class)
            ->setValidatorClass(MemberCreateRequest::class)
            ->columns()
            ->add(
                'first_name',
                TextField::class,
                TextFieldOption::make()
                    ->label(trans('plugins/member::member.first_name'))
                    ->required()
                    ->maxLength(120)
                    ->colspan(1)
            )
            ->add(
                'last_name',
                TextField::class,
                TextFieldOption::make()
                    ->label(trans('plugins/member::member.last_name'))
                    ->required()
                    ->maxLength(120)
                    ->colspan(1)
            )
            ->add(
                'email',
                TextField::class,
                EmailFieldOption::make()->required()->colspan(1)
            )
            ->add(
                'phone',
                TextField::class,
                TextFieldOption::make()
                    ->label(trans('plugins/member::member.phone'))
                    ->placeholder(trans('plugins/member::member.phone_placeholder'))
                    ->maxLength(15)
                    ->colspan(1)
            )
            ->add(
                'dob',
                DatePickerField::class,
                DatePickerFieldOption::make()
                    ->label(trans('plugins/member::member.dob'))
                    ->colspan(2)
            )
            ->add(
                'description',
                TextareaField::class,
                DescriptionFieldOption::make()->colspan(2)
            )
            ->add(
                'is_change_password',
                OnOffField::class,
                OnOffFieldOption::make()
                    ->label(trans('plugins/member::member.form.change_password'))
                    ->defaultValue(0)
                    ->colspan(2)
            )
            ->add(
                'password',
                'password',
                TextFieldOption::make()
                    ->label(trans('plugins/member::member.form.password'))
                    ->collapsible('is_change_password', 1, ! $this->getModel()->exists || $this->getModel()->is_change_password)
                    ->required()
                    ->maxLength(60)
                    ->colspan(1)
            )
            ->add(
                'password_confirmation',
                'password',
                TextFieldOption::make()
                    ->label(trans('plugins/member::member.form.password_confirmation'))
                    ->collapsible('is_change_password', 1, ! $this->getModel()->exists || $this->getModel()->is_change_password)
                    ->required()
                    ->maxLength(60)
                    ->colspan(1)
            )
            ->add('status', SelectField::class, StatusFieldOption::make())
            ->add(
                'avatar_image',
                MediaImageField::class,
                MediaImageFieldOption::make()->value($this->getModel()->avatar->url)
            )
            ->setBreakFieldPoint('status');
    }
}

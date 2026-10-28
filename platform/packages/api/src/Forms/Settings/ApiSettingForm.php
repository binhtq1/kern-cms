<?php

namespace Kern\Api\Forms\Settings;

use Kern\Api\Facades\ApiHelper;
use Kern\Api\Http\Requests\ApiSettingRequest;
use Kern\Base\Forms\FieldOptions\OnOffFieldOption;
use Kern\Base\Forms\Fields\OnOffCheckboxField;
use Kern\Setting\Forms\SettingForm;

class ApiSettingForm extends SettingForm
{
    public function setup(): void
    {
        parent::setup();

        $this
            ->setValidatorClass(ApiSettingRequest::class)
            ->setSectionTitle(trans('packages/api::api.setting_title'))
            ->setSectionDescription(trans('packages/api::api.setting_description'))
            ->contentOnly()
            ->add(
                'api_enabled',
                OnOffCheckboxField::class,
                OnOffFieldOption::make()
                    ->label(trans('packages/api::api.api_enabled'))
                    ->value(ApiHelper::enabled())
                    ->toArray()
            );
    }
}

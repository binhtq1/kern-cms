<?php

namespace Kern\Setting\Forms;

use DateTimeZone;
use Kern\Base\Forms\FieldOptions\HtmlFieldOption;
use Kern\Base\Forms\FieldOptions\SelectFieldOption;
use Kern\Base\Forms\Fields\HtmlField;
use Kern\Base\Forms\Fields\SelectField;
use Kern\Setting\Http\Requests\GeneralSettingRequest;

class GeneralSettingForm extends SettingForm
{
    public function setup(): void
    {
        parent::setup();

        $this
            ->setSectionTitle(trans('core/setting::setting.general.title'))
            ->setSectionDescription(trans('core/setting::setting.general.description'))
            ->contentOnly()
            ->setValidatorClass(GeneralSettingRequest::class)
            ->add(
                'admin_email',
                HtmlField::class,
                HtmlFieldOption::make()->view('core/setting::partials.admin-email-field')
            )
            ->add(
                'time_zone',
                SelectField::class,
                SelectFieldOption::make()
                    ->label(trans('core/setting::setting.general.time_zone'))
                    ->choices(array_combine(DateTimeZone::listIdentifiers(), DateTimeZone::listIdentifiers()))
                    ->selected(setting('time_zone', 'UTC'))
                    ->searchable()
            )
            ->add('enable_send_error_reporting_via_email', 'onOffCheckbox', [
                'label' => trans('core/setting::setting.general.enable_send_error_reporting_via_email'),
                'value' => setting('enable_send_error_reporting_via_email'),
            ])
            ->when(
                apply_filters(BASE_FILTER_AFTER_SETTING_CONTENT, null),
                function (GeneralSettingForm $form, $settingContent): void {
                    $form
                        ->add(
                            'html',
                            HtmlField::class,
                            HtmlFieldOption::make()
                                ->content('</div></div><div class="card mt-3 overflow-hidden"><div class="card-body">'.$settingContent)
                        );
                }
            );
    }
}

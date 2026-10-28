<?php

namespace Kern\Setting\Forms;

use Kern\Base\Forms\FieldOptions\HtmlFieldOption;
use Kern\Base\Forms\FieldOptions\SelectFieldOption;
use Kern\Base\Forms\FieldOptions\TextFieldOption;
use Kern\Base\Forms\Fields\HtmlField;
use Kern\Base\Forms\Fields\SelectField;
use Kern\Base\Forms\Fields\TextField;
use Kern\Setting\Http\Requests\EmailSettingRequest;

class EmailSettingForm extends SettingForm
{
    public function setup(): void
    {
        parent::setup();

        $mailer = old('email_driver', setting('email_driver', config('mail.default')));

        $this
            ->setUrl(route('settings.email.update'))
            ->setSectionTitle(trans('core/setting::setting.panel.email'))
            ->setSectionDescription(trans('core/setting::setting.panel.email_description'))
            ->contentOnly()
            ->setActionButtons(view('core/setting::partials.email.action-buttons', ['form' => $this->getFormOption('id')])->render())
            ->setValidatorClass(EmailSettingRequest::class)
            ->add(
                'email_driver',
                SelectField::class,
                SelectFieldOption::make()
                    ->label(trans('core/setting::setting.email.mailer'))
                    ->choices(apply_filters('core_email_mailers', [
                        'smtp' => 'SMTP',
                        'mailgun' => 'Mailgun',
                        'ses' => 'SES',
                        'postmark' => 'Postmark',
                        'log' => 'Log',
                        'array' => 'Array',
                    ] + (function_exists('proc_open') ? ['sendmail' => 'Sendmail'] : [])))
                    ->selected($mailer)
                    ->addAttribute('data-bb-toggle', 'collapse')
                    ->addAttribute('data-bb-target', '.email-fields')
            )
            ->add(
                'mailer',
                HtmlField::class,
                HtmlFieldOption::make()->view('core/setting::partials.email.email-fields', compact('mailer')),
            )
            ->add(
                'email_from_name',
                TextField::class,
                TextFieldOption::make()
                    ->label(trans('core/setting::setting.email.sender_name'))
                    ->value(old('email_from_name', setting('email_from_name', config('mail.from.name'))))
                    ->placeholder(trans('core/setting::setting.email.sender_name_placeholder'))
                    ->maxLength(60)
            )
            ->add(
                'email_from_address',
                TextField::class,
                TextFieldOption::make()
                    ->label(trans('core/setting::setting.email.sender_email'))
                    ->value(old('email_from_address', setting('email_from_address', get_admin_email()->first())))
                    ->placeholder('admin@example.com')
                    ->maxLength(60)
                    ->wrapperAttributes([
                        'class' => 'mb-0',
                    ])
            );
    }
}

<?php

namespace Kern\ACL\Forms\Auth;

use Kern\ACL\Http\Requests\LoginRequest;
use Kern\ACL\Models\User;
use Kern\Base\Facades\BaseHelper;
use Kern\Base\Forms\FieldOptions\CheckboxFieldOption;
use Kern\Base\Forms\FieldOptions\HtmlFieldOption;
use Kern\Base\Forms\FieldOptions\TextFieldOption;
use Kern\Base\Forms\Fields\CheckboxField;
use Kern\Base\Forms\Fields\HtmlField;
use Kern\Base\Forms\Fields\TextField;

class LoginForm extends AuthForm
{
    public function setup(): void
    {
        parent::setup();

        $this
            ->setValidatorClass(LoginRequest::class)
            ->setUrl(route('access.login'))
            ->heading(trans('core/acl::auth.sign_in_below'))
            ->add(
                'username',
                TextField::class,
                TextFieldOption::make()
                    ->label(trans('core/acl::auth.login.username'))
                    ->value(old(
                        'email',
                        BaseHelper::hasDemoModeEnabled() ? config('core.base.general.demo.account.username') : null,
                    ))
                    ->required()
                    ->attributes(['tabindex' => 1, 'placeholder' => trans('core/acl::auth.login.placeholder.username')])
            )
            ->add(
                'password',
                'password',
                TextFieldOption::make()
                    ->label(trans('core/acl::auth.login.password'))
                    ->required()
                    ->attributes(['tabindex' => 2, 'autocomplete' => 'current-password'])
            )
            ->add(
                'remember',
                CheckboxField::class,
                CheckboxFieldOption::make()
                    ->label(trans('core/acl::auth.login.remember'))
                    ->value(true)
                    ->attributes(['tabindex' => 3])
            )
            ->submitButton(trans('core/acl::auth.login.login'), 'ti ti-login-2')
            ->add(
                'filters',
                HtmlField::class,
                HtmlFieldOption::make()
                    ->content(apply_filters(BASE_FILTER_AFTER_LOGIN_OR_REGISTER_FORM, null, User::class))
            );
    }
}

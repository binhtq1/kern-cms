<?php

namespace Kern\ACL\Supports;

use Inertia\Inertia;
use Inertia\Response;
use Kern\ACL\Forms\Auth\ForgotPasswordForm;
use Kern\ACL\Forms\Auth\LoginForm;
use Kern\ACL\Forms\Auth\ResetPasswordForm;
use Kern\Base\Facades\BaseHelper;
use Kern\Media\Facades\RvMedia;

class AdminAuthData
{
    public function fallbackReason(): ?string
    {
        $mode = $this->mode();
        $class = match ($mode) {
            'forgot' => ForgotPasswordForm::class,
            'reset' => ResetPasswordForm::class,
            default => LoginForm::class,
        };
        $form = $class::create();
        $form->prepareForSchema();
        if ($form->hasSchemaRenderingOutput() || $form->getMetaBoxes()) {
            return 'Blade is required by authentication rendering extensions.';
        }
        $heading = match ($mode) {
            'forgot' => trans('core/acl::auth.forgot_password.title'),
            'reset' => trans('core/acl::auth.reset_password'),
            default => trans('core/acl::auth.sign_in_below'),
        };
        $html = [
            'heading' => sprintf('<h2 class="h3 text-center mb-3">%s</h2>', $heading),
        ];
        $fields = match ($mode) {
            'forgot' => ['email' => 'TextField'],
            'reset' => ['token' => 'InputType', 'email' => 'InputType', 'password' => 'InputType', 'password_confirmation' => 'InputType'],
            default => ['username' => 'TextField', 'password' => 'InputType', 'remember' => 'CheckboxField'],
        };
        $fields['submit'] = 'ButtonType';
        if ($mode === 'login') {
            $html['filters'] = '';
        } elseif ($mode === 'forgot') {
            $html['description'] = BaseHelper::clean(trans('core/acl::auth.forgot_password.message'));
            $html['back_to_login'] = sprintf('<div class="mt-3 text-center"><a href="%s">%s</a></div>', route('access.login'), trans('core/acl::auth.back_to_login'));
        }
        foreach ($html as $name => $content) {
            $fields[$name] = 'HtmlField';
        }
        if (array_diff(array_keys($fields), array_keys($form->getFields()))) {
            return 'Blade is required by authentication form extensions.';
        }
        foreach ($form->getFields() as $name => $field) {
            if (($fields[$name] ?? null) !== class_basename($field)) {
                return 'Blade is required by authentication form field: '.$name.'.';
            }
            if (array_key_exists($name, $html)) {
                $options = $field->getOptions();
                if (trim((string) ($options['html'] ?? $options['content'] ?? '')) !== trim($html[$name])) {
                    return 'Blade is required by authentication HTML field: '.$name.'.';
                }
            }
        }

        return null;
    }

    public function render(): Response
    {
        $mode = $this->mode();
        $key = match ($mode) {
            'forgot' => 'forgot_password.title',
            'reset' => 'reset.title',
            default => 'sign_in_below',
        };

        return Inertia::render('Auth/Form', [
            'mode' => $mode,
            'title' => trans('core/acl::auth.'.$key),
            'description' => match ($mode) {
                'forgot' => trans('core/base::admin.Enter your account email and we will send you a password reset link.'),
                'reset' => trans('core/base::admin.Choose a new password to get back into your account.'),
                default => '',
            },
            'logo' => setting('admin_logo') ? RvMedia::getImageUrl(setting('admin_logo')) : (config('core.base.general.logo') ? url(config('core.base.general.logo')) : null),
            'submit' => route(match ($mode) {
                'forgot' => 'access.password.email', 'reset' => 'access.password.reset.post', default => 'access.login.post',
            }),
            'links' => ['login' => route('access.login'), 'forgot' => route('access.password.request')],
            'credentials' => [
                'username' => $mode === 'login' && BaseHelper::hasDemoModeEnabled() ? (string) config('core.base.general.demo.account.username', '') : '',
                'password' => $mode === 'login' && BaseHelper::hasDemoModeEnabled() ? (string) config('core.base.general.demo.account.password', '') : '',
            ],
            'email' => $mode === 'reset' ? (string) request()->query('email', '') : '',
            'token' => $mode === 'reset' ? (string) request()->route('token') : '',
            'labels' => [
                'username' => trans('core/acl::auth.login.username'),
                'email' => trans('core/acl::auth.login.email'),
                'password' => trans('core/acl::auth.'.($mode === 'reset' ? 'reset.new_password' : 'login.password')),
                'confirmation' => trans('core/acl::auth.reset.password_confirmation'),
                'password_placeholder' => trans('core/acl::auth.reset.placeholder.new_password'),
                'confirmation_placeholder' => trans('core/acl::auth.reset.placeholder.new_password_confirmation'),
                'remember' => trans('core/acl::auth.login.remember'),
                'forgot' => trans('core/acl::auth.lost_your_password'),
                'back' => trans('core/acl::auth.back_to_login'),
                'submit' => match ($mode) {
                    'forgot' => trans('core/base::admin.Send reset link'),
                    'reset' => trans('core/base::admin.Reset password'),
                    default => trans('core/acl::auth.login.login'),
                },
            ],
        ]);
    }

    private function mode(): string
    {
        return match (true) {
            request()->routeIs('access.password.request', 'access.password.email') => 'forgot',
            request()->routeIs('access.password.reset', 'access.password.reset.post') => 'reset',
            default => 'login',
        };
    }
}

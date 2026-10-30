<?php

namespace Kern\Captcha\Providers;

use Illuminate\Foundation\AliasLoader;
use Illuminate\Http\Request as IlluminateRequest;
use Illuminate\Routing\Events\Routing;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Validator;
use Kern\ACL\Forms\Auth\ForgotPasswordForm;
use Kern\ACL\Forms\Auth\LoginForm;
use Kern\ACL\Forms\Auth\ResetPasswordForm;
use Kern\ACL\Http\Requests\ForgotPasswordRequest;
use Kern\ACL\Http\Requests\LoginRequest;
use Kern\ACL\Http\Requests\ResetPasswordRequest;
use Kern\Base\Facades\PanelSectionManager;
use Kern\Base\Forms\FormAbstract;
use Kern\Base\PanelSections\PanelSectionItem;
use Kern\Base\Supports\ServiceProvider;
use Kern\Base\Traits\LoadAndPublishDataTrait;
use Kern\Captcha\Captcha;
use Kern\Captcha\CaptchaV3;
use Kern\Captcha\Facades\Captcha as CaptchaFacade;
use Kern\Captcha\Forms\Fields\MathCaptchaField;
use Kern\Captcha\Forms\Fields\ReCaptchaField;
use Kern\Captcha\MathCaptcha;
use Kern\Setting\PanelSections\SettingOthersPanelSection;
use Kern\Support\Http\Requests\Request;
use Kern\Theme\FormFront;

class CaptchaServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    protected bool $defer = false;

    public function register(): void
    {
        $this->app->singleton('captcha', function () {
            if (setting('captcha_type') === 'v3') {
                return new CaptchaV3(setting('captcha_site_key'), setting('captcha_secret'));
            }

            return new Captcha(setting('captcha_site_key'), setting('captcha_secret'));
        });

        $this->app->singleton('math-captcha', function ($app) {
            return new MathCaptcha($app['session']);
        });

        AliasLoader::getInstance()->alias('Captcha', CaptchaFacade::class);
    }

    public function boot(): void
    {
        $this
            ->setNamespace('plugins/captcha')
            ->loadAndPublishConfigurations(['general', 'permissions'])
            ->loadRoutes()
            ->loadAndPublishViews()
            ->loadAndPublishTranslations();

        $this->bootValidator();

        PanelSectionManager::default()->beforeRendering(function (): void {
            PanelSectionManager::registerItem(
                SettingOthersPanelSection::class,
                fn () => PanelSectionItem::make('captcha')
                    ->setTitle(trans('plugins/captcha::captcha.settings.title'))
                    ->withIcon('ti ti-refresh')
                    ->withPriority(150)
                    ->withDescription(trans('plugins/captcha::captcha.settings.panel_description'))
                    ->withRoute('captcha.settings')
            );
        });

        CaptchaFacade::registerFormSupport(LoginForm::class, LoginRequest::class, trans('plugins/captcha::captcha.admin_login_form'));
        CaptchaFacade::registerFormSupport(ForgotPasswordForm::class, ForgotPasswordRequest::class, trans('plugins/captcha::captcha.admin_forgot_password_form'));
        CaptchaFacade::registerFormSupport(ResetPasswordForm::class, ResetPasswordRequest::class, trans('plugins/captcha::captcha.admin_reset_password_form'));

        FormAbstract::beforeRendering(function (FormAbstract $form): void {
            if (! CaptchaFacade::isEnabled() && ! CaptchaFacade::mathCaptchaEnabled()) {
                return;
            }

            $fieldKey = 'submit';

            $attributes = [
                'colspan' => $form->getColumns('lg'),
            ];

            if ($form instanceof FormFront) {
                $fieldKey = $form->getFormEndKey() ?: ($form->has($fieldKey) ? $fieldKey : array_key_last($form->getFields()));

                if ($form->getFormInputWrapperClass()) {
                    $attributes['wrapper'] = ['class' => $form->getFormInputWrapperClass()];
                }

                if ($form->getFormLabelClass()) {
                    $attributes['label_attr'] = ['class' => $form->getFormLabelClass()];
                }

                if ($form->getFormInputClass()) {
                    $attributes['attr'] = ['class' => $form->getFormInputClass()];
                }
            }

            if (CaptchaFacade::reCaptchaEnabled() && ! $form->has('recaptcha') && CaptchaFacade::formSetting($form::class, 'enable_recaptcha')) {
                $form->addBefore(
                    $fieldKey,
                    'recaptcha',
                    ReCaptchaField::class,
                    $attributes
                );
            }

            if (CaptchaFacade::mathCaptchaEnabled() && ! $form->has('math_captcha') && CaptchaFacade::formSetting($form::class, 'enable_math_captcha')) {
                $form->addBefore(
                    $fieldKey,
                    'math_captcha',
                    MathCaptchaField::class,
                    $attributes
                );
            }
        });

        Event::listen(Routing::class, function (): void {
            add_filter('core_request_rules', function (array $rules, Request $request) {
                if (! CaptchaFacade::isEnabled() && ! CaptchaFacade::mathCaptchaEnabled()) {
                    return $rules;
                }

                CaptchaFacade::getFormsSupport();

                $form = CaptchaFacade::formByRequest($request::class);

                if (! $form) {
                    return $rules;
                }

                if (CaptchaFacade::reCaptchaEnabled() && CaptchaFacade::formSetting($form, 'enable_recaptcha')) {
                    $rules = [...$rules, ...CaptchaFacade::rules()];
                }

                if (CaptchaFacade::mathCaptchaEnabled() && CaptchaFacade::formSetting($form, 'enable_math_captcha')) {
                    $rules = [...$rules, ...CaptchaFacade::mathCaptchaRules()];
                }

                return $rules;
            }, 128, 2);
        });

        add_filter('form_extra_fields_render', function (?string $fields = null, ?string $form = null): ?string {
            if (! CaptchaFacade::isEnabled() && ! CaptchaFacade::mathCaptchaEnabled()) {
                return $fields;
            }

            return $fields.view('plugins/captcha::forms.old-version-support', compact('form'))->render();
        }, 128, 2);

        add_action('form_extra_fields_validate', function (IlluminateRequest $request, ?string $form = null): void {
            if (! CaptchaFacade::isEnabled() && ! CaptchaFacade::mathCaptchaEnabled()) {
                return;
            }

            if (
                CaptchaFacade::reCaptchaEnabled()
                && (! $form || ! class_exists($form) || CaptchaFacade::formSetting($form, 'enable_recaptcha'))
                && ! $request instanceof Request
            ) {
                Validator::validate($request->input(), CaptchaFacade::rules());
            }

            if (CaptchaFacade::mathCaptchaEnabled() && (! $form || ! class_exists($form) || CaptchaFacade::formSetting($form, 'enable_math_captcha'))) {
                Validator::validate($request->input(), CaptchaFacade::mathCaptchaRules());
            }
        }, 999, 2);
    }

    public function bootValidator(): void
    {
        $app = $this->app;

        /**
         * @var Validator $validator
         */
        $validator = $app['validator'];
        $validator->extend('captcha', function ($attribute, $value, $parameters) use ($app) {
            if (! $app['captcha']->reCaptchaEnabled()) {
                return true;
            }

            if (! is_string($value)) {
                return false;
            }

            if (setting('captcha_type') === 'v3') {
                if (empty($parameters)) {
                    $parameters = ['form', (float) setting('recaptcha_score', 0.6)];
                }
            } else {
                $parameters = $this->mapParameterToOptions($parameters);
            }

            return $app['captcha']->verify($value, $this->app['request']->getClientIp(), $parameters);
        }, __('Captcha Verification Failed!'));

        $validator->extend('math_captcha', function ($attribute, $value) {
            if (! is_string($value)) {
                return false;
            }

            return $this->app['math-captcha']->verify($value);
        }, __('Math Captcha Verification Failed!'));
    }

    public function mapParameterToOptions(array $parameters = []): array
    {
        if (! is_array($parameters)) {
            return [];
        }

        $options = [];

        foreach ($parameters as $parameter) {
            $option = explode(':', $parameter);
            if (count($option) === 2) {
                Arr::set($options, $option[0], $option[1]);
            }
        }

        return $options;
    }

    public function provides(): array
    {
        return ['captcha', 'math-captcha'];
    }
}

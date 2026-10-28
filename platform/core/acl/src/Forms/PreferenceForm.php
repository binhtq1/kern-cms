<?php

namespace Kern\ACL\Forms;

use Kern\ACL\Http\Requests\PreferenceRequest;
use Kern\ACL\Models\User;
use Kern\Base\Facades\AdminAppearance;
use Kern\Base\Forms\FieldOptions\RadioFieldOption;
use Kern\Base\Forms\FieldOptions\SelectFieldOption;
use Kern\Base\Forms\Fields\RadioField;
use Kern\Base\Forms\Fields\SelectField;
use Kern\Base\Forms\FormAbstract;
use Kern\Base\Supports\Language;

class PreferenceForm extends FormAbstract
{
    public function setup(): void
    {
        $languages = collect(Language::getAvailableLocales())
            ->pluck('name', 'locale')
            ->map(fn ($item, $key) => $item.' - '.$key)
            ->all();

        /**
         * @var User $user
         */
        $user = $this->getModel();

        $adminAppearance = AdminAppearance::forUser($user);

        $this
            ->template('core/base::forms.form-no-wrap')
            ->setValidatorClass(PreferenceRequest::class)
            ->setMethod('PUT')
            ->when(count($languages) > 1, function (FormAbstract $form) use ($adminAppearance, $languages): void {
                $form->add(
                    'locale',
                    SelectField::class,
                    SelectFieldOption::make()
                        ->label(trans('core/setting::setting.admin_appearance.language'))
                        ->choices($languages)
                        ->selected($adminAppearance->getLocale())
                );
            })
            ->add(
                'locale_direction',
                RadioField::class,
                RadioFieldOption::make()
                    ->label(trans('core/setting::setting.admin_appearance.form.admin_locale_direction'))
                    ->choices([
                        'ltr' => trans('core/setting::setting.locale_direction_ltr'),
                        'rtl' => trans('core/setting::setting.locale_direction_rtl'),
                    ])
                    ->selected($adminAppearance->getLocaleDirection())
            )
            ->add(
                'theme_mode',
                RadioField::class,
                RadioFieldOption::make()
                    ->label(trans('core/setting::setting.admin_appearance.theme_mode'))
                    ->choices([
                        'light' => trans('core/setting::setting.admin_appearance.light'),
                        'dark' => trans('core/setting::setting.admin_appearance.dark'),
                    ])
                    ->selected($user->getMeta('theme_mode', 'light'))
            )
            ->setActionButtons(view('core/acl::users.profile.actions')->render());
    }
}

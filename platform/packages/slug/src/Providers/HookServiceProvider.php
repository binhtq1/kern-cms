<?php

namespace Kern\Slug\Providers;

use Kern\Base\Contracts\BaseModel;
use Kern\Base\Forms\FormAbstract;
use Kern\Base\Supports\ServiceProvider;
use Kern\Slug\Facades\SlugHelper;
use Kern\Slug\Forms\Fields\PermalinkField;
use Kern\Theme\Facades\Theme;
use Kern\Theme\FormFront;

class HookServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        add_filter(BASE_FILTER_BEFORE_RENDER_FORM, [$this, 'addSlugBox'], 1712);

        add_filter('core_slug_language', [$this, 'setSlugLanguageForGenerator'], 17);
    }

    public function addSlugBox(FormAbstract $form): FormAbstract
    {
        if ($form->isDisabledPermalinkField()) {
            return $form;
        }

        if ($form instanceof FormFront) {
            Theme::asset()->container('footer')->usePath(false)->add('slug-js', 'vendor/core/packages/slug/js/front-slug.js', ['jquery']);
            Theme::asset()->usePath(false)->add('slug-css', 'vendor/core/packages/slug/css/slug.css');
        }

        $model = $form->getModel();

        if (! $model instanceof BaseModel || ! SlugHelper::isSupportedModel($model::class)) {
            return $form;
        }

        if (array_key_exists('slug', $form->getFields())) {
            return $form;
        }

        return $form
            ->addAfter(SlugHelper::getColumnNameToGenerateSlug($model), 'slug', PermalinkField::class, [
                'model' => $model,
                'colspan' => 'full',
            ]);
    }

    public function setSlugLanguageForGenerator(): bool|string
    {
        return SlugHelper::turnOffAutomaticUrlTranslationIntoLatin() ? false : 'en';
    }
}

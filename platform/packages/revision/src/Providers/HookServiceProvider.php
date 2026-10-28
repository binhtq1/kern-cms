<?php

namespace Kern\Revision\Providers;

use Illuminate\Database\Eloquent\Model;
use Kern\Base\Facades\Assets;
use Kern\Base\Forms\FormAbstract;
use Kern\Base\Forms\FormTab;
use Kern\Base\Models\BaseModel;
use Kern\Base\Supports\ServiceProvider;

class HookServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        FormAbstract::extend(function (FormAbstract $form): void {
            $model = $form->getModel();

            if (
                ! $model instanceof BaseModel
                || ! $model->exists
                || ! $this->isSupported($model)
            ) {
                return;
            }

            Assets::addStylesDirectly('vendor/core/packages/revision/css/revision.css')
                ->addScriptsDirectly([
                    'vendor/core/packages/revision/js/html-diff.js',
                    'vendor/core/packages/revision/js/revision.js',
                ]);

            $form->addTab(
                FormTab::make()
                    ->id('revisions')
                    ->label(trans('core/base::tabs.revision'))
                    ->content(view('packages/revision::history-content', compact('model')))
            );
        }, 999);
    }

    protected function isSupported(string|Model $model): bool
    {
        if (is_object($model)) {
            $model = $model::class;
        }

        return in_array($model, config('packages.revision.general.supported', []));
    }
}

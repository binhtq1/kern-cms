<?php

namespace Kern\Translation\Http\Controllers;

use Illuminate\Http\Request;
use Kern\Setting\Http\Controllers\SettingController;
use Kern\Translation\Http\Controllers\Concerns\HasMapTranslationsTable;
use Kern\Translation\Http\Requests\TranslationRequest;
use Kern\Translation\Manager;
use Kern\Translation\Supports\AdminTranslationData;
use Kern\Translation\Tables\TranslationTable;

class TranslationController extends SettingController
{
    use HasMapTranslationsTable;

    public function __construct(protected Manager $manager) {}

    public function index(Request $request, TranslationTable $translationTable)
    {
        return app(AdminTranslationData::class)->index(false);
    }

    public function update(TranslationRequest $request)
    {
        app(AdminTranslationData::class)->validateUpdate($request, false);

        $group = $request->input('group');

        $name = $request->input('name');
        $value = $request->input('value');

        [$locale, $key] = explode('|', $name, 2);

        $this->manager->updateTranslation($locale, $group, $key, $value);

        return $this->httpResponse()->withUpdatedSuccessMessage();
    }
}

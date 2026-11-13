<?php

namespace Kern\Translation\Exporters;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Kern\Base\Supports\Language;
use Kern\DataSynchronize\Exporter\ExportColumn;
use Kern\DataSynchronize\Exporter\Exporter;
use Kern\Translation\Services\GetGroupedTranslationsService;

class OtherTranslationExporter extends Exporter
{
    public function getLabel(): string
    {
        return trans('plugins/translation::translation.panel.admin-translations.title');
    }

    public function columns(): array
    {
        $columns = [
            ExportColumn::make('key')->disabled(),
        ];

        foreach (Language::getAvailableLocales() as $locale) {
            $columns[] = ExportColumn::make($locale['locale'])->label($locale['locale'])->disabled();
        }

        return $columns;
    }

    public function collection(): Collection
    {
        $translations = (new GetGroupedTranslationsService)
            ->handle()
            ->transform(fn ($translation) => [
                'key' => sprintf('%s::%s', $translation['group'], $translation['key']),
                'en' => $translation['value'],
            ]);

        foreach (Language::getAvailableLocales() as $locale) {
            $translations->transform(function ($translation) use ($locale) {
                [$group, $key] = explode('::', $translation['key']);

                return [
                    ...$translation,
                    $locale['locale'] => trans(
                        Str::of($group)
                            ->replaceLast(DIRECTORY_SEPARATOR, '::')
                            ->append(".$key")
                            ->toString(),
                        [],
                        $locale['locale']
                    ),
                ];
            });
        }

        return $translations;
    }
}

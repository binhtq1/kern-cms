<?php

namespace Kern\Translation\Importers;

use Illuminate\Support\Facades\Auth;
use Kern\Base\Supports\Language;
use Kern\DataSynchronize\Contracts\Importer\WithMapping;
use Kern\DataSynchronize\Importer\ImportColumn;
use Kern\DataSynchronize\Importer\Importer;
use Kern\Translation\Manager;

class ThemeTranslationImporter extends Importer implements WithMapping
{
    public function headerToSnakeCase(): bool
    {
        return false;
    }

    public function chunkSize(): int
    {
        return 100;
    }

    public function getLabel(): string
    {
        return trans('plugins/translation::translation.panel.theme-translations.title');
    }

    public function columns(): array
    {
        $columns = [
            ImportColumn::make('en')
                ->rules(
                    ['required', 'string', 'max:10000'],
                    trans('validation.required', ['attribute' => 'en']).' '.trans('validation.max.string', ['attribute' => 'en', 'max' => 10000])
                ),
        ];

        foreach (Language::getAvailableLocales() as $locale) {
            if ($locale['locale'] === 'en') {
                continue;
            }

            $columns[] = ImportColumn::make($locale['locale'])
                ->rules(
                    ['nullable', 'string', 'max:10000'],
                    trans(
                        'plugins/translation::translation.import.rules.trans',
                        ['max' => 10000]
                    )
                );
        }

        return $columns;
    }

    public function getValidateUrl(): string
    {
        return route('tools.data-synchronize.import.theme-translations.validate');
    }

    public function getImportUrl(): string
    {
        return route('tools.data-synchronize.import.theme-translations.store');
    }

    public function getExportUrl(): ?string
    {
        return Auth::user()->hasPermission('theme-translations.export')
            ? route('tools.data-synchronize.export.theme-translations.store')
            : null;
    }

    public function map(mixed $row): array
    {
        return $row;
    }

    public function handle(array $data): int
    {
        $count = 0;

        $manager = app(Manager::class);

        foreach (Language::getAvailableLocales() as $locale) {
            if ($locale['locale'] === 'en') {
                continue;
            }

            $localeName = $locale['locale'];
            $localeRows = array_filter($data, fn (array $row): bool => isset($row[$localeName]));

            if ($localeRows === []) {
                continue;
            }

            $translations = $manager->getThemeTranslations($localeName);

            foreach ($localeRows as $row) {
                $translations[$row['en']] = $row[$localeName];
            }

            $manager->saveThemeTranslations($localeName, $translations);
            $count += count($localeRows);
        }

        return $count;
    }
}

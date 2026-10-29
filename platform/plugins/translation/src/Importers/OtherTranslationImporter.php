<?php

namespace Kern\Translation\Importers;

use Illuminate\Support\Facades\Auth;
use Kern\Base\Supports\Language;
use Kern\DataSynchronize\Contracts\Importer\WithMapping;
use Kern\DataSynchronize\Importer\ImportColumn;
use Kern\DataSynchronize\Importer\Importer;
use Kern\Translation\Manager;
use Kern\Translation\Services\GetGroupedTranslationsService;

class OtherTranslationImporter extends Importer implements WithMapping
{
    public function headerToSnakeCase(): bool
    {
        return false;
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function getLabel(): string
    {
        return trans('plugins/translation::translation.panel.admin-translations.title');
    }

    public function columns(): array
    {
        $columns = [
            ImportColumn::make('key')
                ->rules(['required', 'string', 'max:10000', function (string $attribute, mixed $value, \Closure $fail): void {
                    $allowed = app(GetGroupedTranslationsService::class)->handle()->map(fn ($row): string => $row['group'].'::'.$row['key']);
                    if (! $allowed->contains($value)) {
                        $fail('Unknown translation key.');
                    }
                }], trans('plugins/translation::translation.import.rules.key')),
        ];

        foreach (Language::getAvailableLocales() as $locale) {
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
        return route('tools.data-synchronize.import.other-translations.validate');
    }

    public function getImportUrl(): string
    {
        return route('tools.data-synchronize.import.other-translations.store');
    }

    public function getExportUrl(): ?string
    {
        return Auth::user()->hasPermission('other-translations.export')
            ? route('tools.data-synchronize.export.other-translations.store')
            : null;
    }

    public function map(mixed $row): array
    {
        [$group] = explode('::', $row['key'] ?? '', 2);

        return [
            ...$row,
            'group' => $group,
        ];
    }

    public function handle(array $data): int
    {
        $count = 0;

        $manager = app(Manager::class);

        $data = collect($data)->groupBy('group');

        foreach ($data as $group => $translations) {
            foreach (Language::getAvailableLocales() as $locale) {
                $localeTranslations = $translations->filter(fn (array $row): bool => array_key_exists($locale['locale'], $row))
                    ->mapWithKeys(fn (array $row): array => [explode('::', $row['key'], 2)[1] => $row[$locale['locale']]]);

                if ($localeTranslations->isEmpty()) {
                    continue;
                }

                $manager->updateTranslation(
                    $locale['locale'],
                    str_replace('/', DIRECTORY_SEPARATOR, $group),
                    $localeTranslations->all()
                );

                $count += count($localeTranslations);
            }
        }

        return $count;
    }
}

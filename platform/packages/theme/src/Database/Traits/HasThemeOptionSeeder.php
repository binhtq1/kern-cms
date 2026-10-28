<?php

namespace Kern\Theme\Database\Traits;

use Kern\Setting\Facades\Setting;
use Kern\Theme\Facades\ThemeOption;

trait HasThemeOptionSeeder
{
    protected function truncateOptions(): void
    {
        Setting::newQuery()->where('key', 'LIKE', ThemeOption::getOptionKey('%'))->delete();
    }

    protected function createThemeOptions(array $options, bool $truncate = true): void
    {
        if ($truncate) {
            $this->truncateOptions();
        }

        Setting::set(ThemeOption::prepareFromArray($options));

        Setting::save();
    }
}

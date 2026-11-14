<?php

namespace Kern\Language\Listeners;

use Kern\Base\Events\CreatedContentEvent;
use Kern\Language\Listeners\Concerns\EnsureThemePackageExists;
use Kern\Language\Models\Language;
use Kern\Widget\Models\Widget;

class CopyThemeWidgets
{
    use EnsureThemePackageExists;

    public function handle(CreatedContentEvent $event): void
    {
        if (! $this->determineIfThemesExists()) {
            return;
        }

        if (! $event->data instanceof Language) {
            return;
        }

        $theme = setting('theme');

        if (! $theme) {
            return;
        }

        $copiedWidgets = Widget::query()
            ->where('theme', $theme)
            ->get()
            ->toArray();

        if (empty($copiedWidgets)) {
            return;
        }

        foreach ($copiedWidgets as $key => $widget) {
            $copiedWidgets[$key]['theme'] = $theme.'-'.$event->data->lang_code;
            $copiedWidgets[$key]['data'] = json_encode($widget['data']);
            unset($copiedWidgets[$key]['id']);
        }

        Widget::query()->insertOrIgnore($copiedWidgets);
    }
}

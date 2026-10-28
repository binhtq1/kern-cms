<?php

namespace Kern\Theme\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Kern\Theme\Commands\Traits\ThemeTrait;
use Kern\Theme\Facades\Theme;
use Kern\Theme\Services\ThemeService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;

#[AsCommand('cms:theme:activate', 'Activate a theme')]
class ThemeActivateCommand extends Command implements PromptsForMissingInput
{
    use ThemeTrait;

    public function handle(ThemeService $themeService): int
    {
        $theme = $this->getTheme() ?: Theme::getThemeName();

        if (! preg_match('/^[a-z0-9\-]+$/i', $theme)) {
            $this->components->error('Only alphabetic characters are allowed.');

            return self::FAILURE;
        }

        $result = $themeService->activate($theme);

        if ($result['error']) {
            $this->components->error($result['message']);

            return self::FAILURE;
        }

        $this->components->info($result['message']);

        return self::SUCCESS;
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::OPTIONAL, 'The theme name that you want to activate');
    }
}

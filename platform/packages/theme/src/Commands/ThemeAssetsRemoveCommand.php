<?php

namespace Kern\Theme\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Kern\Theme\Commands\Traits\ThemeTrait;
use Kern\Theme\Services\ThemeService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;

#[AsCommand('cms:theme:assets:remove', 'Remove assets for a theme')]
class ThemeAssetsRemoveCommand extends Command implements PromptsForMissingInput
{
    use ThemeTrait;

    public function handle(ThemeService $themeService): int
    {
        $name = $this->getTheme();

        if (! preg_match('/^[a-z0-9\-]+$/i', $name)) {
            $this->components->error('Only alphabetic characters are allowed.');

            return self::FAILURE;
        }

        $result = $themeService->removeAssets($name);

        if ($result['error']) {
            $this->components->error($result['message']);

            return self::FAILURE;
        }

        $this->components->info($result['message']);

        return self::SUCCESS;
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'The theme name that you want to remove assets');
    }
}

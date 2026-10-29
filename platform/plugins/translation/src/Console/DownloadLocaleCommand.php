<?php

namespace Kern\Translation\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Kern\Translation\Manager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;

#[AsCommand('cms:translations:download-locale', 'Install bundled Kern translations')]
class DownloadLocaleCommand extends Command implements PromptsForMissingInput
{
    public function handle(Manager $manager): int
    {
        $this->components->info('Installing bundled translations...');

        $result = $manager->downloadRemoteLocale($this->argument('locale'));

        if ($result['error']) {
            $this->components->error($result['message']);

            return self::FAILURE;
        }

        $this->components->info($result['message']);

        return self::SUCCESS;
    }

    protected function configure(): void
    {
        $this->addArgument('locale', InputArgument::REQUIRED, 'The locale that you want to download');
    }
}

<?php

namespace Kern\Api\Commands;

use Illuminate\Console\Command;
use Kern\Setting\Facades\Setting;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('cms:api:generate-docs', 'Generate API documentation from your API routes.')]
class GenerateDocumentationCommand extends Command
{
    public function handle(): int
    {
        Setting::forceSet('api_enabled', true)->save();

        $this->call('scribe:generate', [
            '--force' => true,
        ]);

        return self::SUCCESS;
    }
}

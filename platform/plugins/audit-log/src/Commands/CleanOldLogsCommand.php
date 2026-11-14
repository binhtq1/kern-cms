<?php

namespace Kern\AuditLog\Commands;

use Illuminate\Console\Command;
use Kern\AuditLog\Models\AuditHistory;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('cms:activity-logs:clean-old-logs', 'Clean logs over 30 days')]
class CleanOldLogsCommand extends Command
{
    public function handle(): int
    {
        $this->components->info('Processing...');

        $this->call('model:prune', ['--model' => AuditHistory::class]);

        $this->components->info('Done!');

        return self::SUCCESS;
    }
}

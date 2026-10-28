<?php

namespace Kern\Media\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Kern\Media\Facades\RvMedia;
use Kern\Media\Models\MediaFile;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\progress;
use function Laravel\Prompts\table;

#[AsCommand('cms:media:thumbnail:generate', 'Generate thumbnails for images')]
class GenerateThumbnailCommand extends Command
{
    public function handle(): int
    {
        $this->components->info('Starting to generate thumbnails...');

        $files = MediaFile::query()->select(['url', 'mime_type', 'folder_id'])->get();

        $progress = progress(
            label: sprintf('Processing %s %s...', number_format($files->count()), Str::plural('file', $files->count())),
            steps: $files->count(),
        );

        $errors = [];

        foreach ($files as $file) {
            /**
             * @var MediaFile $file
             */
            try {
                $progress->label(sprintf('Processing %s...', $file->url));
                RvMedia::generateThumbnails($file);
                $progress->advance();
            } catch (Exception $exception) {
                $errors[] = $file->url;
                $this->components->error($exception->getMessage());
            }
        }

        $progress->finish();

        $this->components->info('Generated media thumbnails successfully!');

        $errors = array_unique($errors);

        $errors = array_map(fn ($item) => [$item], $errors);

        if ($errors) {
            $this->components->info('We are unable to regenerate thumbnail for these files:');

            table(['File directory'], $errors);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}

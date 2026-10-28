<?php

namespace Kern\Base\Commands;

use Exception;
use Illuminate\Console\Command;
use Kern\Base\Commands\Traits\ValidateCommandInput;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\text;

#[AsCommand('cms:google-fonts:fetch', 'Fetch Google Fonts and store them on a local disk')]
class FetchGoogleFontsCommand extends Command
{
    use ValidateCommandInput;

    public function handle(): int
    {
        $fontName = text(
            label: 'Google Fonts Name',
            required: true,
            validate: $this->validate('string')
        );

        $this->components->info(sprintf('Fetching <comment>%s</comment>...', $fontName));

        $font = 'https://fonts.googleapis.com/css2?family='.urlencode($fontName).'&display=swap';

        try {
            $font = app('core.google-fonts')->load($font, forceDownload: true);

            if (! $font) {
                $this->components->error('Failed to fetch Google Fonts.');

                return self::FAILURE;
            }

            $this->components->info('Google Fonts <info>'.$fontName.'</info> has been fetched and stored into <comment>'.ltrim(str_replace(url(''), '', $font->url()), '/').'</comment> successfully.');

            return self::SUCCESS;
        } catch (Exception $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}

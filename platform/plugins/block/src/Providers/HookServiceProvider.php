<?php

namespace Kern\Block\Providers;

use Kern\Base\Facades\BaseHelper;
use Kern\Base\Forms\FieldOptions\SelectFieldOption;
use Kern\Base\Forms\Fields\SelectField;
use Kern\Base\Forms\FormAbstract;
use Kern\Base\Supports\ServiceProvider;
use Kern\Block\Models\Block;
use Kern\Shortcode\Compilers\Shortcode;
use Kern\Shortcode\Forms\ShortcodeForm;

class HookServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! function_exists('shortcode')) {
            return;
        }

        add_shortcode(
            'static-block',
            trans('plugins/block::block.static_block_short_code_name'),
            trans('plugins/block::block.static_block_short_code_description'),
            [$this, 'render']
        );

        shortcode()->setPreviewImage(
            'static-block',
            asset('vendor/core/packages/shortcode/images/placeholder-code.jpg')
        );

        shortcode()->setAdminConfig('static-block', [$this, 'staticBlockAdminConfig']);
    }

    public function render(Shortcode $shortcode): ?string
    {
        $key = $shortcode->alias;

        if (! $key) {
            return null;
        }

        $block = Block::query()
            ->wherePublished()
            ->where('alias', $key)
            ->select(['id', 'content', 'raw_content'])
            ->first();

        if (! $block) {
            return null;
        }

        return $block->raw_content ? BaseHelper::clean($block->raw_content) : $block->content;
    }

    public function staticBlockAdminConfig(array $attributes): FormAbstract
    {
        $blocks = Block::query()
            ->wherePublished()
            ->pluck('name', 'alias')
            ->all();

        return ShortcodeForm::createFromArray($attributes)
            ->add(
                'alias',
                SelectField::class,
                SelectFieldOption::make()
                    ->label(trans('plugins/block::block.static_block_short_code_name'))
                    ->choices($blocks)
            );
    }
}

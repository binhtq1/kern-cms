<?php

namespace Kern\Blog\Exporters;

use Illuminate\Support\Collection;
use Kern\Base\Enums\BaseStatusEnum;
use Kern\Blog\Models\Post;
use Kern\Blog\Supports\PostFormat;
use Kern\DataSynchronize\Exporter\ExportColumn;
use Kern\DataSynchronize\Exporter\ExportCounter;
use Kern\DataSynchronize\Exporter\Exporter;
use Kern\Media\Facades\RvMedia;

class PostExporter extends Exporter
{
    public function getLabel(): string
    {
        return trans('plugins/blog::posts.posts');
    }

    public function columns(): array
    {
        return [
            ExportColumn::make('name'),
            ExportColumn::make('description'),
            ExportColumn::make('content'),
            ExportColumn::make('is_featured')
                ->boolean(),
            ExportColumn::make('format_type')
                ->dropdown(array_keys(PostFormat::getPostFormats(true))),
            ExportColumn::make('image'),
            ExportColumn::make('views'),
            ExportColumn::make('slug'),
            ExportColumn::make('url')
                ->label('URL'),
            ExportColumn::make('status')
                ->dropdown(BaseStatusEnum::values()),
            ExportColumn::make('categories'),
            ExportColumn::make('tags'),
        ];
    }

    public function counters(): array
    {
        return [
            ExportCounter::make()
                ->label(trans('plugins/blog::posts.export.total'))
                ->value(Post::query()->count()),
        ];
    }

    public function hasDataToExport(): bool
    {
        return Post::query()->exists();
    }

    public function collection(): Collection
    {
        return Post::query()
            ->with(['categories', 'tags', 'slugable'])
            ->get()
            ->transform(fn (Post $post) => [ // @phpstan-ignore-line
                ...$post->toArray(),
                'slug' => $post->slugable->key,
                'url' => $post->url,
                'image' => RvMedia::getImageUrl($post->image),
                'categories' => $post->categories->pluck('name')->implode(', '),
                'tags' => $post->tags->pluck('name')->implode(', '),
            ]);
    }
}

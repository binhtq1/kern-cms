<div class="magazine-wrap archive-page">
    <header class="archive-heading">
        {!! Theme::partial('breadcrumb', ['items' => [__('Topic')]]) !!}
        <p class="archive-heading__label">{{ __('Topic') }}</p>
        <h1>{{ $tag->name }}</h1>
    </header>
    {!! Theme::partial('archive', compact('posts')) !!}
</div>

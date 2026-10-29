<div class="magazine-wrap archive-page">
    <header class="archive-heading">
        {!! Theme::partial('breadcrumb', ['items' => [__('Chủ đề')]]) !!}
        <p class="archive-heading__label">{{ __('Chủ đề') }}</p>
        <h1>{{ $tag->name }}</h1>
    </header>
    {!! Theme::partial('archive', compact('posts')) !!}
</div>

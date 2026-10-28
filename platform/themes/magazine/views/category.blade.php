<div class="magazine-wrap archive-page">
    <header class="archive-heading">
        {!! Theme::partial('breadcrumb', ['items' => [__('Chuyên mục')]]) !!}
        <p class="archive-heading__label">{{ __('Chuyên mục') }}</p>
        <h1>{{ $category->name }}</h1>
        @if ($category->description)
            <div class="archive-heading__description">{{ $category->description }}</div>
        @endif
    </header>
    {!! Theme::partial('archive', compact('posts')) !!}
</div>

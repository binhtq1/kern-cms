@php
    $query = BaseHelper::stringify(request()->input('q'));
@endphp

<div class="magazine-wrap archive-page">
    <header class="archive-heading">
        {!! Theme::partial('breadcrumb', ['items' => [__('Search')]]) !!}
        <p class="archive-heading__label">{{ __('Search') }}</p>
        <h1>{{ $query ? __('Results for ":query"', ['query' => $query]) : __('Search articles') }}</h1>
        <form class="search-page__form" action="{{ route('public.search') }}" method="GET" role="search">
            <label for="search-page-query">{{ __('Keyword') }}</label>
            <div>
                <input id="search-page-query" name="q" type="search" value="{{ $query }}" placeholder="{{ __('e.g. coffee, Saigon, spaces') }}" required>
                <button type="submit">{{ __('Search') }}</button>
            </div>
        </form>
    </header>

    @if ($query)
        {!! Theme::partial('archive', compact('posts')) !!}
    @else
        <section class="editorial-empty editorial-empty--compact search-page__empty" aria-live="polite">
            <h2>{{ __('Start with something that interests you') }}</h2>
            <span>{{ __('Search by place, dish, or topic to explore published stories.') }}</span>
        </section>
    @endif
</div>

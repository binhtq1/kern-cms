@php
    $query = BaseHelper::stringify(request()->input('q'));
@endphp

<div class="magazine-wrap archive-page">
    <header class="archive-heading">
        {!! Theme::partial('breadcrumb', ['items' => [__('Tìm kiếm')]]) !!}
        <p class="archive-heading__label">{{ __('Tìm kiếm') }}</p>
        <h1>{{ $query ? __('Kết quả cho ":query"', ['query' => $query]) : __('Tìm bài viết') }}</h1>
        <form class="search-page__form" action="{{ route('public.search') }}" method="GET" role="search">
            <label for="search-page-query">{{ __('Từ khóa') }}</label>
            <div>
                <input id="search-page-query" name="q" type="search" value="{{ $query }}" placeholder="{{ __('Ví dụ: cà phê, Sài Gòn, không gian') }}" required>
                <button type="submit">{{ __('Tìm kiếm') }}</button>
            </div>
        </form>
    </header>

    @if ($query)
        {!! Theme::partial('archive', compact('posts')) !!}
    @else
        <section class="editorial-empty editorial-empty--compact search-page__empty" aria-live="polite">
            <h2>{{ __('Bắt đầu từ một điều bạn tò mò') }}</h2>
            <span>{{ __('Tìm theo địa điểm, món ăn hoặc chủ đề để khám phá các câu chuyện đã xuất bản.') }}</span>
        </section>
    @endif
</div>

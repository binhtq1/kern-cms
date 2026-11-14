<article class="magazine-wrap prose-page">
    <header class="prose-page__header">
        {!! Theme::partial('breadcrumb', ['items' => [$page->name]]) !!}
        <h1>{{ $page->name }}</h1>
    </header>
    <div class="prose-page__content story-content ck-content">
        {!! apply_filters(PAGE_FILTER_FRONT_PAGE_CONTENT, BaseHelper::clean($page->content), $page) !!}
    </div>
</article>

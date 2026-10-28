@if ($posts->isNotEmpty())
    @php
        $archivePosts = method_exists($posts, 'getCollection') ? $posts->getCollection() : collect($posts);
        $archiveLead = $archivePosts->first();
        $archiveSupportingPosts = $archivePosts->slice(1, 2);
        $archiveGridPosts = $archivePosts->slice(3);
    @endphp

    <div class="archive-showcase">
        @if ($archiveLead)
            {!! Theme::partial('post-card', ['post' => $archiveLead, 'variant' => 'feature']) !!}
        @endif

        @if ($archiveSupportingPosts->isNotEmpty())
            <div class="archive-showcase__support">
                @foreach ($archiveSupportingPosts as $post)
                    {!! Theme::partial('post-card', ['post' => $post, 'variant' => 'compact']) !!}
                @endforeach
            </div>
        @endif
    </div>

    @if ($archiveGridPosts->isNotEmpty())
        <div class="article-grid article-grid--archive">
            @foreach ($archiveGridPosts as $post)
                {!! Theme::partial('post-card', compact('post')) !!}
            @endforeach
        </div>
    @endif
    <div class="archive-pagination">
        {!! $posts->withQueryString()->links() !!}
    </div>
@else
    <section class="editorial-empty editorial-empty--compact" aria-live="polite">
        <h2>{{ __('Không tìm thấy bài viết.') }}</h2>
        <span>{{ __('Hãy thử cụm từ khác hoặc quay lại khi có bài viết mới.') }}</span>
    </section>
@endif

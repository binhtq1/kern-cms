@php
    $post->loadMissing('author', 'categories', 'categories.slugable', 'tags', 'tags.slugable');
    $relatedPosts = get_related_posts($post->id, 3)->loadMissing('author', 'categories', 'categories.slugable');
@endphp

<article class="magazine-wrap story">
    <header class="story-header">
        {!! Theme::partial('post-meta', ['post' => $post, 'showAuthor' => false]) !!}
        <h1>{{ $post->name }}</h1>
        @if ($post->description)
            <p class="story-header__dek">{{ $post->description }}</p>
        @endif
        <div class="story-header__details">
            @if ($post->author_name)
                <span>{{ __('Bởi :author', ['author' => $post->author_name]) }}</span>
            @endif
            <span>{{ Theme::formatDate($post->created_at) }}</span>
            @if ($post->time_reading)
                <span>{{ __('Đọc trong :count phút', ['count' => $post->time_reading]) }}</span>
            @endif
        </div>
    </header>

    @if ($post->image)
        <figure class="story-hero">
            {{ RvMedia::image($post->image, $post->name, 'magazine-hero', attributes: ['fetchpriority' => 'high', 'loading' => 'eager', 'decoding' => 'async']) }}
        </figure>
    @endif

    <div class="story-content ck-content">
        {!! BaseHelper::clean($post->content) !!}
    </div>

    <footer class="story-footer">
        @if ($post->tags->isNotEmpty())
            <div class="story-tags" aria-label="{{ __('Chủ đề') }}">
                @foreach ($post->tags as $tag)
                    <a href="{{ $tag->url }}">{{ $tag->name }}</a>
                @endforeach
            </div>
        @endif
        {!! Theme::renderSocialSharing($post->url, $post->description, $post->image) !!}
    </footer>
</article>

@if ($relatedPosts->isNotEmpty())
    <section class="magazine-wrap related-stories" aria-labelledby="related-stories-title">
        <div class="section-heading">
            <h2 id="related-stories-title">{{ __('Đọc tiếp') }}</h2>
        </div>
        <div class="article-grid article-grid--related">
            @foreach ($relatedPosts as $relatedPost)
                {!! Theme::partial('post-card', ['post' => $relatedPost]) !!}
            @endforeach
        </div>
    </section>
@endif

<div class="magazine-wrap comments">
    {!! apply_filters(BASE_FILTER_PUBLIC_COMMENT_AREA, null, $post) !!}
</div>

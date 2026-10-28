@php
    $featuredPosts = get_featured_posts((int) theme_option('homepage_featured_limit', 3), ['author', 'categories', 'categories.slugable']);
    $leadPost = $featuredPosts->shift();
    $featuredPostIds = $featuredPosts->pluck('id')->when($leadPost, fn ($ids) => $ids->prepend($leadPost->id))->all();
    $latestPosts = get_latest_posts((int) theme_option('homepage_latest_limit', 6), $featuredPostIds, ['author', 'categories', 'categories.slugable']);
    $featuredCategories = get_featured_categories(3, ['slugable']);
@endphp

<div class="magazine-wrap magazine-home">
    @if ($leadPost)
        <section class="lead-grid" aria-labelledby="lead-story-title">
            <div class="lead-story__visual">
                <a class="lead-story__media" href="{{ $leadPost->url }}" tabindex="-1" aria-hidden="true">
                    {{ RvMedia::image($leadPost->image, $leadPost->name, 'magazine-hero', attributes: ['fetchpriority' => 'high', 'loading' => 'eager', 'decoding' => 'async']) }}
                </a>
            </div>

            <article class="lead-story__content">
                {!! Theme::partial('post-meta', ['post' => $leadPost, 'showAuthor' => false]) !!}
                <h1 id="lead-story-title"><a href="{{ $leadPost->url }}">{{ $leadPost->name }}</a></h1>
                @if ($leadPost->description)
                    <p>{{ $leadPost->description }}</p>
                @endif
                <a class="text-link tw-interactive" href="{{ $leadPost->url }}">{{ __('Đọc bài viết') }}<span aria-hidden="true">&nbsp;&rarr;</span></a>
            </article>

            @if ($featuredPosts->isNotEmpty())
                <aside class="lead-grid__secondary" aria-labelledby="read-next-title">
                    <h2 id="read-next-title">{{ __('Đọc tiếp') }}</h2>
                    @foreach ($featuredPosts as $post)
                        {!! Theme::partial('post-card', ['post' => $post, 'variant' => 'compact']) !!}
                    @endforeach
                </aside>
            @endif
        </section>
    @else
        <section class="editorial-empty" aria-labelledby="empty-home-title">
            <p>{{ __('Magazine') }}</p>
            <h1 id="empty-home-title">{{ __('Your next story starts here.') }}</h1>
            <span>{{ __('Publish a featured post to fill this space.') }}</span>
        </section>
    @endif

    @if ($latestPosts->isNotEmpty())
        <section class="home-section" id="latest-stories" aria-labelledby="latest-stories-title">
            <div class="section-heading">
                <h2 id="latest-stories-title">{{ __('Mới nhất') }}</h2>
                <a class="text-link tw-interactive" href="{{ get_blog_page_url() }}">{{ __('Xem tất cả') }}<span aria-hidden="true">&nbsp;&rarr;</span></a>
            </div>
            @php
                $latestLead = $latestPosts->first();
                $latestSupportingPosts = $latestPosts->slice(1, 2);
                $latestGridPosts = $latestPosts->slice(3);
            @endphp
            <div class="latest-showcase">
                @if ($latestLead)
                    {!! Theme::partial('post-card', ['post' => $latestLead, 'variant' => 'feature']) !!}
                @endif

                @if ($latestSupportingPosts->isNotEmpty())
                    <div class="latest-showcase__support">
                        @foreach ($latestSupportingPosts as $post)
                            {!! Theme::partial('post-card', ['post' => $post, 'variant' => 'compact']) !!}
                        @endforeach
                    </div>
                @endif
            </div>

            @if ($latestGridPosts->isNotEmpty())
                <div class="article-grid article-grid--latest">
                    @foreach ($latestGridPosts as $post)
                        {!! Theme::partial('post-card', ['post' => $post]) !!}
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    @if ($featuredCategories->isNotEmpty())
        <section class="home-section home-section--categories" id="subjects" aria-labelledby="category-stories-title">
            <div class="section-heading">
                <h2 id="category-stories-title">{{ __('Khám phá theo chủ đề') }}</h2>
            </div>
            <div class="category-columns">
                @foreach ($featuredCategories as $category)
                    @php
                        $categoryPosts = get_posts_by_category($category->id, 0, (int) theme_option('homepage_category_posts_limit', 3))->loadMissing('author', 'categories', 'categories.slugable');
                        $categoryLead = $categoryPosts->shift();
                    @endphp
                    <section class="category-column" aria-labelledby="category-{{ $category->id }}-title">
                        <div class="category-column__heading">
                            <h3 id="category-{{ $category->id }}-title"><a href="{{ $category->url }}">{{ $category->name }}</a></h3>
                            <a href="{{ $category->url }}" aria-label="{{ __('Xem tất cả bài viết trong :category', ['category' => $category->name]) }}">&rarr;</a>
                        </div>
                        @if ($categoryLead)
                            {!! Theme::partial('post-card', ['post' => $categoryLead, 'variant' => 'category-feature']) !!}
                        @endif
                        @forelse ($categoryPosts as $post)
                            {!! Theme::partial('post-card', ['post' => $post, 'variant' => 'list']) !!}
                        @empty
                            @if (! $categoryLead)
                            <p class="category-column__empty">{{ __('Chưa có bài viết nào được xuất bản.') }}</p>
                            @endif
                        @endforelse
                    </section>
                @endforeach
            </div>
        </section>
    @endif
</div>

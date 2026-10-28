@php
    $post->loadMissing('categories', 'categories.slugable');
    $showAuthor = $showAuthor ?? true;
@endphp
<div class="post-meta">
    @if ($post->first_category?->name)
        <a href="{{ $post->first_category->url }}">{{ $post->first_category->name }}</a>
    @endif
    <time datetime="{{ $post->created_at?->toDateString() }}">{{ Theme::formatDate($post->created_at) }}</time>
    @if ($showAuthor && $post->author_name)
        <span>{{ $post->author_name }}</span>
    @endif
</div>

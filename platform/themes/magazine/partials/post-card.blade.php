@php
    $variant = $variant ?? 'standard';
    $post->loadMissing('author', 'categories', 'categories.slugable');
@endphp
<article class="post-card post-card--{{ $variant }}">
    @if ($variant !== 'list')
        <a class="post-card__media tw-interactive" href="{{ $post->url }}" tabindex="-1" aria-hidden="true">
            {{ RvMedia::image($post->image, $post->name, $variant === 'compact' ? 'magazine-compact' : 'magazine-card', attributes: ['loading' => $variant === 'feature' ? 'eager' : 'lazy', 'decoding' => 'async']) }}
        </a>
    @endif
    <div class="post-card__content">
        {!! Theme::partial('post-meta', compact('post')) !!}
        <h3><a href="{{ $post->url }}">{{ $post->name }}</a></h3>
        @if ($variant === 'standard' && $post->description)
            <p>{{ $post->description }}</p>
        @endif
    </div>
</article>

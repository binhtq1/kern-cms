@forelse ($posts as $post)
    <article>
        <h2><a href="{{ $post->url }}">{{ $post->name }}</a></h2>
        <p>{{ $post->description }}</p>
    </article>
@empty
    <p>{{ __('There is no data to display!') }}</p>
@endforelse

{!! $posts->withQueryString()->links() !!}

@php
    $items = $items ?? [];
@endphp

<nav class="breadcrumb" aria-label="{{ __('Breadcrumb navigation') }}">
    <a href="{{ BaseHelper::getHomepageUrl() }}">{{ __('Home') }}</a>
    @foreach ($items as $item)
        <span aria-hidden="true">/</span>
        <span @if ($loop->last) aria-current="page" @endif>{{ $item }}</span>
    @endforeach
</nav>

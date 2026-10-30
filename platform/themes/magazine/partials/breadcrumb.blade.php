@php
    $items = $items ?? [];
@endphp

<nav class="breadcrumb" aria-label="{{ __('Đường dẫn điều hướng') }}">
    <a href="{{ BaseHelper::getHomepageUrl() }}">{{ __('Trang chủ') }}</a>
    @foreach ($items as $item)
        <span aria-hidden="true">/</span>
        <span @if ($loop->last) aria-current="page" @endif>{{ $item }}</span>
    @endforeach
</nav>

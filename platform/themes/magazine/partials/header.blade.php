<!DOCTYPE html>
<html {!! Theme::htmlAttributes() !!}>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {!! Theme::header() !!}
</head>
<body {!! Theme::bodyAttributes() !!}>
{!! apply_filters(THEME_FRONT_BODY, null) !!}
<a class="skip-link" href="#main-content">{{ __('Chuyển đến nội dung') }}</a>

<header class="site-header">
    <div class="magazine-wrap site-header__inner">
        <a class="site-brand tw-interactive" href="{{ BaseHelper::getHomepageUrl() }}" aria-label="{{ theme_option('site_title') ?: config('app.name') }}">
            @if ($logo = Theme::getLogoImage(['class' => 'site-brand__logo'], maxHeight: 46))
                {!! $logo !!}
            @else
                <span class="site-brand__wordmark">{{ theme_option('site_title') ?: config('app.name') }}</span>
            @endif
        </a>

        <div class="site-header__actions">
            @if (is_plugin_active('blog'))
                <a class="header-search-link tw-interactive" href="{{ route('public.search') }}">{{ __('Tìm kiếm') }}</a>
            @endif

            <button class="menu-toggle tw-interactive" type="button" aria-expanded="false" aria-controls="site-navigation" data-menu-toggle>
                <span class="menu-toggle__bar" aria-hidden="true"></span>
                <span class="menu-toggle__bar" aria-hidden="true"></span>
                <span class="menu-toggle__label" aria-hidden="true">{{ __('Menu') }}</span>
                <span class="sr-only">{{ __('Mở hoặc đóng điều hướng') }}</span>
            </button>
        </div>

        <div class="site-navigation" id="site-navigation" data-site-navigation>
            <nav aria-label="{{ __('Điều hướng chính') }}">
                {!! Menu::renderMenuLocation('main-menu', ['options' => ['class' => 'site-menu'], 'view' => 'main-menu']) !!}
            </nav>

            @if (is_plugin_active('blog'))
                <form class="header-search" action="{{ route('public.search') }}" method="GET" role="search">
                    <label class="sr-only" for="magazine-search">{{ __('Tìm kiếm') }}</label>
                    <input id="magazine-search" name="q" type="search" value="{{ request()->input('q') }}" placeholder="{{ __('Tìm bài viết') }}">
                    <button type="submit">{{ __('Tìm') }}</button>
                </form>
            @endif
        </div>
    </div>
</header>

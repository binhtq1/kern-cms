<footer class="site-footer">
    <div class="magazine-wrap site-footer__main">
        <div class="site-footer__identity">
            <a class="site-footer__brand" href="{{ BaseHelper::getHomepageUrl() }}">{{ theme_option('site_title') ?: config('app.name') }}</a>
            @if ($description = theme_option('site_description'))
                <p class="site-footer__description">{{ $description }}</p>
            @endif
        </div>

        <div class="site-footer__navigation">
            <nav aria-label="{{ __('Điều hướng chân trang') }}">
                {!! Menu::renderMenuLocation('main-menu', ['options' => ['class' => 'footer-menu'], 'view' => 'main-menu']) !!}
            </nav>

            @if ($socialLinks = Theme::getSocialLinks())
                <nav class="site-footer__social" aria-label="{{ __('Mạng xã hội') }}">
                @foreach ($socialLinks as $socialLink)
                    @continue(! $icon = $socialLink->getIconHtml())
                    <a {{ $socialLink->getAttributes() }}>
                        {!! $icon !!}
                        <span class="sr-only">{{ $socialLink->getName() }}</span>
                    </a>
                @endforeach
                </nav>
            @endif
        </div>
    </div>
    <div class="magazine-wrap site-footer__bottom">
        <p>{!! Theme::getSiteCopyright() ?: '&copy; '.date('Y').' '.e(theme_option('site_title') ?: config('app.name')) !!}</p>
        @if (is_plugin_active('blog'))
            <a href="{{ route('public.search') }}">{{ __('Tìm bài viết') }}</a>
        @endif
    </div>
</footer>
{!! Theme::footer() !!}
</body>
</html>

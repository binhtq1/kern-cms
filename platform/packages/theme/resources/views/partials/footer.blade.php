{!! Theme::asset()->container('footer')->styles() !!}
{!! Theme::asset()->container('footer')->scripts() !!}
{!! Theme::asset()->container('after_footer')->scripts() !!}

{!! \Kern\Theme\Supports\ThemeSupport::renderGoogleTagManagerScript() !!}

{!! apply_filters(THEME_FRONT_FOOTER, null) !!}

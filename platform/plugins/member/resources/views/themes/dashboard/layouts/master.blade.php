<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="format-detection" content="telephone=no">
        <meta name="apple-mobile-web-app-capable" content="yes">
        @if (theme_option('favicon'))
            <link href="{{ RvMedia::getImageUrl(theme_option('favicon')) }}" rel="shortcut icon">
        @endif

        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ PageTitle::getTitle(false) }}</title>

        @include('plugins/member::themes.dashboard.layouts.header')

        <script type="text/javascript">
            'use strict';

            window.trans = {{ Js::from(trans('plugins/member::dashboard')) }};
            var KernVariables = KernVariables || {};
            KernVariables.languages = {
                tables: {{ Js::from(trans('core/base::tables')) }},
                notices_msg: {{ Js::from(trans('core/base::notices')) }},
                pagination: {{ Js::from(trans('pagination')) }},
            };
            var RV_MEDIA_URL = {
                'media_upload_from_editor': '{{ route('public.member.upload-from-editor') }}'
            };
        </script>

        @stack('header')
    </head>

    <body @if (BaseHelper::isRtlEnabled()) dir="rtl" @endif>
        @yield('body', view('plugins/member::themes.dashboard.layouts.body'))

        @include('plugins/member::themes.dashboard.layouts.footer')
        {!! Assets::renderFooter() !!}
        @stack('scripts')
        @stack('footer')
        {!! apply_filters(THEME_FRONT_FOOTER, null) !!}

        @if (Session::has('success_msg') || Session::has('error_msg') || (isset($errors) && $errors->any()) || isset($error_msg))
            <script type="text/javascript">
                document.addEventListener('DOMContentLoaded', () => {
                    const success = {{ Js::from(session('success_msg')) }};
                    const messages = {{ Js::from(array_filter(array_merge([session('error_msg'), $error_msg ?? null], isset($errors) ? $errors->all() : []))) }};
                    if (success) window.Kern?.showSuccess(success);
                    messages.forEach(message => window.Kern?.showError(message));
                });
            </script>
        @endif
    </body>
</html>

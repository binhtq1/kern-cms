<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ PageTitle::getTitle() }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/core/core/base/css/error-pages.css') }}">
</head>
<body>
    <div class="page page-center">
        <div class="container py-4 container-tight">
            @yield('content')
        </div>
    </div>
</body>
</html>

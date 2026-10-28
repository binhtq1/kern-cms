<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @if (BaseHelper::isRtlEnabled()) dir="rtl" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php(Illuminate\Support\Facades\Vite::useHotFile(public_path('admin.hot'))->useBuildDirectory('build/admin'))
    @viteReactRefresh
    {{ Illuminate\Support\Facades\Vite::useHotFile(public_path('admin.hot'))->useBuildDirectory('build/admin')->withEntryPoints(['resources/js/member.tsx']) }}
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>

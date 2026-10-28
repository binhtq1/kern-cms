<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
        (() => {
            let mode = 'system';
            try {
                const saved = localStorage.getItem('kern-admin-theme');
                if (saved === 'light' || saved === 'dark') mode = saved;
            } catch {}
            document.documentElement.dataset.theme = mode;
            document.documentElement.classList.toggle('dark', mode === 'dark' || (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches));
        })();
    </script>
    @php(Illuminate\Support\Facades\Vite::useHotFile(public_path('admin.hot'))->useBuildDirectory('build/admin'))
    @viteReactRefresh
    {{ Illuminate\Support\Facades\Vite::useHotFile(public_path('admin.hot'))->useBuildDirectory('build/admin')->withEntryPoints(['resources/js/admin.tsx']) }}
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>

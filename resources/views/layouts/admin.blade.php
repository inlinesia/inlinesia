<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#24272e">
    <title>@yield('title', 'Panel Event — Inlinesia')</title>
    <script>
        // Terapkan tema tersimpan sebelum render agar tidak ada kedipan tema.
        (function () {
            var theme;
            try {
                theme = window.localStorage.getItem('inlinesia-theme');
            } catch (error) {
                theme = null;
            }
            if (['copper', 'glass', 'neural', 'topic'].indexOf(theme) === -1) {
                theme = 'copper';
            }
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/theme-switcher.js', 'resources/js/preset-picker.js', 'resources/js/race.js', 'resources/js/page-context.js'])
</head>
<body>
    @php($hideSidebar = $hideSidebar ?? false)
    <div @class(['app-shell', 'is-sidebarless' => $hideSidebar])>
        @unless ($hideSidebar)
            <x-sidebar />
        @endunless
        <main class="main-content" id="beranda">
            <x-topbar :section="trim($__env->yieldContent('section', 'Beranda'))" />
            @if (session('status'))
                <div class="admin-flash" role="status">{{ session('status') }}</div>
            @endif
            @yield('content')
        </main>
    </div>
    <x-page-context />
</body>
</html>

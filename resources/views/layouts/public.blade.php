<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#24272e">
    <title>@yield('title', 'Inlinesia — Indonesia Inline Skate Events')</title>
    <meta name="description" content="@yield('description', 'Pengelolaan event inline skate dan sepatu roda di Indonesia bersama Inlinesia.')">
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
<body class="@yield('body-class', 'public-home')">
    <div class="public-site">
        <x-navbar />

        @if (session('status'))
            <div class="public-flash" role="status">{{ session('status') }}</div>
        @endif
        @if (session('warning'))
            <div class="public-flash public-flash-warning" role="status">{{ session('warning') }}</div>
        @endif

        @yield('content')

        <x-footer />
    </div>
    <x-page-context />
</body>
</html>

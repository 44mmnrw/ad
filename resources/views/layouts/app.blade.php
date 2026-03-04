<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>@yield('title', config('app.name', 'Авто Доставка'))</title>

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="ad-portal-body">
        <div class="ad-portal">
            @include('partials.header')

            <div class="ad-layout">
                @include('partials.sidebar', ['activeMenu' => $activeMenu ?? null])

                <div class="ad-page">
                    <main class="ad-main">
                        @yield('content')
                    </main>

                    @include('partials.footer')
                </div>
            </div>
        </div>
    </body>
</html>

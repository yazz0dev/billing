<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light"> {{-- Default theme --}}
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}"> {{-- CSRF Token for AJAX --}}
    <title>{{ $pageTitle ?? config('app.name', 'Laravel') }}</title>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/global.css') }}?v={{ filemtime(public_path('css/global.css')) }}">
    
    @stack('styles') {{-- For page-specific styles --}}

    <script>
        window.APP_URL = "{{ url('/') }}"; // Base URL for JS
        window.USER_AUTHENTICATED = {{ Auth::check() ? 'true' : 'false' }};
        window.USER_ID = "{{ Auth::id() }}";
        window.USER_ROLE = "{{ Auth::user()->role ?? '' }}";
        window.USER_NAME = "{{ Auth::user()->username ?? 'Guest' }}";
    </script>
</head>
<body class="layout-main {{ $bodyClass ?? '' }}">
    <div class="page-wrapper">
        @include('partials.topbar')

        <main class="main-content-area">
            {{ $slot }} {{-- Main content for Laravel 10+ default --}}
            {{-- @yield('content') --}} {{-- For older Laravel or if you prefer @yield --}}
        </main>

        @include('partials.footer')
    </div>

    <script src="{{ asset('js/popup-notification.js') }}?v={{ filemtime(public_path('js/popup-notification.js')) }}"></script>
    <script src="{{ asset('js/topbar.js') }}?v={{ filemtime(public_path('js/topbar.js')) }}" defer></script>
    
    @stack('scripts') {{-- For page-specific scripts --}}
</body>
</html>
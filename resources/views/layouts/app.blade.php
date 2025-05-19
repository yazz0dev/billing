<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $pageTitle ?? config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
        
        <!-- Styles -->
        <link rel="stylesheet" href="{{ asset('css/global.css') }}?v={{ filemtime(public_path('css/global.css')) }}">
        {{-- @vite(['resources/css/app.css']) --}} {{-- If using Vite for additional app-specific CSS --}}
         @stack('styles')

        <script>
            window.APP_URL = "{{ url('/') }}";
            window.USER_AUTHENTICATED = {{ Auth::check() ? 'true' : 'false' }};
            window.USER_ID = "{{ Auth::id() }}";
            window.USER_ROLE = "{{ Auth::user()->role ?? '' }}";
            window.USER_NAME = "{{ Auth::user()->username ?? 'Guest' }}";
        </script>
    </head>
    <body class="font-sans antialiased layout-main {{ $bodyClass ?? '' }}">
        <div class="min-h-screen bg-gray-100 dark:bg-gray-900 page-wrapper">
            @include('partials.topbar') {{-- Your custom topbar --}}
            {{-- Or use Breeze navigation: @include('layouts.navigation') --}}

            <!-- Page Heading -->
            @if (isset($header))
                <header class="bg-white dark:bg-gray-800 shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endif

            <!-- Page Content -->
            <main class="main-content-area">
                {{ $slot }}
            </main>
            @include('partials.footer')
        </div>
        <script src="{{ asset('js/popup-notification.js') }}?v={{ filemtime(public_path('js/popup-notification.js')) }}"></script>
        <script src="{{ asset('js/topbar.js') }}?v={{ filemtime(public_path('js/topbar.js')) }}" defer></script>
        {{-- @vite(['resources/js/app.js']) --}} {{-- If using Vite for global JS --}}
        @stack('scripts')
    </body>
</html>
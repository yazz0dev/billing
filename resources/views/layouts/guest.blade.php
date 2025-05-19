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
        {{-- @vite(['resources/css/app.css']) --}}
        @stack('styles')
        <script>
            window.APP_URL = "{{ url('/') }}";
            window.USER_AUTHENTICATED = false; // Guest pages
        </script>
    </head>
    <body class="font-sans text-gray-900 antialiased layout-minimal {{ $bodyClass ?? ''}}">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100 dark:bg-gray-900 page-wrapper">
            {{-- Removed Breeze Logo --}}
            <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white dark:bg-gray-800 shadow-md overflow-hidden sm:rounded-lg glass">
                {{ $slot }}
            </div>
        </div>
        <script src="{{ asset('js/popup-notification.js') }}?v={{ filemtime(public_path('js/popup-notification.js')) }}"></script>
         <script>
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof PopupNotification === 'function') {
                    window.popupNotification = new PopupNotification({
                        fetchUrl: `${window.APP_URL}/api/notifications/fetch`,
                        markSeenUrl: `${window.APP_URL}/api/notifications/mark-seen`,
                        fetchFromServer: false
                    });
                }
                // Display errors from session (e.g., validation errors from LoginRequest)
                @if ($errors->any())
                    let errorMessages = '';
                    @foreach ($errors->all() as $error)
                        errorMessages += "{{ addslashes($error) }}<br>";
                    @endforeach
                    if (window.popupNotification) {
                        window.popupNotification.error(errorMessages, "Validation Error");
                    } else {
                        console.error("Validation Errors:", errorMessages.replace(/<br>/g, "\n"));
                    }
                @endif
                 @if(session('status')) // For password reset status, etc.
                    if (window.popupNotification) {
                        window.popupNotification.success("{{ addslashes(session('status')) }}", "Status");
                    }
                @endif
            });
        </script>
        @stack('scripts')
    </body>
</html>
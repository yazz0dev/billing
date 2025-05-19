<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle ?? config('app.name', 'Laravel') }}</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/global.css') }}?v={{ filemtime(public_path('css/global.css')) }}">
    
    @stack('styles')
    <script>
        window.APP_URL = "{{ url('/') }}";
        window.USER_AUTHENTICATED = {{ Auth::check() ? 'true' : 'false' }};
        window.USER_ID = "{{ Auth::id() }}";
        window.USER_ROLE = "{{ Auth::user()->role ?? '' }}";
         window.USER_NAME = "{{ Auth::user()->username ?? 'Guest' }}";
    </script>
</head>
<body class="layout-minimal {{ $bodyClass ?? '' }}">
    <div class="page-wrapper">
        {{ $slot }}
        {{-- @yield('content') --}}
    </div>
    <script src="{{ asset('js/popup-notification.js') }}?v={{ filemtime(public_path('js/popup-notification.js')) }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof PopupNotification === 'function') {
                window.popupNotification = new PopupNotification({
                    fetchUrl: `${window.APP_URL}/api/notifications/fetch`,
                    markSeenUrl: `${window.APP_URL}/api/notifications/mark-seen`,
                    fetchFromServer: false // Usually disabled on minimal pages
                });
            }

            @if(session('initial_page_message'))
                @php $message = session('initial_page_message'); @endphp
                if (window.popupNotification) {
                    window.popupNotification.{{ $message['type'] ?? 'info' }}("{{ addslashes($message['text']) }}", "{{ addslashes($message['title'] ?? ucfirst($message['type'] ?? 'Info')) }}");
                } else {
                    console.log("{{ $message['type'] ?? 'info' }}: {{ addslashes($message['text']) }}");
                }
            @endif
        });
    </script>
    @stack('scripts')
</body>
</html>
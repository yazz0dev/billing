@extends('layouts.minimal', ['pageTitle' => $exception->getMessage() ?: 'Forbidden'])

@section('content') {{-- Or use $slot in minimal layout --}}
<div class="container text-center error-page-container">
    <h1 class="error-title">403</h1>
    <h2 class="error-subtitle">{{ $exception->getMessage() ?: 'Access Denied' }}</h2>
    <p class="error-message">You do not have permission to access this page or resource.</p>
    @if(config('app.debug') && isset($exception) && method_exists($exception, 'getTraceAsString'))
        <div class="debug-info" style="text-align: left; background: var(--bg-surface-alt); border: 1px solid var(--border-color-subtle); padding: 15px; margin-top: 20px; overflow-x: auto; font-family: monospace; font-size: 0.9em; color: var(--text-primary); max-height: 300px; border-radius: var(--border-radius-sm);">
            <strong>Debug Information:</strong><br>
            <pre>{{ $exception->getTraceAsString() }}</pre>
        </div>
    @endif
    <a href="{{ route('home') }}" class="btn btn-primary mt-3">Go to Homepage</a>
    @auth
        <p class="mt-2"><a href="{{ route('logout') }}" class="text-sm" onclick="event.preventDefault(); document.getElementById('logout-form-error').submit();">Logout</a></p>
        <form id="logout-form-error" action="{{ route('logout') }}" method="POST" style="display: none;">@csrf</form>
    @else
        <p class="mt-2"><a href="{{ route('login') }}" class="text-sm">Login</a></p>
    @endauth
</div>
<style> /* Same styles as your PHP error pages */ </style>
@endsection
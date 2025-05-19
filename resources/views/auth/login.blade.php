<x-guest-layout :pageTitle="$pageTitle ?? 'Login'">
    <div class="login-page-container" style="padding-top:0; padding-bottom:0;"> {{-- Adjustments for guest layout --}}
        <div class="login-form-wrapper">
            <div class="login-header">
                <h1>Welcome Back!</h1>
                <p>Log in to access your billing dashboard.</p>
            </div>
            
            <!-- Session Status (e.g. for password reset success) -->
            <x-auth-session-status class="mb-4" :status="session('status')" />

            {{-- Display validation errors --}}
            @if ($errors->any())
                <div class="login-error-message" style="display: block; margin-bottom: 1.5rem; background: var(--error-bg); color: var(--error-text-emphasis); padding: 0.75rem 1rem; border-radius: var(--border-radius-sm); font-size: 0.9rem; border: 1px solid var(--error); border-left-width: 4px;">
                    <ul style="list-style-type: none; padding: 0; margin: 0;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            
            <form method="POST" action="{{ route('login') }}" class="login-form glass">
                @csrf
                <div class="form-group">
                    <label for="username">Username</label> {{-- Breeze uses 'email' by default, changed to 'username' --}}
                    <input type="text" id="username" name="username" :value="old('username')" placeholder="Enter username" required autofocus autocomplete="username">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter password" required autocomplete="current-password">
                </div>

                <div class="form-group flex items-center justify-between mt-1">
                    <label for="remember_me" class="inline-flex items-center">
                        <input id="remember_me" type="checkbox" class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:focus:ring-offset-gray-800" name="remember" style="width:auto; margin-right: 0.5rem;">
                        <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">{{ __('Remember me') }}</span>
                    </label>
                    @if (Route::has('password.request'))
                        <a class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800" href="{{ route('password.request') }}">
                            {{ __('Forgot your password?') }}
                        </a>
                    @endif
                </div>
                
                <button type="submit" class="btn w-full mt-3">Login</button>
            </form>
            <a href="{{ route('home') }}" class="login-back-link mt-3">Back to Home</a>
        </div>
    </div>
</x-guest-layout>
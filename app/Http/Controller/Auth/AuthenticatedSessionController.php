<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use App\Services\NotificationService; // Your service

class AuthenticatedSessionController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login', ['pageTitle' => 'Login']);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = Auth::user();
        $this->notificationService->create(
            "Welcome back, {$user->username}!",
            'success',
            (string) $user->id,
            5000,
            "Login Successful"
        );

        // Redirect based on role
        if ($user->hasRole('admin')) {
            return redirect()->intended(RouteServiceProvider::ADMIN_HOME);
        } elseif ($user->hasRole('staff')) {
            return redirect()->intended(RouteServiceProvider::STAFF_HOME);
        }

        return redirect()->intended(RouteServiceProvider::HOME);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if ($user) {
            $this->notificationService->create(
                "User '{$user->username}' logged out.",
                'info',
                (string) $user->id,
                3000,
                "Logout Successful"
            );
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
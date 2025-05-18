<?php // src/Auth/AuthController.php

namespace App\Auth;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Notification\NotificationService; // Assuming you have this

class AuthController extends Controller
{
    private AuthService $authService;
    private NotificationService $notificationService;

    public function __construct()
    {
        parent::__construct(); // Calls Controller's constructor for View
        $this->authService = new AuthService();
        $this->notificationService = new NotificationService(); // Instantiate if needed
    }

    public function showHomePage(Request $request, Response $response)
    {
        // If user is already logged in, redirect based on role
        if ($this->authService->check()) {
            $role = $_SESSION['user_role'];
            if ($role === 'admin') {
                $response->redirect('/admin/dashboard');
            } elseif ($role === 'staff') {
                $response->redirect('/staff/pos');
            }
            // Fallback if role is unknown or no specific dashboard
            $response->redirect('/some-default-logged-in-page'); // Define this
        }

        $this->render('home.php', ['pageTitle' => 'Welcome'], 'layouts/minimal.php');
    }


    public function showLoginForm(Request $request, Response $response)
    {
        if ($this->authService->check()) {
            // Determine redirect based on role if already logged in
            $role = $_SESSION['user_role'] ?? 'guest';
            $redirectUrl = match ($role) {
                'admin' => '/admin/dashboard',
                'staff' => '/staff/pos',
                default => '/', // Or a generic logged-in dashboard
            };
            $response->redirect($redirectUrl);
            return;
        }

        $errorMessage = $_SESSION['error_message'] ?? null;
        unset($_SESSION['error_message']); // Clear after displaying

        $this->render('auth/login.php', [
            'pageTitle' => 'Login',
            'error_message' => $errorMessage,
            'csrf_token_name' => 'login_csrf',
            'csrf_token_value' => $this->generateCsrfToken('login_form')
        ], 'layouts/minimal.php');
    }

    public function handleLogin(Request $request, Response $response)
    {
        if (!$this->verifyCsrfToken($request, 'login_form', 'login_csrf')) {
            $_SESSION['error_message'] = 'Invalid security token. Please try again.';
            $response->redirect('/login');
            return;
        }

        $username = $request->post('username');
        $password = $request->post('password');

        if (empty($username) || empty($password)) {
            $_SESSION['error_message'] = 'Username and password are required.';
            $response->redirect('/login');
            return;
        }

        if ($this->authService->attemptLogin($username, $password)) {
            $user = $this->authService->user();
            $this->notificationService->create(
                "Welcome back, {$user['username']}!",
                'success',
                (string) $user['id'], // Target self
                5000,
                "Login Successful"
            );

            $redirectUrl = $_SESSION['redirect_after_login'] ?? null;
            unset($_SESSION['redirect_after_login']);

            if ($redirectUrl) {
                $response->redirect($redirectUrl);
            } elseif ($user['role'] === 'admin') {
                $response->redirect('/admin/dashboard');
            } elseif ($user['role'] === 'staff') {
                $response->redirect('/staff/pos');
            } else {
                $response->redirect('/'); // Fallback
            }
        } else {
            $_SESSION['error_message'] = 'Invalid username or password.';
            $this->notificationService->create( // General notification for failed login attempt
                "Failed login attempt for username: {$username}",
                'warning',
                'admin', // Notify admin
                0, // Persist
                "Login Attempt Failed"
            );
            $response->redirect('/login');
        }
    }

    public function logout(Request $request, Response $response)
    {
        $username = $_SESSION['username'] ?? 'User';
        $userId = $_SESSION['user_id'] ?? 'unknown';

        $this->authService->logout();

        $this->notificationService->create(
            "User '{$username}' logged out.",
            'info',
            (string)$userId, // Target self (will be seen on next login if persistent and targeted right) or 'all' if desired
            3000,
            "Logout Successful"
        );
        $response->redirect('/login');
    }
}

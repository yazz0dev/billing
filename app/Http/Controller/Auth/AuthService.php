<?php // src/Auth/AuthService.php

namespace App\Auth;

class AuthService
{
    private UserRepository $userRepository;

    public function __construct()
    {
        $this->userRepository = new UserRepository();
    }

    public function attemptLogin(string $username, string $password): bool
    {
        $user = $this->userRepository->findByUsername($username);

        if ($user && isset($user->password)) {
            if ($password === $user->password) { 
                $this->establishSession($user);
                return true;
            }
        }
        return false;
    }

    private function establishSession(\MongoDB\Model\BSONDocument $user): void
    {
        session_regenerate_id(true); // Prevent session fixation
        $_SESSION['user_id'] = (string) $user->_id;
        $_SESSION['username'] = $user->username;
        $_SESSION['user_role'] = $user->role;
        $_SESSION['user_email'] = $user->email ?? ($user->username . '@example.com');
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }

    public function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public function user(): ?array // Return basic user info if needed
    {
        if (!$this->check()) return null;
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'role' => $_SESSION['user_role'],
            'email' => $_SESSION['user_email'],
        ];
    }

    public function hasRole(string $role): bool
    {
        return $this->check() && strtolower($_SESSION['user_role']) === strtolower($role);
    }

    public function hasAnyRole(array $roles): bool
    {
        if (!$this->check()) return false;
        $userRoleLower = strtolower($_SESSION['user_role']);
        foreach ($roles as $role) {
            if ($userRoleLower === strtolower($role)) {
                return true;
            }
        }
        return false;
    }
}

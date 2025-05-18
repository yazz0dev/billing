<?php // src/Middleware/AuthMiddleware.php

namespace App\Middleware;

use App\Core\Request;
// Use your custom exception
use App\Core\Exception\AccessDeniedException; // Correct namespace

class AuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param string ...$roles Required roles. If empty, just checks for authentication.
     * @throws AccessDeniedException
     */
    public function handle(Request $request, string ...$roles): void // Roles are now variadic
    {
        if (!isset($_SESSION['user_id'])) {
            // User is not authenticated
            throw new AccessDeniedException('Authentication required. Please log in.');
        }

        if (!empty($roles)) {
            // Roles are specified, check if the user has one of them
            $userRole = strtolower($_SESSION['user_role'] ?? '');
            $isAllowed = false;
            foreach ($roles as $requiredRole) {
                if ($userRole === strtolower(trim($requiredRole))) {
                    $isAllowed = true;
                    break;
                }
            }
            if (!$isAllowed) {
                throw new AccessDeniedException('You do not have permission to access this resource. Required role(s): ' . implode(', ', $roles));
            }
        }
        // If no roles specified, authentication check passed.
        // If roles specified, role check passed.
    }
}
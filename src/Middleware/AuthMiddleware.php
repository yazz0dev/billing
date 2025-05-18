<?php // src/Middleware/AuthMiddleware.php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Exception\AccessDeniedException;

class AuthMiddleware
{
    // In a real app, $roles might come from route definition or be more complex
    public function handle(Request $request, ...$roles): void
    {
        if (!isset($_SESSION['user_id'])) {
            throw new AccessDeniedException('Authentication required.'); // This will trigger redirect to login in api/index.php
        }

        if (!empty($roles)) {
            $userRole = $_SESSION['user_role'] ?? null;
            $allowed = false;
            foreach ($roles as $role) {
                if ($userRole === $role) {
                    $allowed = true;
                    break;
                }
            }
            if (!$allowed) {
                throw new Access

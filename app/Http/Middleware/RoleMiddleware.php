<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!Auth::check()) {
            return $request->expectsJson()
                        ? response()->json(['message' => 'Unauthenticated.'], 401)
                        : redirect()->route('login');
        }

        $user = Auth::user();

        if (!$user || !$user->hasAnyRole($roles)) {
            return $request->expectsJson()
                        ? response()->json(['message' => 'This action is unauthorized.'], 403)
                        : abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }
}
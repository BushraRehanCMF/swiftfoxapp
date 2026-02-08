<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsOwner
{
    /**
     * Handle an incoming request.
     * Ensures the user is an account owner.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'You must be logged in to access this resource.',
                ],
            ], 401);
        }

        // Super admins can access owner-only resources when impersonating
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        if (!$user->isOwner()) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Only account owners can access this resource.',
                ],
            ], 403);
        }

        return $next($request);
    }
}

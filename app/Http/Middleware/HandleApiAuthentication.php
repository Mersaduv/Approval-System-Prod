<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class HandleApiAuthentication
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Debug: Log the request
        \Log::info('API Authentication Check', [
            'url' => $request->url(),
            'method' => $request->method(),
            'authenticated' => Auth::check(),
            'user_id' => Auth::id(),
            'session_id' => $request->session()->getId(),
            'cookies' => $request->cookies->all()
        ]);

        // Check if user is authenticated
        if (!Auth::check()) {
            \Log::warning('API Authentication Failed', [
                'url' => $request->url(),
                'session_id' => $request->session()->getId()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please login first.',
                'error' => 'UNAUTHENTICATED'
            ], 401);
        }

        // Check if user has a role
        if (!Auth::user()->role) {
            \Log::warning('User has no role', [
                'user_id' => Auth::id(),
                'user_email' => Auth::user()->email
            ]);

            return response()->json([
                'success' => false,
                'message' => 'User role not found. Please contact administrator.',
                'error' => 'NO_ROLE'
            ], 403);
        }

        \Log::info('API Authentication Success', [
            'user_id' => Auth::id(),
            'user_email' => Auth::user()->email,
            'role' => Auth::user()->role->name
        ]);

        return $next($request);
    }
}

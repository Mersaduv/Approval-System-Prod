<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use App\Models\User;

class AuthController extends Controller
{
    /**
     * Show the login form
     */
    public function showLoginForm()
    {
        return Inertia::render('Auth/Login');
    }

    /**
     * Handle login request
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:8',
        ]);

        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();

            // Debug: Log successful authentication
            \Log::info('User authenticated successfully', [
                'user_id' => Auth::id(),
                'email' => $request->email
            ]);

            // Return Inertia response for SPA
            return redirect()->intended('/');
        }

        // Debug: Log failed authentication
        \Log::warning('Authentication failed', [
            'email' => $request->email,
            'ip' => $request->ip()
        ]);

        throw ValidationException::withMessages([
            'email' => 'The provided credentials do not match our records.',
        ]);
    }

    /**
     * Handle logout request
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    /**
     * Get authenticated user data
     */
    public function user(Request $request)
    {
        return response()->json([
            'user' => $request->user()->load(['department', 'role'])
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    public function index()
    {
        return Inertia::render('Home', [
            'auth' => [
                'user' => Auth::user() ? Auth::user()->load(['department', 'role']) : null
            ]
        ]);
    }

    public function dashboard()
    {
        return Inertia::render('Dashboard', [
            'auth' => [
                'user' => Auth::user() ? Auth::user()->load(['department', 'role']) : null
            ]
        ]);
    }

    public function test()
    {
        return Inertia::render('Test', [
            'auth' => [
                'user' => Auth::user() ? Auth::user()->load(['department', 'role']) : null
            ]
        ]);
    }
}

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

}

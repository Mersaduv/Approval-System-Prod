<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ApprovalPortalController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/user', [AuthController::class, 'user'])->name('user');

    // Protected Routes
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/test-tailwind', function () {
        return view('test-tailwind');
    });
    Route::get('/css-test', function () {
        return view('css-test');
    });

    // New UI Routes
    Route::get('/requests', function () {
        return inertia('Requests', [
            'auth' => [
                'user' => Auth::user() ? Auth::user()->load(['department', 'role']) : null
            ]
        ]);
    });
    Route::get('/requests/new', function () {
        return inertia('NewRequest', [
            'auth' => [
                'user' => Auth::user() ? Auth::user()->load(['department', 'role']) : null
            ]
        ]);
    });
    Route::get('/requests/{id}', function ($id) {
        return inertia('RequestView', [
            'requestId' => $id,
            'source' => 'requests',
            'auth' => [
                'user' => Auth::user() ? Auth::user()->load(['department', 'role']) : null
            ]
        ]);
    });
    Route::get('/notifications', function () {
        return inertia('Notifications', [
            'auth' => [
                'user' => Auth::user() ? Auth::user()->load(['department', 'role']) : null
            ]
        ]);
    });
    Route::get('/reports', function () {
        return inertia('Reports', [
            'auth' => [
                'user' => Auth::user() ? Auth::user()->load(['department', 'role']) : null
            ]
        ]);
    });
    Route::get('/users', function () {
        return inertia('Users', [
            'auth' => [
                'user' => Auth::user() ? Auth::user()->load(['department', 'role']) : null
            ]
        ]);
    });
    Route::get('/settings', function () {
        return inertia('Settings', [
            'auth' => [
                'user' => Auth::user() ? Auth::user()->load(['department', 'role']) : null
            ]
        ]);
    });
    Route::get('/workflow-settings', function () {
        return inertia('WorkflowSettings', [
            'auth' => [
                'user' => Auth::user() ? Auth::user()->load(['department', 'role']) : null
            ]
        ]);
    });
    Route::get('/delegations', function () {
        return inertia('DelegationManagement', [
            'auth' => [
                'user' => Auth::user() ? Auth::user()->load(['department', 'role']) : null
            ]
        ]);
    });
    Route::get('/procurement/verification', function () {
        return inertia('ProcurementVerification', [
            'auth' => [
                'user' => Auth::user() ? Auth::user()->load(['department', 'role']) : null
            ]
        ]);
    });


});

// Test route for debugging (Public - no auth required)
Route::get('/test-data', function () {
    return response()->json([
        'users' => App\Models\User::with('role')->get(),
        'roles' => App\Models\Role::all()
    ]);
});

// Test authenticated route
Route::get('/test-auth', function () {
    if (Auth::check()) {
        return response()->json([
            'authenticated' => true,
            'user' => Auth::user()->load('role'),
            'users' => App\Models\User::with('role')->get(),
            'roles' => App\Models\Role::all()
        ]);
    } else {
        return response()->json([
            'authenticated' => false,
            'message' => 'Please login first'
        ]);
    }
})->middleware('auth');

// Test API route with session
Route::get('/api/test-session', function () {
    if (Auth::check()) {
        return response()->json([
            'success' => true,
            'authenticated' => true,
            'user' => Auth::user()->load('role'),
            'session_id' => request()->session()->getId()
        ]);
    } else {
        return response()->json([
            'success' => false,
            'authenticated' => false,
            'message' => 'Please login first',
            'session_id' => request()->session()->getId()
        ]);
    }
})->middleware('web');

// Approval Portal Routes (Public - no auth required)
Route::get('/approval/{token}', [ApprovalPortalController::class, 'show'])->name('approval.portal');
Route::post('/approval/{token}/process', [ApprovalPortalController::class, 'process'])->name('approval.process');
Route::get('/approval/{token}/success', [ApprovalPortalController::class, 'success'])->name('approval.success');
Route::get('/api/approval/{token}/details', [ApprovalPortalController::class, 'getRequestDetails'])->name('approval.details');

// Approval Portal with RequestView (Redirect to main app)
Route::get('/approval-portal/{token}', function($token) {
    $approvalToken = \App\Models\ApprovalToken::where('token', $token)->first();

    if (!$approvalToken || !$approvalToken->isValid()) {
        return view('approval.invalid-token', [
            'message' => 'Invalid or expired approval token'
        ]);
    }

    return inertia('RequestView', [
        'requestId' => $approvalToken->request_id,
        'source' => 'approval',
        'approvalToken' => $token,
        'auth' => [
            'user' => $approvalToken->approver ? $approvalToken->approver->load(['department', 'role']) : null
        ]
    ]);
})->name('approval.portal.view');

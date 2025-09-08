<?php

use Illuminate\Support\Facades\Route;
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
    Route::get('/dashboard', [HomeController::class, 'dashboard'])->name('dashboard');
    Route::get('/test', [HomeController::class, 'test']);
    Route::get('/test-tailwind', function () {
        return view('test-tailwind');
    });
    Route::get('/css-test', function () {
        return view('css-test');
    });

    // New UI Routes
    Route::get('/requests', function () {
        return inertia('Requests');
    });
    Route::get('/requests/new', function () {
        return inertia('NewRequest');
    });
    Route::get('/notifications', function () {
        return inertia('Notifications');
    });
    Route::get('/reports', function () {
        return inertia('Reports');
    });
    Route::get('/workflow-demo', function () {
        return inertia('WorkflowDemo');
    });
    Route::get('/users', function () {
        return inertia('Users');
    });
    Route::get('/settings', function () {
        return inertia('Settings');
    });
});

// Approval Portal Routes (Public - no auth required)
Route::get('/approval/{token}', [ApprovalPortalController::class, 'show'])->name('approval.portal');
Route::post('/approval/{token}/process', [ApprovalPortalController::class, 'process'])->name('approval.process');
Route::get('/approval/{token}/success', [ApprovalPortalController::class, 'success'])->name('approval.success');
Route::get('/api/approval/{token}/details', [ApprovalPortalController::class, 'getRequestDetails'])->name('approval.details');

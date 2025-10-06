<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class ProfileController extends Controller
{
    /**
     * Show the profile page
     */
    public function index()
    {
        $user = Auth::user()->load(['role', 'department']);

        return Inertia::render('Profile', [
            'auth' => [
                'user' => $user
            ]
        ]);
    }

    /**
     * Update user profile information
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        // Debug logging
        \Log::info('Profile update request received', [
            'user_id' => $user->id,
            'data' => $request->all()
        ]);

        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
        ]);

        if ($validator->fails()) {
            \Log::warning('Profile update validation failed', $validator->errors()->toArray());
            return back()->withErrors($validator->errors())->withInput();
        }

        try {
            $user->update([
                'full_name' => $request->full_name,
                'email' => $request->email,
            ]);

            \Log::info('Profile updated successfully', ['user_id' => $user->id]);
            return back()->with('success', 'Profile updated successfully!');
        } catch (\Exception $e) {
            \Log::error('Profile update failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return back()->withErrors(['error' => 'Failed to update profile. Please try again.'])->withInput();
        }
    }

    /**
     * Update user password
     */
    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        // Debug logging
        \Log::info('Password update request received', [
            'user_id' => $user->id,
            'email' => $user->email,
            'request_data' => $request->all(),
            'current_password_provided' => !empty($request->current_password),
            'new_password_provided' => !empty($request->password),
            'password_confirmation_provided' => !empty($request->password_confirmation)
        ]);

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            \Log::warning('Password update validation failed', [
                'user_id' => $user->id,
                'errors' => $validator->errors()->toArray()
            ]);
            return back()->withErrors($validator->errors())->withInput();
        }

        // Check if current password is correct
        $currentPasswordValid = Hash::check($request->current_password, $user->password);
        \Log::info('Current password validation', [
            'user_id' => $user->id,
            'current_password_valid' => $currentPasswordValid,
            'provided_password' => $request->current_password,
            'stored_hash' => $user->password
        ]);

        if (!$currentPasswordValid) {
            \Log::warning('Password update failed - incorrect current password', [
                'user_id' => $user->id,
                'provided_password' => $request->current_password
            ]);
            return back()->withErrors(['current_password' => 'The current password is incorrect.'])->withInput();
        }

        try {
            $oldPasswordHash = $user->password;
            $user->update([
                'password' => Hash::make($request->password),
            ]);

            \Log::info('Password updated successfully', [
                'user_id' => $user->id,
                'old_hash' => $oldPasswordHash,
                'new_hash' => $user->fresh()->password,
                'new_password_verified' => Hash::check($request->password, $user->fresh()->password)
            ]);

            return back()->with('message', 'Password updated successfully!');
        } catch (\Exception $e) {
            \Log::error('Password update failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return back()->withErrors(['error' => 'Failed to update password. Please try again.'])->withInput();
        }
    }

    /**
     * Validate current password
     */
    public function validateCurrentPassword(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $isValid = Hash::check($request->current_password, $user->password);

        return response()->json([
            'success' => true,
            'valid' => $isValid,
            'message' => $isValid ? 'Current password is correct' : 'Current password is incorrect'
        ]);
    }
}

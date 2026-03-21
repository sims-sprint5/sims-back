<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    private function ensureSpatieRole(User $user): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('model_has_roles')) {
            return;
        }

        if ($user->roles()->exists()) {
            return;
        }

        // Spatie roles for tenant Users are stored under the 'web' guard.
        // This must not depend on auth.defaults.guard.
        $guardName = 'web';
        $legacyRole = strtolower((string) ($user->role ?? ''));

        $roleName = $legacyRole === 'admin' ? 'Admin' : 'Usuario';

        $role = Role::findOrCreate($roleName, $guardName);
        $user->assignRole($role);
    }

    /**
     * Register a new user.
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'role' => 'nullable|in:admin,manager,user,technical',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            // Public registration is for normal users only.
            // Keep accepting the input field for backward compatibility, but ignore it.
            'role' => 'user',
            'status' => 'active',
        ]);

        // Backfill/assign Spatie role based on the existing legacy role field.
        $this->ensureSpatieRole($user);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully',
            'data' => [
                'user' => [
                    'user_id' => $user->user_id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'phone' => $user->phone,
                    'status' => $user->status,
                ],
                'access_token' => $token,
                'token_type' => 'Bearer',
            ],
        ], 201);
    }

    /**
     * Authenticate a user and return a token.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        try {
            $user = User::where('email', $request->email)->first();

            if (! $user || ! Hash::check($request->password, $user->password)) {
                throw ValidationException::withMessages([
                    'email' => ['The provided credentials are incorrect.'],
                ]);
            }

            if ($user->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Account is inactive. Please contact your administrator.',
                ], 403);
            }

            // Ensure the user has a Spatie role before issuing a token.
            // This prevents unexpected 403 when routes are protected by role middleware.
            $this->ensureSpatieRole($user);

            // If tenant migrations are incomplete, avoid opaque 500 and return actionable error.
            if (! Schema::hasTable('personal_access_tokens')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant database is not initialized yet. Please contact support.',
                ], 503);
            }

            // Revoke all previous tokens before issuing a new one.
            $user->tokens()->delete();

            $token = $user->createToken('auth_token')->plainTextToken;
        } catch (QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant database is not initialized yet. Please contact support.',
            ], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => [
                    'user_id' => $user->user_id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'phone' => $user->phone,
                    'status' => $user->status,
                ],
                'access_token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    /**
     * Revoke the current access token (logout).
     */
    public function logout(Request $request)
    {
        // Revoke only the current token.
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Return the authenticated user's profile.
     */
    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => $user->user_id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'phone' => $user->phone,
                'status' => $user->status,
            ],
        ]);
    }

    /**
     * Update the authenticated user's profile (currently only name).
     */
    public function updateMe(Request $request)
    {
        $validated = $request->validate([
            // DB column is name(100); treated as username/display name.
            'name' => 'required|string|min:2|max:100',
        ]);

        $user = $request->user();
        $user->forceFill([
            'name' => $validated['name'],
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'User name updated successfully',
            'data' => [
                'user_id' => $user->user_id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'phone' => $user->phone,
                'status' => $user->status,
            ],
        ]);
    }

    /**
     * Revoke all tokens for the authenticated user (logout from all devices).
     */
    public function logoutAll(Request $request)
    {
        // Revoke every token belonging to this user.
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'All sessions logged out successfully',
        ]);
    }

    /**
     * Change the authenticated user's password.
     */
    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($validated['new_password']),
        ]);

        // Revoke all other tokens except the current one.
        $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully',
        ]);
    }
}

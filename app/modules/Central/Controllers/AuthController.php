<?php

namespace App\Modules\Central\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Central\Models\GlobalAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login central admin
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        /** @var GlobalAdmin|null $admin */
        $admin = GlobalAdmin::where('email', $request->email)->first();

        if (! $admin || ! Hash::check($request->password, $admin->password)) {
            throw ValidationException::withMessages([
                'email' => ['The credentials are incorrect.'],
            ]);
        }

        // Crear token Sanctum con guard central
        $token = $admin->createToken('api-token', ['central'])->plainTextToken;

        return response()->json([
            'message' => 'Login successful!',
            'token' => $token,
            'admin' => $admin,
        ]);
    }

    /**
     * Logout central admin
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully!',
        ]);
    }

    /**
     * Get current authenticated central admin
     */
    public function me(Request $request)
    {
        return response()->json([
            'admin' => $request->user(),
        ]);
    }
}

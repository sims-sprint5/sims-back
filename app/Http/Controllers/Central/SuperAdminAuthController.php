<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\SuperAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SuperAdminAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $superadmin = SuperAdmin::where('email', $request->email)->first();

        if (! $superadmin || ! Hash::check($request->password, $superadmin->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $token = $superadmin->createToken('superadmin-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'token' => $token,
            'access_token' => $token,
            'token_type' => 'Bearer',
            'superadmin' => $superadmin,
            'data' => [
                'superadmin' => $superadmin,
                'access_token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}

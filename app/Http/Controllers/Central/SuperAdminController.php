<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\SuperAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SuperAdminController extends Controller
{
    public function index()
    {
        return response()->json(
            SuperAdmin::select('id', 'name', 'email', 'created_at')->get()
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:superadmins,email',
            'password' => 'required|string|min:8',
        ]);

        $admin = SuperAdmin::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'message' => 'SuperAdmin created',
            'admin'   => $admin->only('id', 'name', 'email', 'created_at'),
        ], 201);
    }

    public function destroy(string $id)
    {
        $admin = SuperAdmin::findOrFail($id);

        if ($admin->email === env('SUPERADMIN_EMAIL')) {
            return response()->json([
                'message' => 'The primary SuperAdmin cannot be deleted.',
            ], 403);
        }

        $admin->delete();
        return response()->json(['message' => 'SuperAdmin deleted successfully.']);
    }
}

<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function index()
    {
        return response()->json(Tenant::with('domains')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'id'     => 'required|string|alpha_dash|unique:tenants,id',
            'name'   => 'required|string|max:255',
        ]);

        // Create tenant → automatically triggers CreateDatabase + MigrateDatabase
        $tenant = Tenant::create([
            'id'   => $request->id,
            'name' => $request->name,
        ]);

        // Add subdomain
        $tenant->domains()->create(['domain' => $request->id]);

        return response()->json([
            'message' => 'Tenant creat correctament',
            'tenant'  => $tenant->load('domains'),
        ], 201);
    }

    public function destroy(string $id)
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->delete(); // Automatically triggers DeleteDatabase

        return response()->json(['message' => 'Tenant deleted correctly.']);
    }
}

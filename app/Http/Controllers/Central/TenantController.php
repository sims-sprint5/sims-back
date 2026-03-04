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
            'id'             => 'required|string|alpha_dash|unique:tenants,id|max:50',
            'name'           => 'required|string|max:255',
            'admin_name'     => 'nullable|string|max:255',
            'admin_email'    => 'nullable|email|max:255',
            'admin_password' => 'nullable|string|min:8',
        ]);

        $tenantId = $request->id;

        // 'name' must live inside the 'data' JSON column.
        // Passing it as a top-level key alongside 'data' causes Eloquent to
        // overwrite the JSON that stancl already set, so 'name' is lost.
        $tenant = Tenant::create([
            'id'   => $tenantId,
            'data' => [
                'name'           => $request->name,
                'admin_name'     => $request->admin_name     ?? 'Admin ' . ucfirst($tenantId),
                'admin_email'    => $request->admin_email    ?? "admin@{$tenantId}.local",
                'admin_password' => $request->admin_password ?? '',
            ],
        ]);

        $tenant->domains()->create(['domain' => $tenantId]);

        return response()->json([
            'message' => 'Tenant creat correctament',
            'tenant'  => $tenant->load('domains'),
            'access'  => [
                'url'         => "http://{$tenantId}.localhost:8000",
                'admin_email' => $tenant->data['admin_email'],
            ],
        ], 201);
    }

    public function show(string $id)
    {
        $tenant = Tenant::with('domains')->findOrFail($id);
        return response()->json($tenant);
    }

    public function destroy(string $id)
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->delete();
        return response()->json(['message' => "Tenant '{$id}' eliminat correctament."]);
    }
}

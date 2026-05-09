<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;

class CertificateController extends Controller
{
    public function generateAll(Request $request)
    {
        // Validate that the request comes from the local host (security)
        if ($request->ip() !== '127.0.0.1' && $request->ip() !== 'localhost') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Execute the command
        $kernel = app(Kernel::class);
        $kernel->call('tenants:generate-certificates');

        return response()->json([
            'message' => 'SSL certificates generation completed',
        ]);
    }
}

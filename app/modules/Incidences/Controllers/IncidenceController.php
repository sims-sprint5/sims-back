<?php

namespace App\Modules\Incidences\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Incidences\Models\Incidence;
use App\Modules\Incidences\Requests\CreateIncidenceRequest;
use App\Modules\Incidences\Requests\UpdateIncidenceRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class IncidenceController extends Controller
{
    /**
     * GET - List all active incidences.
     */
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Incidence::class);

        $incidences = Incidence::query()->latest()->paginate(10);

        return response()->json([
            'message' => 'Incidences retrieved successfully',
            'data' => $incidences,
        ]);
    }

    /**
     * POST - Create a new incidence.
     */
    public function store(CreateIncidenceRequest $request): JsonResponse
    {
        $this->authorize('create', Incidence::class);
        $validatedData = $request->validated();

        $status = $validatedData['status'] ?? 'reported';
        $resolvedAt = in_array($status, ['resolved', 'closed']) ? now() : null;

        $incidence = Incidence::create([
            'reported_by' => auth()->id(),
            'type' => $validatedData['type'],
            'severity' => $validatedData['severity'],
            'description' => $validatedData['description'],
            'status' => $status,
            'resolution_notes' => $validatedData['resolution_notes'] ?? null,
            'resolved_at' => $resolvedAt,
        ]);

        return response()->json([
            'message' => 'Incidence created successfully',
            'data' => $incidence,
        ], 201);
    }

    /**
     * GET {id} - Show specific incidence.
     */
    public function show($id): JsonResponse
    {
        $incidence = Incidence::find($id);

        if (! $incidence) {
            throw new NotFoundHttpException('Incidence not found');
        }

        $this->authorize('view', $incidence);

        return response()->json([
            'message' => 'Incidence retrieved successfully',
            'data' => $incidence,
        ]);
    }

    /**
     * PATCH/PUT - Update incidence details.
     */
    public function update(UpdateIncidenceRequest $request, $id): JsonResponse
    {
        $incidence = Incidence::find($id);

        if (! $incidence) {
            throw new NotFoundHttpException('Incidence not found');
        }

        $this->authorize('update', $incidence);

        $validated = $request->validated();

        // Handle resolution timestamp
        if (isset($validated['status'])) {
            if (in_array($validated['status'], ['resolved', 'closed'])) {
                $validated['resolved_at'] = $incidence->resolved_at ?? now();
            } else {
                $validated['resolved_at'] = null;
            }
        }

        // Update attributes
        $incidence->update($validated);

        return response()->json([
            'message' => 'Incidence updated successfully',
            'data' => $incidence,
        ]);
    }

    /**
     * DELETE - Soft delete incidence.
     */
    public function destroy($id): JsonResponse
    {
        $incidence = Incidence::find($id);

        if (! $incidence) {
            throw new NotFoundHttpException('Incidence not found');
        }

        $this->authorize('delete', $incidence);

        $incidence->delete();

        return response()->json([
            'message' => 'Incidence deleted successfully',
        ]);
    }

    /**
     * GET - List trashed incidences.
     */
    public function trashed(): JsonResponse
    {
        $this->authorize('viewAny', Incidence::class);

        $incidences = Incidence::onlyTrashed()->latest()->paginate(10);

        return response()->json([
            'message' => 'Trashed incidences retrieved successfully',
            'data' => $incidences,
        ]);
    }
}

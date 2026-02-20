<?php

namespace App\Modules\Vehicle\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Vehicle\Models\Vehicle;
use App\Modules\Vehicle\Requests\CreateVehicleRequest;
use App\Modules\Vehicle\Requests\UpdateVehicleRequest;
use App\Modules\Vehicle\Resources\VehicleResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class VehicleController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Vehicle::class);

        $vehicles = Vehicle::paginate(10);

        return response()->json([
            'message' => 'Vehicles retrieved successfully',
            'data' => VehicleResource::collection($vehicles),
        ]);
    }

    public function show($vehicleId): JsonResponse
    {
        $vehicle = Vehicle::find($vehicleId);

        if (! $vehicle) {
            throw new NotFoundHttpException('Vehicle not found');
        }

        $this->authorize('view', $vehicle);

        return response()->json([
            'message' => 'Vehicle retrieved successfully',
            'data' => VehicleResource::make($vehicle),
        ]);
    }

    public function store(CreateVehicleRequest $request): JsonResponse
    {
        $this->authorize('create', Vehicle::class);

        $validated = $request->validated();

        $vehicle = Vehicle::create($validated);

        return response()->json([
            'message' => 'Vehicle created successfully',
            'data' => VehicleResource::make($vehicle),
        ], 201);
    }

    public function update(UpdateVehicleRequest $request, $vehicleId): JsonResponse
    {
        $vehicle = Vehicle::find($vehicleId);

        if (! $vehicle) {
            throw new NotFoundHttpException('Vehicle not found');
        }

        $this->authorize('update', $vehicle);

        $validated = $request->validated();

        $vehicle->update($validated);

        return response()->json([
            'message' => 'Vehicle updated successfully',
            'data' => VehicleResource::make($vehicle),
        ]);
    }

    public function destroy($vehicleId): JsonResponse
    {
        $vehicle = Vehicle::find($vehicleId);

        if (! $vehicle) {
            throw new NotFoundHttpException('Vehicle not found');
        }

        $this->authorize('delete', $vehicle);

        $vehicle->delete();

        return response()->json([
            'message' => 'Vehicle deleted successfully',
        ], 200);
    }
}

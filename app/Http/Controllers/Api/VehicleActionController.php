<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class VehicleActionController extends Controller
{
    public function latestTemperature(Request $request, Reservation $reservation)
    {
        $user = $request->user();
        if ($reservation->user_id !== $user->getKey() && ! $user->hasRole('Admin')) {
            return response()->json(['message' => 'No tens permís.'], 403);
        }

        $apiKey = env('VEHICLE_SUBSYSTEM_KEY', 'subsistemaequip2');
        $baseUrl = rtrim(env('VEHICLE_SUBSYSTEM_URL', 'http://localhost:8088'), '/');

        try {
            $response = Http::withHeaders(['X-API-Key' => $apiKey])
                ->get("{$baseUrl}/api/laravel/temperatures/latest");

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json(['message' => 'No hi ha dades de temperatura disponibles.'], $response->status());
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error connectant amb el subsistema.', 'error' => $e->getMessage()], 500);
        }
    }

    public function toggleAction(Request $request, Reservation $reservation, string $action)
    {
        if (! in_array($action, ['on', 'off'])) {
            return response()->json(['message' => "L'acció ha de ser 'on' o 'off'"], 400);
        }

        $user = $request->user();
        if ($reservation->user_id !== $user->getKey() && ! $user->hasRole('Admin')) {
            return response()->json(['message' => 'No tens permís per interactuar amb aquesta reserva.'], 403);
        }

        if (in_array($reservation->status, ['cancelled', 'cancelada'])) {
            return response()->json(['message' => 'No pots interactuar amb una reserva cancel·lada.'], 403);
        }

        $now = now();
        if ($now->lt($reservation->start_date) || $now->gt($reservation->end_date)) {
            return response()->json(['message' => 'Només pots interactuar amb el vehicle durant el període assignat de la reserva.'], 403);
        }

        $vehicleIdentifier = str_pad((string) $reservation->vehicle_id, 3, '0', STR_PAD_LEFT);
        $apiKey = env('VEHICLE_SUBSYSTEM_KEY', 'subsistemaequip2');
        $baseUrl = rtrim(env('VEHICLE_SUBSYSTEM_URL', 'http://localhost:8088'), '/');
        $url = "{$baseUrl}/api/actuator/{$vehicleIdentifier}/{$action}";

        try {
            $response = Http::withHeaders([
                'X-API-Key' => $apiKey,
            ])->post($url);

            if ($response->successful()) {
                return response()->json(['message' => "Acció '{$action}' aplicada correctament al vehicle."]);
            }

            return response()->json([
                'message' => 'El subsistema del vehicle ha retornat un error.',
                'details' => $response->json() ?? $response->body(),
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error de connexió establint comunicació amb el vehicle.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

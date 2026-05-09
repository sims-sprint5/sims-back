<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class VehicleActionController extends Controller
{
    /**
     * Envia una acció (on/off) al vehicle d'una reserva.
     */
    public function toggleAction(Request $request, Reservation $reservation, string $action)
    {
        // Validar que l'acció sigui permesa
        if (! in_array($action, ['on', 'off'])) {
            return response()->json(['message' => "L'acció ha de ser 'on' o 'off'"], 400);
        }

        // Comprovar si l'usuari autenticat és el propietari d'aquesta reserva (o és Admin)
        $user = $request->user();
        if ($reservation->user_id !== $user->getKey() && ! $user->hasRole('Admin')) {
            return response()->json(['message' => 'No tens permís per interactuar amb aquesta reserva.'], 403);
        }

        // Verificacions de la reserva: Dins del període establert
        $now = now();

        if ($now->lt($reservation->start_date) || $now->gt($reservation->end_date)) {
            return response()->json(['message' => 'Només pots interactuar amb el vehicle durant el període assignat de la reserva.'], 403);
        }

        // Substituirem el '001' per $reservation->vehicle_id en el futur
        $vehicleIdentifier = '001';

        // Obtenir l'API KEY del subsistema des del .env (via config)
        $apiKey = env('VEHICLE_SUBSYSTEM_KEY', 'subsistemaequip2');

        $url = "http://localhost:8088/api/actuator/{$vehicleIdentifier}/{$action}";

        try {
            // Realitzar la petició HTTP POST (Proxy) al subsistema del vehicle
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

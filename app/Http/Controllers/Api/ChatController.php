<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    /**
     * Send message to Groq AI chatbot and get response.
     *
     * Requires authentication. Returns AI-generated response with context
     * about vehicle reservation system.
     */
    public function ask(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        $user = $request->user();
        $message = $request->string('message');

        // System prompt with context about the business
        $systemPrompt = <<<'PROMPT'
Eres un asistente de soporte para una plataforma de reserva de vehículos llamada SIMS.

**FUNCIONALIDADES DEL SISTEMA:**
- Usuarios pueden crear, modificar y cancelar reservas de vehículos
- Cada reserva tiene: vehículo, fecha inicio, fecha fin, ubicación recogida/entrega
- Estados de reserva: pending (pendiente), active (activa), completed (completada), cancelled (cancelada)
- Los usuarios pueden ver todas sus reservas en "Mis Reservas"
- Pueden extender reservas modificando la fecha de fin
- El sistema verifica automáticamente disponibilidad de vehículos

**CÓMO AYUDAR:**
1. Si preguntan sobre CREAR RESERVA → Explica que vayan a Mapa, seleccionen vehículo, fechas
2. Si preguntan sobre MODIFICAR RESERVA → Diles que vayan a "Mis Reservas", seleccionen reserva, "Modificar Fecha"
3. Si preguntan sobre CANCELAR → En "Mis Reservas", botón "Cancelar Reserva" (solo si pending y no empezó)
4. Si preguntan sobre DISPONIBILIDAD → Explica que el mapa muestra solo vehículos disponibles

**INSTRUCCIONES IMPORTANTES:**
- Siempre sugiere usar los botones/interfaces en lugar de comandos
- Si no sabes algo sobre el sistema, di que consulten con soporte
- Sé amable, profesional, conciso y en español
- Limita respuestas a máximo 2-3 párrafos

**CONTEXTO DEL USUARIO:**
- Usuario: {user_name}
- Email: {user_email}
PROMPT;

        $systemPrompt = str_replace(
            ['{user_name}', '{user_email}'],
            [$user->name, $user->email],
            $systemPrompt
        );

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.env('GROQ_API_KEY'),
            ])->timeout(30)->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => env('GROQ_MODEL', 'openai/gpt-oss-120b'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt,
                    ],
                    [
                        'role' => 'user',
                        'content' => $message,
                    ],
                ],
                'temperature' => 0.7,
                'max_tokens' => 1000,
            ]);

            if ($response->failed()) {
                $errorMsg = (string) ($response->json('error.message') ?? 'Groq API error');
                Log::error('Groq API failed.', [
                    'status' => $response->status(),
                    'message' => $errorMsg,
                ]);

                return response()->json([
                    'error' => 'Error al conectar con el servei de xat. Torna-ho a provar mes tard.',
                    'status' => $response->status(),
                ], 500);
            }

            $content = $response['choices'][0]['message']['content'] ?? 'Sin respuesta';

            return response()->json([
                'message' => $content,
                'timestamp' => now()->toIso8601String(),
            ]);

        } catch (\Exception $e) {
            Log::error('Chatbot error.', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error al connectar amb el servei de xat. Torna-ho a provar mes tard.',
            ], 500);
        }
    }
}

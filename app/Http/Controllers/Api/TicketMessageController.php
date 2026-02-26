<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Http\Request;

class TicketMessageController extends Controller
{
    public function index(Request $request, Ticket $ticket)
    {
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min(100, $perPage));

        $messages = TicketMessage::query()
            ->where('ticket_id', $ticket->getKey())
            ->orderBy('created_at', 'asc')
            ->simplePaginate($perPage);

        return response()->json($messages);
    }

    public function store(Request $request, Ticket $ticket)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $userId = $user->getKey();

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:5000', 'regex:/\\S/'],
        ]);

        $ticketMessage = TicketMessage::create([
            'ticket_id' => $ticket->getKey(),
            'sender_id' => $userId,
            'message' => $validated['message'],
        ]);

        $ticketMessage->refresh();

        return response()->json($ticketMessage, 201);
    }
}

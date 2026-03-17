<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Http\Request;

class TicketMessageController extends Controller
{
    /**
     * GET /v1/tickets/{ticket}/messages
     * Returns the ticket message history with user name and is_admin status.
     */
    public function index(Request $request, Ticket $ticket)
    {
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min(100, $perPage));

        $messages = $ticket->messages()
            ->with('user')
            ->orderBy('created_at', 'asc')
            ->simplePaginate($perPage);

        $transformed = $messages->through(function ($msg) {
            return [
                'id'         => $msg->getKey(),
                'ticket_id'  => $msg->ticket_id,
                'user_id'    => $msg->sender_id,
                'message'    => $msg->message,
                'is_admin'   => (bool) $msg->is_admin,
                'created_at' => $msg->created_at,
                'user'       => ['name' => $msg->user?->name],
            ];
        });

        return response()->json($transformed);
    }

    /**
     * POST /v1/tickets/{ticket}/messages
     * Creates a message. user_id and is_admin are derived from the token, never from the body.
     */
    public function store(Request $request, Ticket $ticket)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:5000', 'regex:/\S/'],
        ]);

        $ticketMessage = TicketMessage::create([
            'ticket_id' => $ticket->getKey(),
            'sender_id' => $user->getKey(),          // always from the token
            'message'   => $validated['message'],
            'is_admin'  => $user->role === 'admin',  // calculated from the token
        ]);

        $ticketMessage->load('user');

        return response()->json([
            'id'         => $ticketMessage->getKey(),
            'ticket_id'  => $ticketMessage->ticket_id,
            'user_id'    => $ticketMessage->sender_id,
            'message'    => $ticketMessage->message,
            'is_admin'   => (bool) $ticketMessage->is_admin,
            'created_at' => $ticketMessage->created_at,
            'user'       => ['name' => $ticketMessage->user?->name],
        ], 201);
    }
}

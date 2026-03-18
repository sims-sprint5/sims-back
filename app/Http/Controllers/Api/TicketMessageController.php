<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Http\Request;

class TicketMessageController extends Controller
{
    private function isAdmin(Request $request): bool
    {
        $user = $request->user();

        return (bool) ($user?->hasRole('Admin') || strtolower((string) ($user?->role ?? '')) === 'admin');
    }

    private function assertCanAccessTicket(Request $request, Ticket $ticket)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $this->isAdmin($request) && (string) $ticket->user_id !== (string) $user->user_id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return null;
    }

    /**
     * GET /v1/tickets/{ticket}/messages
     * Returns the ticket message history with user name and is_admin status.
     */
    public function index(Request $request, Ticket $ticket)
    {
        if ($deny = $this->assertCanAccessTicket($request, $ticket)) {
            return $deny;
        }

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
        if ($deny = $this->assertCanAccessTicket($request, $ticket)) {
            return $deny;
        }

        $user = $request->user();

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:5000', 'regex:/\S/'],
        ]);

        $ticketMessage = TicketMessage::create([
            'ticket_id' => $ticket->getKey(),
            'sender_id' => $user->getKey(),          // always from the token
            'message'   => $validated['message'],
            'is_admin'  => $this->isAdmin($request),  // calculated from the token
            'created_at' => now(),
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

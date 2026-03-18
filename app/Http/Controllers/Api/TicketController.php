<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    private function isAdmin(Request $request): bool
    {
        $user = $request->user();

        return (bool) ($user?->hasRole('Admin') || strtolower((string) ($user?->role ?? '')) === 'admin');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = $request->integer('per_page', 15);
        $query = Ticket::query()->with(['user', 'vehicle', 'reservation', 'assignedUser']);

        if (! $this->isAdmin($request)) {
            $query->where('user_id', $request->user()->user_id);
        }

        $tickets = $query->paginate($perPage);

        return response()->json($tickets);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,user_id',
            'vehicle_id' => 'nullable|exists:vehicles,vehicle_id',
            'reservation_id' => 'nullable|exists:reservations,reservation_id',
            'type' => 'required|string|in:technical,billing,complaint,inquiry',
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'required|string|in:low,medium,high,urgent',
            'status' => 'nullable|string|in:open,in_progress,resolved,closed',
            'assigned_to' => 'nullable|exists:users,user_id',
        ]);

        if (! $this->isAdmin($request)) {
            $validated['user_id'] = $request->user()->user_id;
            unset($validated['assigned_to'], $validated['status']);
        } else {
            if (empty($validated['user_id'])) {
                return response()->json(['message' => 'user_id is required for admin.'], 422);
            }
        }

        $ticket = Ticket::create($validated);

        return response()->json($ticket->load(['user', 'vehicle', 'assignedUser']), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $ticket = Ticket::with(['user', 'vehicle', 'reservation', 'assignedUser'])->findOrFail($id);

        if (! request()->user()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $this->isAdmin(request()) && $ticket->user_id !== request()->user()->user_id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json($ticket);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $ticket = Ticket::findOrFail($id);

        $validated = $request->validate([
            'user_id' => 'sometimes|exists:users,user_id',
            'vehicle_id' => 'nullable|exists:vehicles,vehicle_id',
            'reservation_id' => 'nullable|exists:reservations,reservation_id',
            'type' => 'sometimes|string|in:technical,billing,complaint,inquiry',
            'subject' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'priority' => 'sometimes|string|in:low,medium,high,urgent',
            'status' => 'sometimes|string|in:open,in_progress,resolved,closed',
            'assigned_to' => 'nullable|exists:users,user_id',
        ]);

        $ticket->update($validated);

        return response()->json($ticket->load(['user', 'vehicle', 'assignedUser']));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $ticket = Ticket::findOrFail($id);
        $ticket->delete();

        return response()->json(['message' => 'Ticket deleted successfully'], 200);
    }

    /**
     * Get tickets by user.
     */
    public function byUser(string $userId)
    {
        $request = request();

        if (! $request->user()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $this->isAdmin($request) && (string) $request->user()->user_id !== (string) $userId) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $tickets = Ticket::where('user_id', $userId)
            ->with(['vehicle', 'reservation', 'assignedUser'])
            ->get();

        return response()->json($tickets);
    }

    /**
     * Assign ticket to a user.
     */
    public function assign(Request $request, string $id)
    {
        $ticket = Ticket::findOrFail($id);

        $validated = $request->validate([
            'assigned_to' => 'required|exists:users,user_id',
        ]);

        $ticket->update($validated);

        return response()->json($ticket->load('assignedUser'));
    }

    /**
     * Update ticket status.
     */
    public function updateStatus(Request $request, string $id)
    {
        $ticket = Ticket::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|string|in:open,in_progress,resolved,closed',
        ]);

        $ticket->update($validated);

        return response()->json($ticket);
    }
}

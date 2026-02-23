<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $tickets = Ticket::with(['user', 'vehicle', 'reservation', 'assignedUser'])->get();
        return response()->json($tickets);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,user_id',
            'vehicle_id' => 'nullable|exists:vehicles,vehicle_id',
            'reservation_id' => 'nullable|exists:reservations,reservation_id',
            'type' => 'required|string|in:technical,billing,complaint,inquiry',
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'required|string|in:low,medium,high,urgent',
            'status' => 'nullable|string|in:open,in_progress,resolved,closed',
            'assigned_to' => 'nullable|exists:users,user_id',
        ]);

        $ticket = Ticket::create($validated);
        
        return response()->json($ticket->load(['user', 'vehicle', 'assignedUser']), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $ticket = Ticket::with(['user', 'vehicle', 'reservation', 'assignedUser'])->findOrFail($id);
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
        
        return response()->json(['message' => 'Ticket eliminado correctamente'], 200);
    }

    /**
     * Get tickets by user.
     */
    public function byUser(string $userId)
    {
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

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    private const ALLOWED_PRIORITIES = ['alta', 'mitjana', 'baixa'];

    private const LEGACY_PRIORITY_MAP = [
        'low' => 'baixa',
        'medium' => 'mitjana',
        'high' => 'alta',
    ];

    private const ALLOWED_STATUSES = ['obert', 'en_progres', 'finalitzat'];

    private const LEGACY_STATUS_MAP = [
        'open' => 'obert',
        'in_progress' => 'en_progres',
        'resolved' => 'finalitzat',
        'closed' => 'finalitzat',
    ];

    private const DEFAULT_PRIORITY = 'baixa';

    private const DEFAULT_STATUS = 'obert';

    private function isAdmin(Request $request): bool
    {
        $user = $request->user();

        return (bool) ($user?->hasRole('Admin') || strtolower((string) ($user?->role ?? '')) === 'admin');
    }

    private function normalizePriority(?string $priority): ?string
    {
        if ($priority === null) {
            return null;
        }

        return self::LEGACY_PRIORITY_MAP[$priority] ?? $priority;
    }

    private function normalizeStatus(?string $status): ?string
    {
        if ($status === null) {
            return null;
        }

        return self::LEGACY_STATUS_MAP[$status] ?? $status;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = $request->integer('per_page', 15);
        $query = Ticket::query()->with(['user', 'vehicle', 'reservation', 'assignedUser']);

        $isAdmin = $this->isAdmin($request);

        if (! $isAdmin) {
            $query->where('user_id', $request->user()->user_id);
        }

        $tickets = $query->paginate($perPage);

        if (! $isAdmin) {
            $tickets->getCollection()->transform(function (Ticket $ticket) {
                return $ticket->makeHidden('priority');
            });
        }

        return response()->json($tickets);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $isAdmin = $this->isAdmin($request);

        if (! $isAdmin) {
            $validated = $request->validate([
                'vehicle_id' => 'nullable|exists:vehicles,vehicle_id',
                'reservation_id' => 'nullable|exists:reservations,reservation_id',
                'type' => 'required|string|in:technical,billing,complaint,inquiry',
                'subject' => 'required|string|max:255',
                'description' => 'required|string',
            ]);

            $validated['user_id'] = $request->user()->user_id;
            $validated['priority'] = self::DEFAULT_PRIORITY;
            $validated['status'] = self::DEFAULT_STATUS;
        } else {
            $validated = $request->validate([
                'user_id' => 'nullable|exists:users,user_id',
                'vehicle_id' => 'nullable|exists:vehicles,vehicle_id',
                'reservation_id' => 'nullable|exists:reservations,reservation_id',
                'type' => 'required|string|in:technical,billing,complaint,inquiry',
                'subject' => 'required|string|max:255',
                'description' => 'required|string',
                'priority' => 'nullable|string|in:'.implode(',', array_merge(self::ALLOWED_PRIORITIES, array_keys(self::LEGACY_PRIORITY_MAP))),
                'status' => 'nullable|string|in:'.implode(',', array_merge(self::ALLOWED_STATUSES, array_keys(self::LEGACY_STATUS_MAP))),
                'assigned_to' => 'nullable|exists:users,user_id',
            ]);

            if (empty($validated['user_id'])) {
                return response()->json(['message' => 'user_id is required for admin.'], 422);
            }

            $validated['priority'] = $this->normalizePriority($validated['priority'] ?? null) ?? self::DEFAULT_PRIORITY;
            $validated['status'] = $this->normalizeStatus($validated['status'] ?? null) ?? self::DEFAULT_STATUS;
        }

        $ticket = Ticket::create($validated);

        $ticket->load(['user', 'vehicle', 'reservation', 'assignedUser']);

        if (! $isAdmin) {
            $ticket->makeHidden('priority');
        }

        return response()->json($ticket, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $ticket = Ticket::with(['user', 'vehicle', 'reservation', 'assignedUser'])->findOrFail($id);

        if (! $request->user()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $this->isAdmin($request) && $ticket->user_id !== $request->user()->user_id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if (! $this->isAdmin($request)) {
            $ticket->makeHidden('priority');
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
            'priority' => 'sometimes|string|in:'.implode(',', array_merge(self::ALLOWED_PRIORITIES, array_keys(self::LEGACY_PRIORITY_MAP))),
            'status' => 'sometimes|string|in:'.implode(',', array_merge(self::ALLOWED_STATUSES, array_keys(self::LEGACY_STATUS_MAP))),
            'assigned_to' => 'nullable|exists:users,user_id',
        ]);

        if (array_key_exists('priority', $validated)) {
            $validated['priority'] = $this->normalizePriority($validated['priority']);
        }

        if (array_key_exists('status', $validated)) {
            $validated['status'] = $this->normalizeStatus($validated['status']);
        }

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
    public function byUser(Request $request, string $userId)
    {
        if (! $request->user()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $this->isAdmin($request) && (string) $request->user()->user_id !== (string) $userId) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $tickets = Ticket::where('user_id', $userId)
            ->with(['vehicle', 'reservation', 'assignedUser'])
            ->get();

        if (! $this->isAdmin($request)) {
            $tickets->transform(function (Ticket $ticket) {
                return $ticket->makeHidden('priority');
            });
        }

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
            'status' => 'required|string|in:'.implode(',', array_merge(self::ALLOWED_STATUSES, array_keys(self::LEGACY_STATUS_MAP))),
        ]);

        $validated['status'] = $this->normalizeStatus($validated['status']) ?? self::DEFAULT_STATUS;

        $ticket->update($validated);

        return response()->json($ticket);
    }
}

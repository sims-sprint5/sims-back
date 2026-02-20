<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IncidenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'incidentNumber' => $this->incident_number,

            // Relationships
            'reportedBy' => $this->reported_by,
            'reporter' => $this->whenLoaded('reporter'),

            // Main data
            'type' => $this->type,
            'description' => $this->description,
            'severity' => $this->severity,
            'status' => $this->status,

            // Format dates to ISO8601 safely
            'occurredAt' => $this->occurred_at?->toIso8601String(),
            'resolvedAt' => $this->resolved_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),

            // Resolution info
            'resolutionNotes' => $this->resolution_notes, // This maps DB resolution_notes
            'metadata' => $this->metadata,
        ];
    }
}

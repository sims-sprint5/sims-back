<?php

namespace App\Modules\Vehicle\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'license_plate' => $this->license_plate,
            'vin' => $this->vin,
            'brand' => $this->brand,
            'model' => $this->model,
            'type' => $this->type,
            'status' => $this->status,
            'year' => $this->year,
            'color' => $this->color,
            'battery_level' => $this->battery_level,
            'range_km' => $this->range_km,
            'price_per_minute' => $this->price_per_minute,
            'price_per_hour' => $this->price_per_hour,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

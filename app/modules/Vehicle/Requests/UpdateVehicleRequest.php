<?php

namespace App\Modules\Vehicle\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVehicleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $vehicleId = $this->route('vehicleId');

        return [
            'license_plate' => 'sometimes|required|string|max:20|unique:vehicles,license_plate,'.$vehicleId,
            'vin' => 'nullable|string|max:17|unique:vehicles,vin,'.$vehicleId,
            'brand' => 'sometimes|required|string|max:100',
            'model' => 'sometimes|required|string|max:100',
            'type' => 'sometimes|required|in:scooter,bike,car,van',
            'status' => 'sometimes|in:available,in_use,maintenance,retired',
            'year' => 'nullable|integer|min:1900|max:'.date('Y'),
            'color' => 'nullable|string|max:50',
            'battery_level' => 'nullable|integer|min:0|max:100',
            'range_km' => 'nullable|integer|min:0',
            'price_per_minute' => 'nullable|numeric|min:0',
            'price_per_hour' => 'nullable|numeric|min:0',
            'metadata' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'license_plate.unique' => 'This license plate already exists',
            'vin.unique' => 'This VIN already exists',
            'type.in' => 'Invalid vehicle type',
            'battery_level.max' => 'Battery level cannot exceed 100%',
        ];
    }
}

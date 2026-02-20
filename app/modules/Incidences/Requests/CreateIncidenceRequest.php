<?php

namespace App\Modules\Incidences\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateIncidenceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'type' => 'required|string|in:Technical,Maintenance,UserComplaint,Accident,other',
            'description' => 'required|string|min:10',
            'severity' => 'required|string|in:low,medium,high,critical',

            'status' => 'nullable|string|in:reported,investigating,resolved,closed',
            'resolution_notes' => 'nullable|string',
        ];
    }

    /**
     * Customize attribute names for error messages.
     */
    public function attributes(): array
    {
        return [
            'description' => 'description',
            'severity' => 'severity',
            'type' => 'incidence type',
            'status' => 'status',
        ];
    }
}

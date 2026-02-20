<?php

namespace App\Modules\Incidences\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIncidenceRequest extends FormRequest
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
            'status' => [
                'sometimes',
                Rule::in(['reported', 'investigating', 'resolved', 'closed']),
            ],
            'severity' => [
                'sometimes',
                Rule::in(['low', 'medium', 'high', 'critical']),
            ],
            'type' => [
                'sometimes',
                Rule::in(['Technical', 'Maintenance', 'UserComplaint', 'Accident', 'other']),
            ],
            'description' => 'sometimes|string|min:5',
            'resolution_notes' => [
                'nullable',
                'string',
                Rule::requiredIf(fn () => in_array($this->status, ['resolved', 'closed'])),
            ],
            'metadata' => 'nullable|array',
        ];
    }
}

<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConversationRequest extends FormRequest
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
            'assigned_user_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'status' => ['sometimes', 'in:open,closed'],
            'labels' => ['sometimes', 'array'],
            'labels.*' => ['exists:labels,id'],
        ];
    }
}

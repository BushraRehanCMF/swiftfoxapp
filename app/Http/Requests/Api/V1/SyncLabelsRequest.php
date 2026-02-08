<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class SyncLabelsRequest extends FormRequest
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
            'label_ids' => ['required', 'array'],
            'label_ids.*' => ['exists:labels,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'label_ids.required' => 'Label IDs are required.',
            'label_ids.array' => 'Label IDs must be an array.',
            'label_ids.*.exists' => 'One or more selected labels do not exist.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCampaignRequest extends FormRequest
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
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:10'],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string'],
            'valid_from' => [
                'nullable',
                'date',
                'required_with:valid_until',
            ],
            'valid_until' => [
                'nullable',
                'date',
                'required_with:valid_from',
                'after:valid_from',
            ],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'valid_from.required_with' => __('validation.campaign.valid_from_required_with'),
            'valid_until.required_with' => __('validation.campaign.valid_until_required_with'),
            'valid_until.after' => __('validation.campaign.valid_until_after'),
        ];
    }
}

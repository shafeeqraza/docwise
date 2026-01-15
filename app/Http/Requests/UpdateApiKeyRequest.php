<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateApiKeyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization handled by middleware - user must be company admin
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'permissions' => [
                'sometimes',
                'nullable',
                'array',
            ],
            'permissions.*' => [
                'string',
                'in:widget:chat,widget:read,*',
            ],
            'rate_limit_per_minute' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
                'max:10000',
            ],
            'rate_limit_per_hour' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
                'max:100000',
            ],
            'is_active' => [
                'sometimes',
                'nullable',
                'boolean',
            ],
            'expires_at' => [
                'sometimes',
                'nullable',
                'date',
                'after:now',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'API key name is required',
            'name.max' => 'API key name must not exceed 255 characters',
            'permissions.array' => 'Permissions must be an array',
            'permissions.*.in' => 'Invalid permission. Allowed: widget:chat, widget:read, *',
            'rate_limit_per_minute.integer' => 'Rate limit per minute must be an integer',
            'rate_limit_per_minute.min' => 'Rate limit per minute must be at least 1',
            'rate_limit_per_minute.max' => 'Rate limit per minute must not exceed 10000',
            'rate_limit_per_hour.integer' => 'Rate limit per hour must be an integer',
            'rate_limit_per_hour.min' => 'Rate limit per hour must be at least 1',
            'rate_limit_per_hour.max' => 'Rate limit per hour must not exceed 100000',
            'is_active.boolean' => 'Is active must be a boolean value',
            'expires_at.date' => 'Expires at must be a valid date',
            'expires_at.after' => 'Expires at must be in the future',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Trim name whitespace
        if ($this->has('name')) {
            $this->merge([
                'name' => trim($this->name),
            ]);
        }
    }
}

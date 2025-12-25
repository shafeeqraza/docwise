<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateCompanyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
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
                'required',
                'string',
                'max:255',
            ],
            'slug' => [
                'nullable',
                'string',
                'max:100',
                'unique:companies,slug',
            ],
            'email' => [
                'nullable',
                'string',
                'email',
                'max:255',
            ],
            'phone' => [
                'nullable',
                'string',
                'max:50',
            ],
            'status' => [
                'nullable',
                'string',
                'in:active,suspended,trial',
            ],
            'subscription_plan' => [
                'nullable',
                'string',
                'max:50',
            ],
            'billing_cycle' => [
                'nullable',
                'string',
                'in:monthly,yearly',
            ],
            'next_billing_date' => [
                'nullable',
                'date',
            ],
            'payment_status' => [
                'nullable',
                'string',
                'in:active,past_due,cancelled',
            ],
            'allow_overages' => [
                'nullable',
                'boolean',
            ],
            'settings' => [
                'nullable',
                'array',
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
            'name.required' => 'Company name is required',
            'name.max' => 'Company name must not exceed 255 characters',
            'slug.unique' => 'This slug is already taken',
            'slug.max' => 'Slug must not exceed 100 characters',
            'email.email' => 'Please provide a valid email address',
            'email.max' => 'Email address must not exceed 255 characters',
            'phone.max' => 'Phone number must not exceed 50 characters',
            'status.in' => 'Status must be one of: active, suspended, trial',
            'billing_cycle.in' => 'Billing cycle must be either monthly or yearly',
            'next_billing_date.date' => 'Next billing date must be a valid date',
            'payment_status.in' => 'Payment status must be one of: active, past_due, cancelled',
            'allow_overages.boolean' => 'Allow overages must be a boolean value',
            'settings.array' => 'Settings must be an array',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Trim email whitespace
        if ($this->has('email')) {
            $this->merge([
                'email' => $this->email ? strtolower(trim($this->email)) : null,
            ]);
        }

        // Trim name whitespace
        if ($this->has('name')) {
            $this->merge([
                'name' => trim($this->name),
            ]);
        }
    }
}

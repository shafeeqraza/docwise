<?php

namespace App\Http\Requests;

use App\Enums\CompanyBillingCycle;
use App\Enums\CompanyPaymentStatus;
use App\Enums\CompanyStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateCompanyRequest extends FormRequest
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
        $company = $this->route('company');

        // Build unique rule excluding current company
        $slugRule = ['sometimes', 'nullable', 'string', 'max:100'];

        if ($company) {
            // Try to find company to get ID for unique rule
            $companyModel = is_numeric($company)
                ? \App\Models\Company::find($company)
                : \App\Models\Company::where('uuid', $company)->first();

            if ($companyModel) {
                $slugRule[] = 'unique:companies,slug,' . $companyModel->id;
            } else {
                $slugRule[] = 'unique:companies,slug';
            }
        } else {
            $slugRule[] = 'unique:companies,slug';
        }

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'slug' => $slugRule,
            'email' => [
                'sometimes',
                'nullable',
                'string',
                'email',
                'max:255',
            ],
            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
            ],
            'status' => [
                'sometimes',
                'nullable',
                'string',
                Rule::enum(CompanyStatus::class),
            ],
            'subscription_plan' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
            ],
            'billing_cycle' => [
                'sometimes',
                'nullable',
                'string',
                Rule::enum(CompanyBillingCycle::class),
            ],
            'next_billing_date' => [
                'sometimes',
                'nullable',
                'date',
            ],
            'payment_status' => [
                'sometimes',
                'nullable',
                'string',
                Rule::enum(CompanyPaymentStatus::class),
            ],
            'allow_overages' => [
                'sometimes',
                'nullable',
                'boolean',
            ],
            'settings' => [
                'sometimes',
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
            'status.enum' => 'Status must be one of: ' . implode(', ', CompanyStatus::values()),
            'billing_cycle.enum' => 'Billing cycle must be one of: ' . implode(', ', CompanyBillingCycle::values()),
            'next_billing_date.date' => 'Next billing date must be a valid date',
            'payment_status.enum' => 'Payment status must be one of: ' . implode(', ', CompanyPaymentStatus::values()),
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

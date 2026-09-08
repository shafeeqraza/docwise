<?php

namespace App\Http\Requests;

use App\Enums\DocumentFileType;
use App\Enums\DocumentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListDocumentsRequest extends FormRequest
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
            'status' => [
                'nullable',
                'string',
                Rule::enum(DocumentStatus::class),
            ],
            'file_type' => [
                'nullable',
                'string',
                Rule::enum(DocumentFileType::class),
            ],
            'search' => [
                'nullable',
                'string',
                'max:255',
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
            'page' => [
                'nullable',
                'integer',
                'min:1',
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
            'status.enum' => 'Status must be one of: ' . implode(', ', DocumentStatus::values()),
            'file_type.enum' => 'File type must be one of: ' . implode(', ', DocumentFileType::values()),
            'search.max' => 'Search term must not exceed 255 characters',
            'per_page.integer' => 'Per page must be an integer',
            'per_page.min' => 'Per page must be at least 1',
            'per_page.max' => 'Per page must not exceed 100',
            'page.integer' => 'Page must be an integer',
            'page.min' => 'Page must be at least 1',
        ];
    }
}


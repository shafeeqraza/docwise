<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadDocumentRequest extends FormRequest
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
            'file' => [
                'required',
                'file',
                'mimes:pdf,docx,txt,html,md',
                'max:10240', // 10MB max
            ],
            'title' => [
                'nullable',
                'string',
                'max:500',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'tags' => [
                'nullable',
                'array',
            ],
            'tags.*' => [
                'string',
                'max:50',
            ],
            'language' => [
                'nullable',
                'string',
                'size:2',
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
            'file.required' => 'A file is required',
            'file.file' => 'The uploaded file is invalid',
            'file.mimes' => 'The file must be one of the following types: pdf, docx, txt, html, md',
            'file.max' => 'The file size must not exceed 10MB',
            'title.max' => 'Title must not exceed 500 characters',
            'tags.array' => 'Tags must be an array',
            'tags.*.string' => 'Each tag must be a string',
            'tags.*.max' => 'Each tag must not exceed 50 characters',
            'language.size' => 'Language code must be exactly 2 characters',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Trim title whitespace
        if ($this->has('title')) {
            $this->merge([
                'title' => $this->title ? trim($this->title) : null,
            ]);
        }

        // Trim description whitespace
        if ($this->has('description')) {
            $this->merge([
                'description' => $this->description ? trim($this->description) : null,
            ]);
        }

        // Ensure tags is an array
        if ($this->has('tags') && !is_array($this->tags)) {
            $this->merge([
                'tags' => [],
            ]);
        }
    }
}

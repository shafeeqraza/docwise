<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitFeedbackRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization handled by middleware - API key authentication
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
            'message_id' => [
                'required',
                'integer',
                'exists:chat_messages,id',
            ],
            'type' => [
                'required',
                'string',
                'in:thumbs_up,thumbs_down,rating',
            ],
            'rating' => [
                'nullable',
                'required_if:type,rating',
                'integer',
                'min:1',
                'max:5',
            ],
            'comment' => [
                'nullable',
                'string',
                'max:1000',
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
            'message_id.required' => 'Message ID is required',
            'message_id.exists' => 'Message not found',
            'type.required' => 'Feedback type is required',
            'type.in' => 'Feedback type must be one of: thumbs_up, thumbs_down, rating',
            'rating.required_if' => 'Rating is required when feedback type is rating',
            'rating.min' => 'Rating must be at least 1',
            'rating.max' => 'Rating must not exceed 5',
            'comment.max' => 'Comment must not exceed 1000 characters',
        ];
    }
}

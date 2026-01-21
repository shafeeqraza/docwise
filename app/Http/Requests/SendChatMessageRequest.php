<?php

namespace App\Http\Requests;

use App\Services\V1\Common\TokenEstimator;
use Illuminate\Foundation\Http\FormRequest;

class SendChatMessageRequest extends FormRequest
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
        $maxInputChars = config('chat.max_input_characters', 8000);

        return [
            'message' => [
                'required',
                'string',
                "max:{$maxInputChars}",
            ],
            'session_id' => [
                'nullable',
                'uuid',
            ],
            'user_metadata' => [
                'nullable',
                'array',
            ],
            'user_metadata.user_id' => [
                'nullable',
                'string',
                'max:255',
            ],
            'user_metadata.name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'user_metadata.email' => [
                'nullable',
                'email',
                'max:255',
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
        $maxInputChars = config('chat.max_input_characters', 8000);

        return [
            'message.required' => 'Message is required',
            'message.string' => 'Message must be a string',
            'message.max' => "Message must not exceed {$maxInputChars} characters",
            'session_id.uuid' => 'Session ID must be a valid UUID',
            'user_metadata.array' => 'User metadata must be an array',
            'user_metadata.email.email' => 'User email must be a valid email address',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * Uses FAST local estimation for validation.
     * Exact token counting happens later in ChatService for billing/limits.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->has('message')) {
                // Use fast local estimation (no API calls, ~1ms)
                $estimator = app(TokenEstimator::class);
                $estimatedTokens = $estimator->estimate($this->message);
                $maxInputTokens = config('chat.max_input_tokens', 2000);

                if ($estimatedTokens > $maxInputTokens) {
                    $validator->errors()->add(
                        'message',
                        "Message exceeds maximum token limit of {$maxInputTokens} tokens (estimated: {$estimatedTokens} tokens)"
                    );
                }
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Trim message whitespace
        if ($this->has('message')) {
            $this->merge([
                'message' => trim($this->message),
            ]);
        }
    }
}

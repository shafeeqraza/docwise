<?php

return [
    'default_llm_model' => env('CHAT_DEFAULT_LLM_MODEL', 'Gemini 2.5 Flash'),
    'default_embedding_model' => env('CHAT_DEFAULT_EMBEDDING_MODEL', 'models/gemini-embedding-001'),
    'max_context_chunks' => env('CHAT_MAX_CONTEXT_CHUNKS', 5),
    'max_chat_history' => env('CHAT_MAX_HISTORY', 10),
    'temperature' => env('CHAT_TEMPERATURE', 0.7),

    // Token limits for safety and cost control
    'max_input_tokens' => env('CHAT_MAX_INPUT_TOKENS', 2000), // Max tokens for user input
    'max_output_tokens' => env('CHAT_MAX_OUTPUT_TOKENS', 1000), // Max tokens for AI response
    'max_total_tokens' => env('CHAT_MAX_TOTAL_TOKENS', 8000), // Max total tokens (input + context + output)

    // Character limits (fallback for token estimation)
    'max_input_characters' => env('CHAT_MAX_INPUT_CHARS', 8000), // ~2000 tokens

    // Legacy config (kept for backward compatibility)
    'max_tokens' => env('CHAT_MAX_TOKENS', 1000),
];

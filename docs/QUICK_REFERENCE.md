# Chat API - Quick Reference Guide

**Quick access guide for developers working on the Chat API**

---

## Request Flow (60 Second Overview)

```
Client Widget
    ↓ POST /api/widget/chat {message, session_id}
    ↓ Header: Authorization: Bearer {api_key}
    ↓
[1] AuthenticateApiKey Middleware (~50ms)
    • Hash API key
    • Lookup in DB
    • Validate: active, not expired
    • ⚠️ Domain check is DISABLED
    ↓
[2] RateLimitApiKey Middleware (~5ms)
    • Check per-minute limit (60/min)
    • Check per-hour limit (1000/hr)
    • Add rate limit headers
    ↓
[3] SendChatMessageRequest Validation (~10ms)
    • Character limit (8000 chars)
    • Fast token estimation (~2000 tokens)
    ↓
[4] WidgetChatController::chat() (~10ms)
    • Create DTO
    • Call ChatService
    • Log usage
    ↓
[5] ChatService::sendMessage() (~100ms)
    • Get/create session
    • Save user message
    • Execute RAG pipeline ← DELEGATES HERE
    • Save AI response
    • Update stats
    ↓
[6] ChatRAGPipeline::execute() (~3-5 seconds)
    [6a] Generate Embedding (~500ms)
         • GeminiEmbeddingProvider
         • Returns 768-dim vector
    
    [6b] Vector Search (~200ms)
         • QdrantVectorStore
         • Filter by company_id
         • Top 5 chunks
    
    [6c] Build Context (~10ms)
         • Format chunks
         • Add system prompt
         • Add chat history
    
    [6d] Token Validation (~500-1000ms)
         • Count REAL tokens (cached)
         • Check limits
         • Truncate if needed
    
    [6e] LLM Call (~2-3 seconds)
         • GeminiLLMProvider
         • Retry on 503/429
         • Return completion
    
    [6f] Build Response (~5ms)
         • Citations
         • Confidence score
    ↓
[7] Return Response (~50ms)
    • ChatSessionResource
    • Rate limit headers
    • 200 OK

Total: 3.5-5.5 seconds
```

---

## Key Files Map

### Entry Points
```
routes/api.php
    └→ WidgetChatController::chat()
        └→ ChatService::sendMessage()
            └→ ChatRAGPipeline::execute()
```

### Middleware Stack
```
bootstrap/app.php
    • AuthenticateApiKey
    • RateLimitApiKey
```

### Core Services
```
app/Services/V1/Chat/
    • ChatService.php (orchestration)
    
app/Domains/RAG/Pipelines/
    • ChatRAGPipeline.php (RAG workflow)
```

### Providers & Factories
```
app/Domains/RAG/
    ├── LLMs/
    │   ├── Gemini/GeminiLLMProvider.php
    │   └── OpenAI/OpenAILLMProvider.php
    ├── Embeddings/
    │   ├── Gemini/GeminiEmbeddingProvider.php
    │   └── OpenAI/OpenAIEmbeddingProvider.php
    ├── Tokenizers/
    │   ├── Gemini/GeminiTokenizer.php
    │   └── Tiktoken/TiktokenTokenizer.php
    └── Factories/
        ├── LLMProviderFactory.php
        ├── EmbeddingProviderFactory.php
        └── TokenizerFactory.php
```

### Data Layer
```
app/Repositories/V1/
    ├── ChatRepository.php
    ├── RetrievedChunkRepository.php
    └── Contracts/
        ├── ChatRepositoryInterface.php
        └── RetrievedChunkRepositoryInterface.php

app/Models/
    ├── ChatSession.php
    ├── ChatMessage.php
    ├── Feedback.php
    └── CompanyApiKey.php
```

### Token Management
```
app/Services/V1/Common/
    ├── TokenEstimator.php (fast, ~1ms)
    └── TokenCountCache.php (accurate caching)

app/Domains/RAG/Tokenizers/
    ├── GeminiTokenizer.php (API call)
    └── TiktokenTokenizer.php (local library)
```

---

## Configuration Files

### `config/chat.php`
```php
'default_llm_model' => 'Gemini 2.5 Flash',
'default_embedding_model' => 'models/gemini-embedding-001',
'max_context_chunks' => 5,
'max_chat_history' => 10,
'temperature' => 0.7,

// Token Limits
'max_input_tokens' => 2000,
'max_output_tokens' => 1000,
'max_total_tokens' => 8000,
'max_input_characters' => 8000,
```

### `config/qdrant.php`
```php
'host' => env('QDRANT_HOST', 'https://...'),
'api_key' => env('QDRANT_API_KEY'),
'collection_name' => env('QDRANT_COLLECTION', 'document_chunks'),
```

### `.env` (Key Variables)
```bash
# LLM APIs
GEMINI_API_KEY=
OPENAI_API_KEY=

# Qdrant
QDRANT_HOST=
QDRANT_API_KEY=
QDRANT_COLLECTION=

# Chat Config
CHAT_DEFAULT_LLM_MODEL="Gemini 2.5 Flash"
CHAT_MAX_INPUT_TOKENS=2000
CHAT_MAX_OUTPUT_TOKENS=1000
CHAT_MAX_TOTAL_TOKENS=8000

# Cache
CACHE_DRIVER=redis
```

---

## API Endpoints

### `POST /api/widget/chat`
**Purpose:** Send a chat message and get AI response

**Headers:**
```
Authorization: Bearer {api_key}
Content-Type: application/json
```

**Request:**
```json
{
  "message": "What is your refund policy?",
  "session_id": "uuid-here",  // optional
  "user_metadata": {           // optional
    "user_id": "user123",
    "name": "John Doe",
    "email": "john@example.com"
  }
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Message sent successfully",
  "data": {
    "uuid": "session-uuid",
    "company_id": 1,
    "session_identifier": "uuid",
    "message_count": 2,
    "total_tokens": 450,
    "created_at": "2026-01-21T10:30:00Z",
    "latest_message": {
      "id": 123,
      "role": "assistant",
      "content": "Our refund policy allows...",
      "tokens_prompt": 300,
      "tokens_completion": 150,
      "model_used": "Gemini 2.5 Flash",
      "confidence_score": 0.92,
      "citations": [
        {
          "index": 1,
          "chunk_id": "uuid",
          "document_id": 456,
          "content_preview": "Refunds are processed within...",
          "similarity_score": 0.95
        }
      ],
      "created_at": "2026-01-21T10:30:01Z"
    }
  }
}
```

**Error Responses:**
```json
// 401 - Invalid API Key
{
  "error": "Invalid API key",
  "message": "The provided API key is invalid"
}

// 429 - Rate Limit
{
  "error": "Rate limit exceeded",
  "message": "Too many requests. Please try again later.",
  "retry_after": 30,
  "limit": 60,
  "remaining": 0
}

// 422 - Validation Error
{
  "message": "Message exceeds maximum token limit of 2000 tokens (estimated: 2500 tokens)",
  "errors": {
    "message": ["Message exceeds maximum token limit..."]
  }
}
```

---

### `GET /api/widget/chat/{session_uuid}`
**Purpose:** Get chat history for a session

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 122,
      "role": "user",
      "content": "What is your refund policy?",
      "created_at": "2026-01-21T10:30:00Z"
    },
    {
      "id": 123,
      "role": "assistant",
      "content": "Our refund policy allows...",
      "tokens_prompt": 300,
      "tokens_completion": 150,
      "confidence_score": 0.92,
      "citations": [...],
      "created_at": "2026-01-21T10:30:01Z"
    }
  ]
}
```

---

### `POST /api/widget/chat/{session_uuid}/feedback`
**Purpose:** Submit feedback for a message

**Request:**
```json
{
  "message_id": 123,
  "type": "thumbs_up",  // or "thumbs_down", "rating", "comment"
  "rating": 5,          // optional (1-5)
  "comment": "Very helpful!"  // optional
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Feedback submitted successfully"
}
```

---

## Common Tasks

### 1. Add a New LLM Provider

**Step 1:** Create provider class
```php
// app/Domains/RAG/LLMs/Anthropic/AnthropicLLMProvider.php
class AnthropicLLMProvider implements LLMProvider
{
    public function generateCompletion(array $messages, array $options = []): CompletionDTO { }
    public function supports(string $model): bool { }
}
```

**Step 2:** Update factory
```php
// app/Domains/RAG/Factories/LLMProviderFactory.php
public function create(string $model): LLMProvider
{
    return match(true) {
        str_contains($model, 'claude') => app(AnthropicLLMProvider::class),
        // ... existing cases
    };
}
```

**Step 3:** Add config
```php
// config/chat.php
'supported_llm_models' => [
    'claude-3-opus',
    'claude-3-sonnet',
    // ...
],
```

---

### 2. Debug Token Counting Issues

**Check cache:**
```bash
redis-cli
> KEYS token_count:*
> GET token_count:{hash}
```

**Check TokenEstimator accuracy:**
```php
$estimator = app(TokenEstimator::class);
$estimated = $estimator->estimate($text);

$tokenizer = app(TokenizerFactory::class)->create($model);
$actual = $tokenizer->countTokens($text);

$difference = abs($actual - $estimated);
$accuracy = 100 - (($difference / $actual) * 100);

Log::info('Token estimation accuracy', [
    'estimated' => $estimated,
    'actual' => $actual,
    'accuracy' => $accuracy,
]);
```

---

### 3. Debug RAG Pipeline

**Add logging in ChatRAGPipeline:**
```php
// Already exists in the code
$this->logService?->info('Step X', [
    'company_id' => $companyId,
    'data' => $data,
]);
```

**Check logs:**
```bash
tail -f storage/logs/laravel.log | grep "RAG pipeline"
```

**Check Qdrant search results:**
```bash
# Via tinker
php artisan tinker

$embedding = [/* 768-dim array */];
$chunks = app(VectorStoreService::class)->search($embedding, 5, ['company_id' => 1]);
dd($chunks);
```

---

### 4. Test API Key Authentication

**Create test API key:**
```php
php artisan tinker

$company = Company::first();
$apiKey = CompanyApiKey::create([
    'company_id' => $company->id,
    'name' => 'Test Key',
    'key_hash' => hash('sha256', 'test-key-' . Str::random(32)),
    'key_prefix' => 'test_',
    'rate_limit_per_minute' => 60,
    'rate_limit_per_hour' => 1000,
    'is_active' => true,
]);

// Note: You need to save the plain key before hashing
$plainKey = 'test-key-' . Str::random(32);
$apiKey->key_hash = hash('sha256', $plainKey);
$apiKey->save();

echo "API Key: {$plainKey}\n";
```

**Test with curl:**
```bash
curl -X POST http://localhost/api/widget/chat \
  -H "Authorization: Bearer {api_key}" \
  -H "Content-Type: application/json" \
  -d '{"message": "Hello!"}'
```

---

### 5. Monitor Performance

**Check cache hit rate:**
```php
// Add to TokenCountCache
private static $hits = 0;
private static $misses = 0;

public function remember(string $text, callable $counter): int
{
    if (Cache::has($cacheKey)) {
        self::$hits++;
    } else {
        self::$misses++;
    }
    
    $hitRate = self::$hits / (self::$hits + self::$misses) * 100;
    Log::info('Cache hit rate', ['rate' => $hitRate]);
    
    return Cache::remember(...);
}
```

**Check LLM latency:**
```bash
tail -f storage/logs/laravel.log | grep "RAG pipeline completed"
# Look for latency_ms value
```

---

## Database Schema (Key Tables)

### `chat_sessions`
```sql
id, uuid, company_id, session_identifier, 
message_count, total_tokens, user_metadata, 
created_at, updated_at
```

### `chat_messages`
```sql
id, session_id, role, content, 
tokens_prompt, tokens_completion, model_used,
confidence_score, citations (json),
created_at, updated_at
```

### `feedback`
```sql
id, uuid, message_id, session_id, user_id,
feedback_type, rating, comment, 
categories (json), metadata (json),
created_at, updated_at
```

### `retrieved_chunks`
```sql
id, message_id, chunk_id, document_id,
similarity_score, rank_position, used_in_context,
metadata (json), created_at
```

### `company_api_keys`
```sql
id, company_id, name, key_hash, key_prefix,
rate_limit_per_minute, rate_limit_per_hour,
allowed_domain, is_active, expires_at,
last_used_at, created_at, updated_at
```

---

## Troubleshooting

### Issue: "401 Invalid API key"
**Causes:**
- API key not in database
- Key hash mismatch (whitespace in key?)
- Key is inactive (`is_active = 0`)
- Key is expired

**Debug:**
```php
$apiKey = trim($request->bearerToken());
$hash = hash('sha256', $apiKey);
$record = CompanyApiKey::where('key_hash', $hash)->first();
dd([
    'api_key' => $apiKey,
    'hash' => $hash,
    'found' => $record !== null,
    'is_active' => $record?->is_active,
    'expires_at' => $record?->expires_at,
]);
```

---

### Issue: "429 Rate limit exceeded"
**Causes:**
- Exceeded per-minute limit (60 req/min)
- Exceeded per-hour limit (1000 req/hr)

**Check:**
```bash
redis-cli
> GET "api_key:{id}:minute:{timestamp}"
> GET "api_key:{id}:hour:{timestamp}"
```

**Reset (for testing):**
```bash
redis-cli FLUSHALL
```

---

### Issue: "Message exceeds token limit"
**Causes:**
- Input too long (>2000 tokens estimated)

**Fix:**
- Increase `config('chat.max_input_tokens')`
- OR ask user to shorten message

---

### Issue: Empty vector search results
**Causes:**
- No documents indexed for company
- company_id filter mismatch
- Qdrant collection not created

**Debug:**
```php
// Check Qdrant collection
$vectorStore = app(QdrantVectorStore::class);
$info = $vectorStore->getCollectionInfo();
dd($info);

// Check company documents
$chunks = DocumentChunk::where('company_id', 1)->count();
dd($chunks);
```

---

### Issue: LLM returns empty response
**Causes:**
- Model overloaded (503 error)
- API key invalid
- Token limit exceeded

**Check logs:**
```bash
grep "LLM API request failed" storage/logs/laravel.log
```

---

## Performance Tips

### 1. Cache Query Embeddings
```php
class QueryEmbeddingCache
{
    public function remember(string $query, callable $generator): array
    {
        $key = 'query_emb:' . hash('xxh3', $query);
        return Cache::remember($key, 3600, $generator);
    }
}
```

### 2. Optimize Database Queries
```php
// Add indexes
Schema::table('chat_messages', function (Blueprint $table) {
    $table->index(['session_id', 'created_at']);
});

// Eager load
$session = ChatSession::with(['messages', 'latestMessage'])->find($id);
```

### 3. Use Redis for Cache
```bash
# .env
CACHE_DRIVER=redis
REDIS_CLIENT=phpredis
```

---

## Development Workflow

### 1. Local Setup
```bash
# Clone repo
git clone ...

# Install dependencies
composer install

# Configure .env
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate

# Start servers
php artisan serve
php artisan queue:work
```

### 2. Testing Changes
```bash
# Run linter
composer phpcs

# Run tests (when available)
php artisan test

# Manual test via tinker
php artisan tinker
```

### 3. Deployment Checklist
- [ ] Run migrations
- [ ] Clear config cache: `php artisan config:clear`
- [ ] Clear route cache: `php artisan route:clear`
- [ ] Restart queue workers: `php artisan queue:restart`
- [ ] Check logs for errors
- [ ] Test API endpoints

---

## Quick Commands

```bash
# Clear all caches
php artisan optimize:clear

# Create Qdrant indexes
php artisan qdrant:create-index

# Test queue job
php artisan queue-test

# Monitor logs
tail -f storage/logs/laravel.log

# Check Redis
redis-cli MONITOR

# Database query log
DB::listen(function($query) {
    Log::info($query->sql, $query->bindings);
});
```

---

**Last Updated:** 2026-01-21  
**For detailed architecture, see:** `CHAT_API_ARCHITECTURE.md`

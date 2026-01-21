# Chat API - Improvements Roadmap

**Prioritized list of improvements, technical debt, and future features**

---

## 🔴 CRITICAL (Fix Immediately - This Week)

### 1. Re-Enable Domain Restriction
**File:** `app/Http/Middleware/AuthenticateApiKey.php:64-90`  
**Priority:** P0 (Security vulnerability)  
**Effort:** 1 hour  
**Impact:** HIGH - Prevents API key theft

**Current State:**
```php
// Lines 64-90 are commented out
// if ($origin) {
//     if (!$apiKeyRecord->isDomainAllowed($origin)) {
//         return response()->json([...], 403);
//     }
// }
```

**Action Items:**
- [ ] Uncomment domain validation logic
- [ ] Add feature flag: `config('api.enforce_domain_restriction', true)`
- [ ] Test with valid and invalid domains
- [ ] Document domain restriction in API docs

**Alternative (if domain restriction not needed):**
- [ ] Remove code entirely (don't leave commented)
- [ ] Remove `allowed_domain` column from database
- [ ] Update documentation

**Why Critical:** Currently ANY domain can use ANY API key if stolen.

---

### 2. Fix Duplicate Usage Tracking Services
**Files:**
- `app/Services/V1/Company/ApiKeyUsageService.php` (uses `UsageMetric` model)
- `app/Services/V1/Analytics/ApiKeyUsageService.php` (uses `ApiKeyUsageLog` model)

**Priority:** P0 (Data inconsistency)  
**Effort:** 4 hours  
**Impact:** HIGH - Billing might be incorrect

**Problem:** Two different services with same name doing different things.

**Action Items:**
- [ ] Decide which approach to use (ApiKeyUsageLog is newer)
- [ ] Delete obsolete implementation
- [ ] Migrate data if needed
- [ ] Update all references
- [ ] Test usage tracking end-to-end

**Recommendation:** Use `ApiKeyUsageLog` + repository pattern (more consistent with architecture).

---

### 3. Implement Basic Test Suite
**Priority:** P0 (No regression detection)  
**Effort:** 8 hours (for critical path only)  
**Impact:** HIGH - Prevents production bugs

**Minimum Required Tests:**
```
tests/
├── Unit/
│   ├── TokenEstimatorTest.php
│   ├── TokenCountCacheTest.php
│   └── Services/
│       └── ChatServiceTest.php
├── Feature/
│   ├── ChatAPITest.php
│   └── Middleware/
│       ├── AuthenticateApiKeyTest.php
│       └── RateLimitApiKeyTest.php
└── Integration/
    └── RAGPipelineTest.php
```

**Action Items:**
- [ ] Set up PHPUnit configuration
- [ ] Write tests for critical path (chat endpoint)
- [ ] Write tests for middleware
- [ ] Write tests for token validation
- [ ] Add to CI/CD pipeline

---

## 🟡 HIGH PRIORITY (Fix This Month)

### 4. Add Request ID Tracking
**Priority:** P1  
**Effort:** 2 hours  
**Impact:** MEDIUM - Better debugging

**Implementation:**
```php
// app/Http/Middleware/AddRequestId.php
class AddRequestId
{
    public function handle($request, Closure $next)
    {
        $requestId = Str::uuid();
        $request->attributes->add(['request_id' => $requestId]);
        
        Log::withContext(['request_id' => $requestId]);
        
        $response = $next($request);
        $response->headers->set('X-Request-ID', $requestId);
        
        return $response;
    }
}
```

**Action Items:**
- [ ] Create middleware
- [ ] Add to middleware stack
- [ ] Update all Log::info() calls to include request_id
- [ ] Add to error responses

---

### 5. Improve Exception Handling in Controller
**File:** `app/Http/Controllers/V1/Api/WidgetChatController.php:70`  
**Priority:** P1  
**Effort:** 1 hour  
**Impact:** MEDIUM - Better error messages

**Current:**
```php
} catch (\Exception $e) {
    return $this->respondError('An error occurred...', 500);
}
```

**Better:**
```php
} catch (ModelNotFoundException $e) {
    return $this->respondError('Session not found', 404);
} catch (ValidationException $e) {
    return $this->respondValidationError($e->errors(), 422);
} catch (RateLimitException $e) {
    return $this->respondError('Rate limit exceeded', 429);
} catch (LLMProviderException $e) {
    Log::error('LLM provider error', ['error' => $e->getMessage()]);
    return $this->respondError('AI service temporarily unavailable', 503);
} catch (\Exception $e) {
    Log::error('Unexpected error', ['exception' => $e]);
    return $this->respondError('Internal server error', 500);
}
```

**Action Items:**
- [ ] Create custom exception classes
- [ ] Update controller catch blocks
- [ ] Add proper HTTP status codes
- [ ] Document error responses in API docs

---

### 6. Add Database Indexes
**Priority:** P1  
**Effort:** 1 hour  
**Impact:** HIGH - 30-50% faster queries

**Action Items:**
```php
// Create migration: 2026_01_21_add_performance_indexes.php

Schema::table('chat_sessions', function (Blueprint $table) {
    $table->index(['company_id', 'created_at']);
    $table->index(['session_identifier', 'company_id']);
});

Schema::table('chat_messages', function (Blueprint $table) {
    $table->index(['session_id', 'created_at']);
    $table->index(['session_id', 'role']);
});

Schema::table('api_key_usage_logs', function (Blueprint $table) {
    $table->index(['api_key_id', 'created_at']);
    $table->index(['company_id', 'created_at']);
});

Schema::table('retrieved_chunks', function (Blueprint $table) {
    $table->index(['message_id', 'rank_position']);
});
```

- [ ] Create migration
- [ ] Run on development
- [ ] Measure query performance before/after
- [ ] Run on staging
- [ ] Run on production (during low traffic)

---

### 7. Create FeedbackRepository
**File:** `app/Services/V1/Chat/ChatService.php:117,129`  
**Priority:** P1  
**Effort:** 2 hours  
**Impact:** MEDIUM - Consistency with architecture

**Current:**
```php
// Direct model usage in service
$existingFeedback = Feedback::where('message_id', $message->id)->first();
Feedback::create([...]);
```

**Better:**
```php
// app/Repositories/V1/Contracts/FeedbackRepositoryInterface.php
interface FeedbackRepositoryInterface
{
    public function findByMessageId(int $messageId): ?Feedback;
    public function create(array $data): Feedback;
    public function update(Feedback $feedback, array $data): bool;
}

// app/Repositories/V1/FeedbackRepository.php
class FeedbackRepository implements FeedbackRepositoryInterface
{
    public function findByMessageId(int $messageId): ?Feedback
    {
        return Feedback::where('message_id', $messageId)->first();
    }
    
    public function create(array $data): Feedback
    {
        return Feedback::create($data);
    }
    
    public function update(Feedback $feedback, array $data): bool
    {
        return $feedback->update($data);
    }
}
```

**Action Items:**
- [ ] Create interface
- [ ] Create repository
- [ ] Register in service provider
- [ ] Update ChatService to use repository
- [ ] Write tests

---

### 8. Add Query Embedding Cache
**Priority:** P1  
**Effort:** 3 hours  
**Impact:** HIGH - 20-30% latency reduction

**Implementation:**
```php
// app/Services/V1/Common/QueryEmbeddingCache.php
class QueryEmbeddingCache
{
    private const CACHE_PREFIX = 'query_embedding:';
    private const CACHE_TTL = 3600; // 1 hour
    
    public function remember(string $query, string $model, callable $generator): array
    {
        // Normalize query (lowercase, trim, remove extra spaces)
        $normalizedQuery = $this->normalizeQuery($query);
        
        $cacheKey = $this->getCacheKey($normalizedQuery, $model);
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($generator) {
            return $generator();
        });
    }
    
    private function normalizeQuery(string $query): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $query)));
    }
    
    private function getCacheKey(string $query, string $model): string
    {
        $hash = hash('xxh3', $query . $model);
        return self::CACHE_PREFIX . $hash;
    }
}
```

**Usage in ChatRAGPipeline:**
```php
// Before
$queryEmbedding = $embeddingProvider->generateEmbedding($message, $embeddingModel);

// After
$queryEmbedding = $this->queryEmbeddingCache->remember(
    $message,
    $embeddingModel,
    fn() => $embeddingProvider->generateEmbedding($message, $embeddingModel)
);
```

**Action Items:**
- [ ] Create `QueryEmbeddingCache` service
- [ ] Inject into `ChatRAGPipeline`
- [ ] Update embedding generation to use cache
- [ ] Add cache hit/miss metrics
- [ ] Test with repeated queries

**Expected Impact:**
- First query: ~500ms (cache miss)
- Repeated query: ~5ms (cache hit)
- Similar queries: Need semantic similarity check (Phase 2)

---

### 9. Add Basic Monitoring
**Priority:** P1  
**Effort:** 4 hours  
**Impact:** HIGH - Visibility into production

**Recommended Tools:**
- **Option 1:** Sentry (error tracking) + New Relic (APM)
- **Option 2:** Laravel Telescope (development) + Bugsnag (production)
- **Option 3:** Self-hosted Grafana + Prometheus

**Minimum Metrics:**
```php
// app/Services/V1/Common/MetricsService.php
class MetricsService
{
    public function recordChatLatency(float $latencyMs, string $stage, array $tags = []): void
    {
        // Send to monitoring service
    }
    
    public function incrementCounter(string $metric, array $tags = []): void
    {
        // Increment counter
    }
    
    public function recordGauge(string $metric, float $value, array $tags = []): void
    {
        // Record gauge value
    }
}
```

**Key Metrics to Track:**
- Request rate (req/sec)
- Response time (p50, p95, p99)
- Error rate (%)
- LLM latency
- Token usage
- Cache hit rate
- Database query time

**Action Items:**
- [ ] Choose monitoring tool
- [ ] Set up account/installation
- [ ] Create `MetricsService`
- [ ] Add metrics to critical paths
- [ ] Create dashboards
- [ ] Set up alerts

---

## 🟢 MEDIUM PRIORITY (Next 2-3 Months)

### 10. Response Streaming
**Priority:** P2  
**Effort:** 8 hours  
**Impact:** MEDIUM - Better UX

**Implementation:**
```php
public function chatStream(SendChatMessageRequest $request): StreamedResponse
{
    return response()->stream(function () use ($request) {
        $stream = $this->llmProvider->generateCompletionStream($messages);
        
        foreach ($stream as $chunk) {
            echo "data: " . json_encode(['content' => $chunk]) . "\n\n";
            ob_flush();
            flush();
        }
        
        echo "data: [DONE]\n\n";
    }, 200, [
        'Content-Type' => 'text/event-stream',
        'Cache-Control' => 'no-cache',
        'X-Accel-Buffering' => 'no',
    ]);
}
```

**Gemini Streaming:**
```php
$response = Http::timeout(60)
    ->withHeaders([
        'Content-Type' => 'application/json',
    ])
    ->post($url . '?alt=sse', $payload);

$stream = $response->getBody();
while (!$stream->eof()) {
    $line = $stream->read(1024);
    // Parse SSE format
    yield $line;
}
```

---

### 11. Async Processing with Queues
**Priority:** P2  
**Effort:** 12 hours  
**Impact:** HIGH - Much better response times

**Architecture:**
```
POST /api/widget/chat
    ↓
Save user message
    ↓
Dispatch ProcessChatMessageJob
    ↓
Return 202 Accepted { "job_id": "..." }
    ↓ (async)
ProcessChatMessageJob:
    - Generate embedding
    - Vector search
    - Token validation
    - LLM call
    - Save response
    - Trigger webhook/SSE
```

**Implementation:**
```php
class ProcessChatMessageJob implements ShouldQueue
{
    public function handle(ChatRAGPipeline $pipeline): void
    {
        $response = $pipeline->execute($this->params);
        
        $this->chatRepository->createMessage([
            'session_id' => $this->session->id,
            'role' => 'assistant',
            'content' => $response['content'],
            // ...
        ]);
        
        // Notify client
        event(new ChatResponseReady($this->session, $response));
    }
}
```

**Benefits:**
- API response: 50ms (vs 5000ms)
- Better error handling (automatic retries)
- Better resource utilization

---

### 12. Semantic Caching
**Priority:** P2  
**Effort:** 16 hours  
**Impact:** HIGH - 40-60% latency reduction

**Concept:**
```
User query: "What's the refund policy?"
    ↓
Generate embedding
    ↓
Search cache (cosine similarity > 0.95)
    ↓
If match found:
    Return cached response (5ms)
Else:
    Execute full pipeline
    Cache response (5000ms)
```

**Implementation:**
```php
class SemanticCache
{
    public function search(array $embedding, float $threshold = 0.95): ?array
    {
        // Search cached queries in Qdrant/Redis
        $results = $this->vectorStore->search(
            $embedding,
            limit: 1,
            filter: ['cache_type' => 'query_response']
        );
        
        if (!empty($results) && $results[0]->similarity > $threshold) {
            return json_decode($results[0]->metadata['response'], true);
        }
        
        return null;
    }
    
    public function store(array $embedding, string $query, array $response): void
    {
        // Store in vector DB for semantic lookup
        $this->vectorStore->upsert([
            'id' => Str::uuid(),
            'vector' => $embedding,
            'payload' => [
                'cache_type' => 'query_response',
                'query' => $query,
                'response' => json_encode($response),
                'created_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
```

---

### 13. Advanced Analytics Dashboard
**Priority:** P2  
**Effort:** 20 hours  
**Impact:** MEDIUM - Business insights

**Features:**
- Chat volume trends
- Latency by stage (waterfall chart)
- Token usage by company
- Model usage distribution
- Feedback analysis
- Popular queries
- Error rates

**Tech Stack:**
- Laravel Nova + Custom Tools
- OR separate React dashboard
- OR Grafana dashboards

---

### 14. Comprehensive API Documentation
**Priority:** P2  
**Effort:** 8 hours  
**Impact:** MEDIUM - Developer experience

**Tools:**
- **Option 1:** Swagger/OpenAPI + Swagger UI
- **Option 2:** Postman Collection
- **Option 3:** Laravel Scribe

**Contents:**
- [ ] Authentication guide
- [ ] All endpoints with examples
- [ ] Request/response schemas
- [ ] Error codes
- [ ] Rate limits
- [ ] SDKs (JavaScript, Python)
- [ ] Webhook documentation

---

## 🔵 LOW PRIORITY (Future / Nice to Have)

### 15. Webhook System
Notify companies about events (message sent, feedback submitted, etc.)

### 16. Multi-Region Deployment
Deploy to multiple regions for lower latency.

### 17. Fine-Tuning Support
Allow companies to fine-tune models on their data.

### 18. Multi-Modal Support
Support images, PDFs, and other file types in chat.

### 19. Advanced RAG Features
- Hybrid search (vector + keyword)
- Re-ranking models
- Multi-hop reasoning
- Citation validation

### 20. Enterprise Security
- API key rotation
- IP whitelisting
- Request signing (HMAC)
- Audit logging
- RBAC for API keys

---

## Implementation Timeline

### Week 1-2 (Critical Fixes)
- [ ] Re-enable domain restriction
- [ ] Fix duplicate usage services
- [ ] Basic test suite
- [ ] Request ID tracking

### Month 1 (High Priority)
- [ ] Exception handling
- [ ] Database indexes
- [ ] FeedbackRepository
- [ ] Query embedding cache
- [ ] Basic monitoring

### Month 2 (Medium Priority - Part 1)
- [ ] Response streaming
- [ ] Async processing
- [ ] Comprehensive tests (80%+ coverage)
- [ ] API documentation

### Month 3 (Medium Priority - Part 2)
- [ ] Semantic caching
- [ ] Analytics dashboard
- [ ] Performance optimization
- [ ] Security improvements

### Month 4-6 (Low Priority)
- [ ] Webhook system
- [ ] Advanced RAG features
- [ ] Enterprise features
- [ ] Multi-region deployment

---

## Success Metrics

### Technical Metrics
- **Test Coverage:** 0% → 80%+
- **Response Time:** 5000ms → 500ms (with async)
- **Cache Hit Rate:** 0% → 70%+ (query embeddings)
- **Error Rate:** <1%
- **Database Query Time:** <50ms p95

### Business Metrics
- **Messages/Day:** Track growth
- **User Satisfaction:** >4.0/5.0 avg feedback
- **API Key Churn:** <5%/month
- **Revenue/API Key:** Track

---

## Risk Mitigation

### For Each Change:
1. **Write tests FIRST** (TDD)
2. **Deploy to staging** before production
3. **Feature flags** for risky changes
4. **Rollback plan** documented
5. **Monitor metrics** for 24-48 hours after deployment

### Feature Flags Example:
```php
if (config('features.enable_semantic_caching', false)) {
    // Use semantic cache
} else {
    // Use direct LLM call
}
```

---

## Appendix: Effort Estimation Guide

| Effort | Hours | Description |
|--------|-------|-------------|
| Trivial | 1-2h | Config change, minor fix |
| Small | 2-4h | Single file, clear requirements |
| Medium | 4-8h | Multiple files, some unknowns |
| Large | 8-16h | Cross-cutting, needs design |
| XLarge | 16-40h | Major feature, significant testing |

**Team Size Assumption:** 1-2 developers

**Velocity Assumption:** 20-30 hours/week of focused development time

---

**Last Updated:** 2026-01-21  
**Review Schedule:** Monthly  
**Owner:** Development Team

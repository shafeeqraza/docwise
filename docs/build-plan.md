# AI Customer Support Build Blueprint

This document outlines the folder layout, relational and vector schemas, and a 30-day execution plan to build a Laravel + MySQL + Qdrant Retrieval-Augmented Generation (RAG) customer support system.

---

## 1. Repository & Folder Structure

```
rag-customer-support/
├── app/                     # Laravel core (Controllers, Models, Jobs, Policies)
│   ├── Console/Commands     # Ingestion, maintenance commands
│   ├── Http/
│   │   ├── Controllers/
│   │   └── Middleware/
│   ├── Jobs/
│   ├── Models/
│   └── Services/
│       ├── Embeddings/      # LLM + embedding adapters
│       └── VectorStores/    # Qdrant client wrappers
├── bootstrap/
├── config/
│   ├── qdrant.php           # Qdrant host, ports, collections
│   ├── rag.php              # Chunk sizes, similarity thresholds
│   └── services.php
├── database/
│   ├── migrations/
│   ├── seeders/
│   └── factories/
├── docs/
│   └── build-plan.md
├── public/
├── resources/
│   └── views/               # Blade templates (widget only - frontend moved to separate repo)
├── routes/
│   ├── api.php              # Upload, chat, admin REST endpoints
│   └── web.php
├── storage/
│   ├── app/documents/       # Uploaded originals
│   └── logs/
├── tests/
│   ├── Feature/
│   └── Unit/
├── infrastructure/
│   ├── docker/
│   │   ├── php/Dockerfile
│   │   ├── nginx/Dockerfile
│   │   └── qdrant/docker-compose.override.yml
│   └── scripts/
│       ├── ingest_sample_docs.sh
│       └── sync_qdrant_collections.php
├── composer.json
└── README.md
```

---

## 2. MySQL Schema (Relational Layer)

> Use UUIDs for public IDs and unsigned big integers for internal keys; add `company_id` to every tenant-bound table to enforce isolation.

| Table               | Key Columns                                                                                                                                    | Purpose / Notes                                                    |
| ------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------ |
| `companies`         | `id`, `name`, `slug`, `status`, `settings (json)`                                                                                              | Tenant profile and configuration.                                  |
| `users`             | `id`, `company_id`, `name`, `email`, `role`, `password`, `api_token`, `last_login_at`                                                          | Admins, agents, or bot users.                                      |
| `documents`         | `id`, `company_id`, `title`, `source_type`, `status`, `uploaded_by`, `storage_path`, `mime_type`, `checksum`, `metadata (json)`                | Logical docs; one row per upload batch.                            |
| `document_versions` | `id`, `document_id`, `version`, `processing_state`, `error_log`, `processed_at`                                                                | Track reprocessing and rollbacks.                                  |
| `document_chunks`   | `id`, `company_id`, `document_id`, `version_id`, `chunk_index`, `content`, `token_count`, `embedding_id`, `qdrant_point_id`, `metadata (json)` | Chunks that map to Qdrant vectors; keep textual copy for auditing. |
| `ingestion_jobs`    | `id`, `company_id`, `document_id`, `type`, `status`, `queued_at`, `started_at`, `finished_at`, `fail_reason`                                   | Queue/job observability.                                           |
| `chat_sessions`     | `id`, `company_id`, `external_user_id`, `channel`, `status`, `created_at`, `ended_at`, `context (json)`                                        | Thread-level context.                                              |
| `chat_messages`     | `id`, `session_id`, `role (user/assistant/system)`, `content`, `tokens`, `latency_ms`, `raw_llm_response (json)`                               | Ordered conversation messages.                                     |
| `retrieved_chunks`  | `id`, `message_id`, `chunk_id`, `document_id`, `score`, `rank`, `metadata (json)`                                                              | Snapshot of context passed to LLM.                                 |
| `feedback`          | `id`, `message_id`, `user_id`, `type (thumbs_up/down)`, `notes`, `labels (json)`                                                               | Human evaluation + fine-tune hooks.                                |
| `usage_metrics`     | `id`, `company_id`, `period`, `embeddings_count`, `tokens_prompt`, `tokens_completion`, `vector_queries`, `cache_hits`                         | Billing + dashboards.                                              |

Indexes to add early:

-   `document_chunks (company_id, document_id, chunk_index)`
-   `chat_messages (session_id, created_at)`
-   `retrieved_chunks (message_id)`
-   `usage_metrics (company_id, period)`

---

## 3. Qdrant Schema (Vector Layer)

**Collection Strategy**

-   One collection per company: `company_{company_id}_chunks` to enforce physical isolation and simplify retention deletes.
-   Vector size = embedding dimension (e.g., 1536 for text-embedding-3-large).
-   Distance metric: cosine or dot, depending on chosen embedding family.

**Payload (stored with each point)**

| Field             | Type     | Description                                         |
| ----------------- | -------- | --------------------------------------------------- |
| `chunk_id`        | int      | Foreign key to `document_chunks.id`.                |
| `company_id`      | int      | Used for validation; should match collection owner. |
| `document_id`     | int      | Source document reference.                          |
| `version_id`      | int      | Version control for re-ingestion.                   |
| `chunk_index`     | int      | Position within original document.                  |
| `content_preview` | string   | First ~200 chars for quick display.                 |
| `metadata`        | object   | `{page: ?, heading: ?, tags: []}` etc.              |
| `created_at`      | datetime | For retention policies.                             |

**Recommended Qdrant Settings**

-   `on_disk_payload = true` for large metadata.
-   `hnsw_config`: `m=16`, `ef_construct=256`.
-   `quantization_config` (optional) for cost-saving once data grows.

---

## 4. 30-Day Actionable Build Plan

| Phase                     | Days  | Key Deliverables                                                                                                                          |
| ------------------------- | ----- | ----------------------------------------------------------------------------------------------------------------------------------------- |
| **Foundation**            | 1-5   | Requirements doc, environment setup (Laravel, MySQL, Qdrant via Docker), stub services, multi-tenant auth.                                |
| **Ingestion Pipeline**    | 6-12  | Upload API/UI, file storage, parsing jobs (PDF/DOCX/TXT), chunking service, embedding + Qdrant writers, job monitoring UI.                |
| **Retrieval & Chat**      | 13-18 | Retrieval service (Qdrant similarity + filters), prompt composer, LLM integration, chat API + UI with streaming, citations in responses.  |
| **Admin & Observability** | 19-24 | Admin dashboard, document lifecycle actions, chat log explorer, feedback capture, usage metrics, alerting for failures.                   |
| **Hardening & Polish**    | 25-30 | Multi-tenant isolation tests, rate limiting, integration tests, performance tuning, onboarding wizard, deployment + portfolio case study. |

### Step-by-Step Checklist

1. **Project Bootstrap**
    - `laravel new rag-customer-support` (or `composer create-project`), configure `.env` with MySQL + Qdrant URLs.
    - Add Docker compose services (`php-fpm`, `nginx`, `mysql`, `qdrant`, `queues`).
    - Create `config/qdrant.php` and service provider binding `VectorStoreInterface -> QdrantClient`.
2. **Authentication & Multi-Tenancy**
    - Migrate `companies`, `users`; seed demo company and admin.
    - Implement middleware to scope every request by `company_id` (subdomain, header, or token).
3. **Document Upload & Storage**
    - Build `POST /api/documents` (multipart) that writes to `storage/app/documents/{company}/{uuid}` and enqueues `ProcessDocument` job.
    - Store metadata in `documents` + `document_versions`.
4. **Ingestion Jobs**
    - `ProcessDocument` pipeline: extract text (Spatie PDF, PHPWord, or convert via LibreOffice), chunk text (e.g., 500 tokens overlap 100), persist chunks.
    - `GenerateEmbeddings` job: call embedding API, persist vector to `document_chunks`, upsert to Qdrant.
    - Track progress in `ingestion_jobs`.
5. **Retrieval Service**
    - Build `app/Services/VectorStores/QdrantService` with methods `upsertChunks`, `searchSimilar`, `deleteByDocument`.
    - Implement `ChunkRetrievalService` that calls Qdrant, applies filters (doc, tags), and returns normalized snippets.
6. **Chat Workflow**
    - Data model for sessions/messages/retrieved_chunks; migrations + models.
    - `POST /api/chat` endpoint: accept user question, create message, fetch context chunks, compose prompt, call LLM, store assistant reply + citations.
    - Front-end view (Livewire or SPA) to show chat with citation pills.
7. **Admin Dashboard**
    - Metrics cards (docs processed, tokens used, chats today).
    - Tables for documents with statuses, chat logs with filters, ingestion job details.
    - Actions: reprocess document, delete, download logs.
8. **Feedback & Monitoring**
    - Capture thumbs up/down per answer; API endpoint to submit.
    - Alerting (email/Slack) when ingestion job fails or LLM returns error.
9. **Testing & Deployment**
    - Feature tests for upload → chunk pipeline (mock embeddings/Qdrant).
    - Feature tests for chat flow (fake LLM).
    - GitHub Actions pipeline running PHPUnit + Pint + front-end build.
    - Deploy to staging/prod (Laravel Forge or Vapor) + managed Qdrant or self-hosted on VM.
10. **Portfolio Packaging**
    - Document architecture, include screenshots of admin + chat, record short demo, write case study highlighting RAG + multi-tenancy.

---

### Day-by-Day Breakdown (Actionable Tasks)

#### Week 1 – Foundation & Project Skeleton

-   **Day 1:** Create repo, run `composer create-project laravel/laravel rag-customer-support`, configure `.env` with local MySQL + Redis; install Sail or Docker compose; verify `php artisan serve` works.
-   **Day 2:** Scaffold multi-tenant tables (`companies`, `users`), run migrations, seed demo tenant/admin via `database/seeders/DatabaseSeeder.php`; install Laravel Breeze or Jetstream for auth.
-   **Day 3:** Add domain/service folders (`app/Services/Embeddings`, `app/Services/VectorStores`), define interfaces, stub `QdrantClient` using HTTP client; add config files `config/qdrant.php`, `config/rag.php`.
-   **Day 4:** Implement tenant resolution middleware (`app/Http/Middleware/ResolveCompany.php`), update `routes/web.php` and `routes/api.php` to require company context; write feature test ensuring isolation.
-   **Day 5:** Draft ERD + update this doc; frontend will be in separate repository (see `docs/frontend-separation.md`).

#### Week 2 – Document Intake Pipeline

-   **Day 6:** Build `POST /api/documents` controller to store uploads under `storage/app/documents/{company}/{uuid}`; persist row in `documents` + `document_versions`; return job ID.
-   **Day 7:** Configure queue worker (Redis + Horizon), create `ProcessDocument` job that logs to `ingestion_jobs`; implement MIME routing (PDF via Spatie, DOCX via PhpWord, TXT via native read).
-   **Day 8:** Implement text chunker service (`app/Services/Chunking/TextChunker.php`) with configurable token target/overlap from `config/rag.php`; write unit tests covering chunk sizing.
-   **Day 9:** Integrate embedding provider (OpenAI, Azure) in `app/Services/Embeddings/OpenAIEmbeddingService.php`; add retry/backoff + token usage logging to `usage_metrics`.
-   **Day 10:** Create `QdrantVectorStore` with methods `ensureCollection`, `upsert`, `deleteByFilter`; on chunk embedding completion, push vectors to `company_{id}_chunks`; persist `qdrant_point_id`.

#### Week 3 – Retrieval-Augmented Chat

-   **Day 11:** Design chat migrations for `chat_sessions`, `chat_messages`, `retrieved_chunks`; create Eloquent models and relationships; seed dummy sessions for UI testing.
-   **Day 12:** Implement `ChunkRetrievalService` calling Qdrant `search` with filters (company, document, tags); normalize scores, cap by `rag.context_limit`; write integration test mocking Qdrant.
-   **Day 13:** Build `PromptBuilder` service that assembles system instructions + conversation history + retrieved chunks; enforce token budgeting (truncate history when over limit).
-   **Day 14:** Add `LLMClient` (OpenAI/Anthropic) with streaming + logging; handle fallbacks for empty context; persist assistant reply + raw response JSON.
-   **Day 15:** Build chat API (`POST /api/chat/messages`), orchestrate steps: create session if needed, store user message, call retrieval + LLM, store assistant message, attach citations in `retrieved_chunks`; frontend UI will consume this API (separate repository).

#### Week 4 – Admin, Monitoring, Hardening

-   **Day 16:** Create admin dashboard API endpoints showing cards (documents processed today, open sessions, token spend); query `usage_metrics`. Frontend will consume these endpoints (separate repository).
-   **Day 17:** Implement document management API endpoints (list, filter, view version history, trigger reprocess/delete); backend endpoints enforce soft delete + Qdrant cleanup. Frontend UI in separate repository.
-   **Day 18:** Build chat log explorer API with search (session ID, user email, rating); show retrieved chunks and feedback inline. Frontend will consume this API.
-   **Day 19:** Add feedback API endpoint `POST /api/chat/feedback` writing to `feedback` table and tagging related chunks. Frontend UI in separate repository.
-   **Day 20:** Implement alerts: dispatch notification (mail/Slack) from failed jobs + LLM errors; add Horizon dashboard link + log tailing command docs.

#### Week 5 – Hardening, Deployment, Portfolio

-   **Day 21:** Audit multi-tenancy: add global scopes or policies, ensure all queries filter by `company_id`; write automated tests attempting cross-tenant access.
-   **Day 22:** Add rate limiting + API keys per company (`company_api_keys` table, middleware verifying header); log usage per key.
-   **Day 23:** Security sweep: enforce signed URLs for downloads, rotate secrets via `.env.example`, enable Laravel audit logs for CRUD actions.
-   **Day 24:** Expand automated tests (feature + unit) covering ingestion, chat flow, feedback; run coverage report; fix flakiness.
-   **Day 25:** Performance tuning: batch chunk inserts, queue batching for embeddings, Qdrant collection index tweaks (`ef_search`); add cache for frequent queries.
-   **Day 26:** Build onboarding wizard: stepper UI guiding company through upload, test chat, and API key retrieval; store progress in `companies.settings`.
-   **Day 27:** Polish UX: markdown rendering for answers, highlight citations, show doc preview on hover, add skeleton loaders + progress bars.
-   **Day 28:** Write documentation (`docs/architecture.md`, `docs/api.md`, `docs/onboarding.md`); include architecture diagram (draw.io/Excalidraw) and sequence diagrams.
-   **Day 29:** Deploy to staging (Forge/Vapor or Docker on VPS); configure managed Qdrant or self-hosted; run smoke tests, monitor logs, validate SSL/domain.
-   **Day 30:** Capture screenshots, record demo video, publish case study (problem, architecture, tech stack, challenges, roadmap); link to live demo + GitHub in portfolio.

---

## 5. Next Steps

1. Commit this plan (`docs/build-plan.md`) as the guiding document.
2. Initialize Laravel project and copy the folder structure.
3. Start with Phase 1 tasks and iterate daily, updating this doc with progress notes.

Good luck building your AI-powered customer support platform!

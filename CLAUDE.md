# CLAUDE.md

Guidance for working in this repository.

## What this is

DocWise is a multi-tenant Laravel 12 API (PHP ^8.4) for RAG-based customer support. Companies upload documents, which are chunked, embedded, and stored in a vector store; an embeddable widget (`public/widget.js`) then chats against that knowledge base. There is no frontend here beyond the widget; the admin UI lives in a separate repo.

## Running it (Docker)

The project is fully containerized, so run PHP, Composer, and Artisan inside the `app` container rather than on the host. The Docker files (`compose.yaml`, `docker/`) come from the `chore/docker-dev-env` branch.

```bash
cp .env.example .env                  # first time: fill in API keys and DB_PASSWORD (required)
docker compose up -d --build          # first boot runs composer install into the vendor volume
docker compose exec app php artisan key:generate   # first time only
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed --class=SuperAdminSeeder

docker compose exec app php artisan test                                # full suite
docker compose exec app php artisan test --filter=VectorStoreManagerTest  # one class or method
docker compose exec app vendor/bin/pint            # code style (Laravel preset); add --test to check only
docker compose exec app composer install           # after changing composer.json
docker compose exec app php artisan qdrant:create-index
docker compose logs -f queue                       # ingestion job output
```

The API and widget are served at `http://localhost:8000` (`APP_PORT`); the widget demo is at `/widget-demo.html`.

| Service | Role | Host port |
| --- | --- | --- |
| `app` | php-fpm, PHP 8.4 (`docker/php/Dockerfile`) | none (reached through nginx) |
| `nginx` | web server | `8000` |
| `queue` | `queue:work --tries=3 --timeout=300` | none |
| `docwise-postgres` | Postgres 16 with pgvector | `5433` |
| `docwise-mysql` | MySQL 8 | `3308` |
| `docwise-qdrant` | Qdrant | `6335` (HTTP), `6336` (gRPC) |

- **Pick one database and vector store pairing.** The default is pgsql with pgvector (`DB_CONNECTION=pgsql`, `VECTOR_STORE_DRIVER=pgsql`). The alternative is MySQL with Qdrant (`DB_CONNECTION=mysql`, `VECTOR_STORE_DRIVER=qdrant`, `DOCKER_DB_HOST=docwise-mysql`, `DOCKER_DB_PORT=3306`). pgvector stores vectors in `document_chunks`, so it only works when Postgres is the main database.
- **Container overrides:** inside the containers, compose sets `DB_HOST`/`DB_PORT` from `DOCKER_DB_HOST`/`DOCKER_DB_PORT`, points `QDRANT_HOST` at `docwise-qdrant`, and sets `PDF_TO_TEXT_PATH=/usr/bin/pdftotext`. The `DB_HOST`/`DB_PORT` values in `.env` are only for running outside Docker.
- **vendor volume:** `vendor/` is a named Docker volume, not the bind mount. The host copy is only for IDE autocompletion, so dependency changes must run through `docker compose exec app composer ...`.
- **Code reloads live:** source is bind-mounted, so code changes need no rebuild. The `queue` worker is long-running, so restart it after changing job or pipeline code: `docker compose restart queue`.
- **Data is kept:** database data lives in named volumes and survives `docker compose down`. `docker compose down -v` deletes it; don't run that unless a clean slate is intended.

Tests run on in-memory SQLite with `QUEUE_CONNECTION=sync` (see `phpunit.xml`). They don't need Postgres, MySQL, Qdrant, or Gemini, so keep new tests that way and mock the contracts instead of calling external APIs.

## Architecture

Request flow: route, then middleware, then controller, then service (via interface), then repository or RAG domain.

- **Controllers** (`app/Http/Controllers/V1/Api`) are thin. They build a `final readonly` DTO from the FormRequest and return through the `ResponseHandler` trait (`respondResource`, `respondMessage`, `respondError`), which produces the `{success, message, data|errors}` envelope. Don't return raw arrays or `response()->json()` from controllers.
- **Services** (`app/Services/V1`) hold business logic, are injected by interface, and take DTOs from `app/Services/V1/DTOs`. Chat is split into small collaborators, orchestrated by `Chat/UseCases/SendMessage`.
- **Repositories** (`app/Repositories/V1`) wrap Eloquent. Most have an interface in `Contracts/` (a few, like `DocumentRepository`, are injected concretely).
- **Bindings** are split across three providers: `ServiceBindingServiceProvider`, `RepositoryBindingServiceProvider`, and `RAGBindingServiceProvider`. `AppServiceProvider` is intentionally empty. A new interface needs a binding added to the matching provider.
- **Exceptions** are rendered centrally in `bootstrap/app.php` through `ApiExceptionHandler`, so every error is JSON in the same envelope. Throw exceptions rather than building error responses in services.

### RAG domain (`app/Domains/RAG`)

Framework-light core: `Contracts/`, immutable `DTOs/`, and implementations grouped by provider (`Embeddings/Gemini`, `LLMs/{Gemini,OpenAI}`, `VectorStores/{Qdrant,PgSQL}`, `Loaders/{Pdf,Docx,Txt}`, `Tokenizers/{Gemini,Tiktoken}`).

- **Ingestion**: `DocumentService::uploadDocument` stores the file on Cloudinary and creates the Document, DocumentVersion, and IngestionJob records inside a transaction, then fires `DocumentUploaded`. `ProcessDocumentUploaded` (auto-discovered listener, so `$listen` is empty) dispatches the `ProcessDocument` job, which runs `DocumentIngestionPipeline`: load, chunk, persist chunks (after clearing any left by an earlier attempt), validate token limits, embed, upsert vectors, record Qdrant IDs. Progress and completion are reported through the `on_progress` and `on_complete` callbacks in `$options`. On success the job fires `DocumentProcessed`, and usage is recorded by its listener rather than the pipeline. `ProcessDocument` is queued after commit, unique per document+version, retries with backoff, and fails straight away on non-retryable errors (token limit, extraction, missing embedding model); its `failed()` hook marks the ingestion job failed.
- **Events**: `DocumentUploaded` → `ProcessDocumentUploaded`; `DocumentProcessed` → `RecordDocumentUsage` (queued); `ChatMessageAnswered` (fired by `SendMessage`) → `UpdateChatSessionStats` (sync, because the response reads the stats) and `RecordChatUsage` (queued). All are auto-discovered; check with `php artisan event:list`.
- **Chat**: `ChatRAGPipeline::execute` embeds the query, searches with a `company_id` filter, and drops low-similarity chunks with `ChunkFilter`. If nothing is relevant it returns a fixed "no information" answer without calling the LLM. Otherwise it builds a context-only system prompt, trims to the token budget with `TokenOptimizer`, and calls the LLM.
- **Chunking** is hybrid: Tiktoken locally for recursive splitting, then the Gemini tokenizer API for final counts.

### Adding drivers

Vector stores and embedding providers are discovered by attribute. There is no map to edit.

- **Vector store**: create a class under `app/Domains/RAG/VectorStores/`, implement `VectorStore`, tag it `#[VectorStoreDriver('name')]`, and add a `vectorstore.drivers.name` config block. `VectorStoreManager` passes that block's keys to the constructor as camelCase named arguments (`vector_column` becomes `$vectorColumn`), and the `driver` key is dropped. Select a store with `VECTOR_STORE_DRIVER`.
- **Embedding provider**: create a class under `app/Domains/RAG/Embeddings/`, implement `EmbeddingProvider`, and tag it `#[EmbeddingDriver('name')]`. The factory picks the first provider whose `supports($model)` returns true.
- Two classes claiming the same driver name throw a `LogicException` at discovery time.
- **Not attribute-based (yet)**: LLM providers are registered in `LLMProviderFactory::registerDefaultProviders()`, and document loaders are mapped by `DocumentFileType` in `DocumentLoaderFactory::$loaderMap`.

## Multi-tenancy (critical)

Each request resolves its company into `$request->attributes->get('current_company_id')`:

- `company.scope` (`ResolveCompany`): a regular user gets their own `company_id`. A super admin must send the `X-Company-Id` header (impersonation), otherwise the request gets a 400.
- `api.key` (`AuthenticateApiKey`): widget routes authenticate with a sha256-hashed company API key sent as `Authorization: Bearer` or `X-API-Key`, and also set `api_key`.

Always read the company from that attribute and scope every query and vector search by it. Never take a company ID from the request body.

Other middleware aliases (`superadmin`, `company.admin`, `api.rate_limit`, `auth.cookie`) are registered in `bootstrap/app.php`. `AuthenticateWithCookie` is prepended to every API route and copies the auth cookie into a Bearer header, so Sanctum sees cookie-authenticated requests as token requests.

## Conventions

- **Enums**: status and type columns are backed string enums in `app/Enums`, cast on the model. Migrations use `$table->enum('col', MyEnum::values())` through the `HasValues` trait, because passing `cases()` breaks SQL generation. Use `tryFrom` on user input.
- **DTOs** are `final readonly`. RAG DTOs are changed through `with*()` methods (for example `ChunkDTO::withEmbedding`), never mutated.
- **Contract implementations** carry `#[\Override]` on each interface method. Keep this when adding methods.
- **Model observers** are registered with `#[ObservedBy]` on the model, not in a provider.
- **Logging**: inject `App\Services\V1\Common\LogService` instead of using the `Log` facade in services and the domain. Classes using the `Retries*` traits must set a `$logService` property.
- **Exceptions**: RAG code uses `App\Domains\RAG\Exceptions\*`. Some of those class names also exist in `App\Exceptions\*`, so check the import.
- **Routes** have no `/v1` prefix despite what `README.md` says; the V1 namespace is code-only. `routes/api.php` is the source of truth. Several docs in `docs/` and the README describe planned rather than implemented endpoints.
- **Style**: 4-space indent, LF line endings. Commit messages use conventional prefixes (`feat:`, `fix:`, `refactor:`).

## Gotchas

- The queue's `retry_after` (`DB_QUEUE_RETRY_AFTER`, default 330) must stay above `ProcessDocument::$timeout` (300), or a long ingestion is picked up by a second worker while still running.
- `DocumentObserver` only assigns a UUID. Don't fire `DocumentUploaded` from a model hook, or documents will be processed twice; `DocumentService` fires it after the version and job exist.
- `Company::getEmbeddingModel()` throws if `settings.embedding_model` is unset, so a company without that setting fails every ingestion job.
- `Company` plan limits read `config('billing.plans')`, but there is no `config/billing.php`, so the hardcoded defaults always apply.
- After ingestion, Qdrant IDs are written back only when `vectorstore.default === 'qdrant'`. The pgsql store keeps vectors in `document_chunks.embedding` (added by a migration that checks both the driver and the DB connection).
- Deleting a document does not remove its vectors yet (TODO in `DocumentService::deleteDocument`), and duplicate-checksum detection on upload is commented out.
- The API key domain allow-list check in `AuthenticateApiKey` is commented out.
- `routes/console.php`'s `ingest:document` command is a debug stub that calls Gemini and `dd()`s the result.
- Ingestion needs real `GEMINI_API_KEY` and `CLOUDINARY_*` credentials: uploads go to Cloudinary, and both embeddings and final token counts call Gemini. Chat also needs `OPENAI_API_KEY` when the chosen LLM model is an OpenAI one.

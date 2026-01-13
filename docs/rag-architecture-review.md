# RAG Architecture Review & Improvement Roadmap

**Date:** January 13, 2026  
**Role:** Senior Architect  
**Project:** DocWise RAG Ingestion Pipeline

---

## 1. Executive Summary

The current implementation of the Document Upload and RAG Ingestion pipeline is architecturally sound, employing a robust **Pipeline Pattern** and adhering to **SOLID** principles. The separation of concerns between standard document management (Laravel Services) and the RAG domain (Pipelines, DTOs, Factories) is excellent.

To transition from a functional prototype to a production-grade enterprise system, the next phase must focus on **operational resilience**, **data efficiency**, and **SDK-backed stability**.

---

## 2. Current Architecture Overview

The ingestion process follows a structured flow:

1. **Load:** Text is extracted from uploaded files (currently supporting text-based formats).
2. **Split:** Text is divided into chunks with overlap using a recursive splitter.
3. **Persist (MySQL):** Metadata and text chunks are stored in the relational database.
4. **Embed:** Vectors are generated via the Gemini/OpenAI API.
5. **Store (Qdrant):** Vectors and payloads are upserted into the Qdrant vector store.
6. **Finalize:** Database records are updated with vector store IDs and processing status.

---

## 3. Priority Improvements

### Priority 1: High (Resilience & Performance)

#### 3.1. Internal Pipeline Retries

-   **Issue:** The pipeline currently relies on the top-level Laravel Job retry. A failure at the final step (Vector Storage) causes the entire job to restart, re-running the expensive embedding generation step.
-   **Recommendation:** Implement granular retry logic with exponential backoff within the `EmbeddingProvider` and `VectorStore` implementations.
-   **Benefit:** Reduces API costs and processing time by preventing redundant work.

#### 3.2. Batch Database Operations

-   **Issue:** The `DocumentIngestionPipeline` performs individual `save()` operations for every chunk to update metadata and Qdrant IDs.
-   **Recommendation:** Refactor `DocumentChunkRepository` to support bulk updates. Collect all IDs and metadata updates into a single batch query.
-   **Benefit:** Dramatically reduces database overhead for large documents.

#### 3.3. Official SDK Integration

-   **Issue:** Raw `Http` client calls are used for OpenAI and Qdrant, making them harder to maintain and less feature-rich.
-   **Recommendation:** Migrate to `openai-php/laravel` and `qdrant/php-client`.
-   **Benefit:** Improved type safety, better handling of large payloads, and built-in error handling.

---

### Priority 2: Medium (Data Scalability & Accuracy)

#### 3.4. Vector Storage Optimization

-   **Issue:** Storing full embedding vectors (e.g., 1536 dimensions) in the MySQL `metadata` column will lead to massive storage bloat and slow down database backups/queries.
-   **Recommendation:** Store only the `qdrant_point_id` in MySQL. Keep the high-dimensional vectors exclusively in Qdrant.
-   **Benefit:** Keeps the relational database lean and performant.

#### 3.5. Precision Tokenization

-   **Issue:** Current chunking uses character counts or basic sentence splitting as proxies for tokens.
-   **Recommendation:** Integrate `tiktoken` for OpenAI/Gemini models to ensure chunks exactly fit the model's context window.
-   **Benefit:** Eliminates "Token Limit Exceeded" errors and ensures optimal retrieval quality.

#### 3.6. Binary File Support

-   **Issue:** PDF and DOCX extraction are currently placeholders.
-   **Recommendation:** Implement the `DocumentTextExtractionService` using `spatie/pdf-to-text` and `phpoffice/phpword`.
-   **Benefit:** Enables the core business use case of processing diverse document types.

---

### Priority 3: Low (Observability & UX)

#### 3.7. Granular Progress Tracking

-   **Recommendation:** Update the `IngestionJob` record with a `progress_percentage` after each major pipeline stage.
-   **Benefit:** Allows the UI to provide real-time feedback to users during long-running uploads.

#### 3.8. Cost & Usage Analytics

-   **Recommendation:** Implement a `UsageMetricService` that tracks estimated and actual token usage per company.
-   **Benefit:** Enables billing integration and internal cost monitoring.

---

## 4. Roadmap

| Phase       | Focus      | Key Deliverables                                             |
| :---------- | :--------- | :----------------------------------------------------------- |
| **Phase 1** | Efficiency | Batch DB updates, Tiktoken integration, PDF Loader           |
| **Phase 2** | Stability  | OpenAI/Qdrant SDKs, Internal Retries, Vector Storage Cleanup |
| **Phase 3** | Production | Progress Tracking, Usage Metrics, DOCX Loader                |

---

_Document created for DocWise Development Team._

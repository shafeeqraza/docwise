# MySQL Database Schema - AI Customer Support System

This document defines the complete MySQL database structure for the Laravel-based AI customer support system with multi-tenant architecture.

---

## Database Design Principles

- **Multi-tenant isolation**: Every tenant-bound table includes `company_id` for data separation
- **UUID public IDs**: Use UUIDs for external API references, auto-incrementing IDs for internal relationships
- **Soft deletes**: Important entities support soft deletion for audit trails
- **JSON columns**: Flexible metadata storage for evolving requirements
- **Proper indexing**: Optimized for common query patterns

---

## Table Definitions

### 1. companies
**Purpose**: Tenant/organization profiles and configuration

```sql
CREATE TABLE companies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(255),
    phone VARCHAR(50),
    status ENUM('active', 'suspended', 'trial') DEFAULT 'active',
    subscription_plan VARCHAR(50) DEFAULT 'basic',
    settings JSON,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    
    INDEX idx_companies_slug (slug),
    INDEX idx_companies_status (status),
    INDEX idx_companies_created (created_at)
);
```

**Key Fields**:
- `settings`: `{"max_documents": 100, "embedding_model": "text-embedding-3-large", "chunk_size": 500}`
- `subscription_plan`: Controls feature access and limits

---

### 2. users
**Purpose**: Admin users, agents, and API users per company

```sql
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) UNIQUE NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    email_verified_at TIMESTAMP NULL DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'admin', 'agent', 'api_user') DEFAULT 'agent',
    api_token VARCHAR(80) UNIQUE NULL,
    permissions JSON,
    last_login_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    UNIQUE KEY unique_email_per_company (company_id, email),
    INDEX idx_users_company (company_id),
    INDEX idx_users_role (role),
    INDEX idx_users_api_token (api_token)
);
```

**Key Fields**:
- `permissions`: `{"can_upload": true, "can_delete_docs": false, "can_view_analytics": true}`
- `api_token`: For programmatic access to upload/chat APIs

---

### 3. documents
**Purpose**: Uploaded files and their metadata

```sql
CREATE TABLE documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) UNIQUE NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(500) NOT NULL,
    description TEXT,
    source_type ENUM('upload', 'url', 'api') DEFAULT 'upload',
    file_type ENUM('pdf', 'docx', 'txt', 'html', 'md') NOT NULL,
    status ENUM('uploaded', 'processing', 'completed', 'failed', 'archived') DEFAULT 'uploaded',
    uploaded_by BIGINT UNSIGNED NOT NULL,
    storage_path VARCHAR(1000) NOT NULL,
    original_filename VARCHAR(500),
    mime_type VARCHAR(100),
    file_size BIGINT UNSIGNED,
    checksum VARCHAR(64),
    language VARCHAR(10) DEFAULT 'en',
    tags JSON,
    metadata JSON,
    processed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_documents_company (company_id),
    INDEX idx_documents_status (status),
    INDEX idx_documents_type (file_type),
    INDEX idx_documents_created (created_at),
    INDEX idx_documents_checksum (checksum)
);
```

**Key Fields**:
- `tags`: `["faq", "policy", "product-manual"]`
- `metadata`: `{"page_count": 45, "author": "John Doe", "category": "support"}`

---

### 4. document_versions
**Purpose**: Track document reprocessing and version history

```sql
CREATE TABLE document_versions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id BIGINT UNSIGNED NOT NULL,
    version INTEGER NOT NULL DEFAULT 1,
    processing_state ENUM('pending', 'parsing', 'chunking', 'embedding', 'completed', 'failed') DEFAULT 'pending',
    chunk_count INTEGER DEFAULT 0,
    token_count INTEGER DEFAULT 0,
    embedding_model VARCHAR(100),
    chunk_strategy JSON,
    error_log TEXT,
    processing_started_at TIMESTAMP NULL DEFAULT NULL,
    processing_completed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    UNIQUE KEY unique_document_version (document_id, version),
    INDEX idx_versions_state (processing_state),
    INDEX idx_versions_created (created_at)
);
```

**Key Fields**:
- `chunk_strategy`: `{"method": "recursive", "chunk_size": 500, "overlap": 100}`
- `error_log`: Detailed failure reasons for debugging

---

### 5. document_chunks
**Purpose**: Text chunks with embeddings, mapped to Qdrant vectors

```sql
CREATE TABLE document_chunks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) UNIQUE NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    document_id BIGINT UNSIGNED NOT NULL,
    version_id BIGINT UNSIGNED NOT NULL,
    chunk_index INTEGER NOT NULL,
    content TEXT NOT NULL,
    content_hash VARCHAR(64),
    token_count INTEGER,
    embedding_model VARCHAR(100),
    qdrant_point_id VARCHAR(100),
    qdrant_collection VARCHAR(100),
    metadata JSON,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (version_id) REFERENCES document_versions(id) ON DELETE CASCADE,
    INDEX idx_chunks_company_doc (company_id, document_id),
    INDEX idx_chunks_version (version_id),
    INDEX idx_chunks_qdrant (qdrant_point_id),
    INDEX idx_chunks_hash (content_hash)
);
```

**Key Fields**:
- `metadata`: `{"page": 5, "heading": "Refund Policy", "section": "Customer Service"}`
- `qdrant_point_id`: UUID linking to vector in Qdrant collection

---

### 6. ingestion_jobs
**Purpose**: Track background job status and failures

```sql
CREATE TABLE ingestion_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) UNIQUE NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    document_id BIGINT UNSIGNED,
    job_type ENUM('parse_document', 'generate_embeddings', 'sync_qdrant', 'cleanup') NOT NULL,
    status ENUM('queued', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'queued',
    priority INTEGER DEFAULT 0,
    attempts INTEGER DEFAULT 0,
    max_attempts INTEGER DEFAULT 3,
    payload JSON,
    progress_data JSON,
    error_message TEXT,
    queued_at TIMESTAMP NULL DEFAULT NULL,
    started_at TIMESTAMP NULL DEFAULT NULL,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    failed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    INDEX idx_jobs_company (company_id),
    INDEX idx_jobs_status (status),
    INDEX idx_jobs_type (job_type),
    INDEX idx_jobs_queued (queued_at)
);
```

**Key Fields**:
- `payload`: Job-specific data like file paths, chunk ranges
- `progress_data`: `{"chunks_processed": 45, "total_chunks": 120}`

---

### 7. chat_sessions
**Purpose**: Conversation threads with external users

```sql
CREATE TABLE chat_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) UNIQUE NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    external_user_id VARCHAR(255),
    channel ENUM('web', 'api', 'widget', 'slack', 'teams') DEFAULT 'web',
    title VARCHAR(500),
    status ENUM('active', 'resolved', 'escalated', 'archived') DEFAULT 'active',
    language VARCHAR(10) DEFAULT 'en',
    user_metadata JSON,
    context JSON,
    message_count INTEGER DEFAULT 0,
    total_tokens INTEGER DEFAULT 0,
    satisfaction_rating INTEGER,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    ended_at TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    INDEX idx_sessions_company (company_id),
    INDEX idx_sessions_external_user (external_user_id),
    INDEX idx_sessions_status (status),
    INDEX idx_sessions_created (created_at)
);
```

**Key Fields**:
- `user_metadata`: `{"name": "John Smith", "email": "john@example.com", "tier": "premium"}`
- `context`: Session-level context like product IDs, case numbers

---

### 8. chat_messages
**Purpose**: Individual messages in conversations

```sql
CREATE TABLE chat_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) UNIQUE NOT NULL,
    session_id BIGINT UNSIGNED NOT NULL,
    role ENUM('user', 'assistant', 'system') NOT NULL,
    content TEXT NOT NULL,
    content_type ENUM('text', 'markdown', 'html') DEFAULT 'text',
    tokens_prompt INTEGER,
    tokens_completion INTEGER,
    model_used VARCHAR(100),
    temperature DECIMAL(3,2),
    latency_ms INTEGER,
    confidence_score DECIMAL(4,3),
    raw_llm_response JSON,
    citations JSON,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (session_id) REFERENCES chat_sessions(id) ON DELETE CASCADE,
    INDEX idx_messages_session (session_id),
    INDEX idx_messages_role (role),
    INDEX idx_messages_created (created_at)
);
```

**Key Fields**:
- `raw_llm_response`: Full API response for debugging
- `citations`: `[{"chunk_id": 123, "document": "FAQ.pdf", "page": 5}]`

---

### 9. retrieved_chunks
**Purpose**: Track which chunks were used as context for each response

```sql
CREATE TABLE retrieved_chunks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message_id BIGINT UNSIGNED NOT NULL,
    chunk_id BIGINT UNSIGNED NOT NULL,
    document_id BIGINT UNSIGNED NOT NULL,
    similarity_score DECIMAL(6,4) NOT NULL,
    rank_position INTEGER NOT NULL,
    used_in_context BOOLEAN DEFAULT TRUE,
    metadata JSON,
    created_at TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (message_id) REFERENCES chat_messages(id) ON DELETE CASCADE,
    FOREIGN KEY (chunk_id) REFERENCES document_chunks(id) ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    INDEX idx_retrieved_message (message_id),
    INDEX idx_retrieved_chunk (chunk_id),
    INDEX idx_retrieved_score (similarity_score)
);
```

**Key Fields**:
- `similarity_score`: Cosine similarity from vector search
- `rank_position`: Order in retrieved results (1 = most relevant)

---

### 10. feedback
**Purpose**: User feedback on AI responses for improvement

```sql
CREATE TABLE feedback (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) UNIQUE NOT NULL,
    message_id BIGINT UNSIGNED NOT NULL,
    session_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED,
    feedback_type ENUM('thumbs_up', 'thumbs_down', 'rating', 'comment') NOT NULL,
    rating INTEGER,
    comment TEXT,
    categories JSON,
    metadata JSON,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (message_id) REFERENCES chat_messages(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES chat_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_feedback_message (message_id),
    INDEX idx_feedback_type (feedback_type),
    INDEX idx_feedback_created (created_at)
);
```

**Key Fields**:
- `categories`: `["accuracy", "helpfulness", "completeness"]`
- `metadata`: Additional context like user agent, page URL

---

### 11. usage_metrics
**Purpose**: Track resource usage for billing and analytics

```sql
CREATE TABLE usage_metrics (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    metric_type ENUM('daily', 'weekly', 'monthly') NOT NULL,
    documents_uploaded INTEGER DEFAULT 0,
    documents_processed INTEGER DEFAULT 0,
    chunks_created INTEGER DEFAULT 0,
    embeddings_generated INTEGER DEFAULT 0,
    chat_sessions INTEGER DEFAULT 0,
    chat_messages INTEGER DEFAULT 0,
    tokens_prompt INTEGER DEFAULT 0,
    tokens_completion INTEGER DEFAULT 0,
    vector_queries INTEGER DEFAULT 0,
    api_calls INTEGER DEFAULT 0,
    storage_bytes BIGINT DEFAULT 0,
    costs JSON,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    UNIQUE KEY unique_company_period (company_id, period_start, metric_type),
    INDEX idx_metrics_company (company_id),
    INDEX idx_metrics_period (period_start, period_end)
);
```

**Key Fields**:
- `costs`: `{"embedding": 2.45, "llm": 15.30, "storage": 0.85}`
- Aggregated daily/weekly/monthly for dashboard charts

---

### 12. company_api_keys
**Purpose**: API authentication and rate limiting per company

```sql
CREATE TABLE company_api_keys (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) UNIQUE NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    key_hash VARCHAR(255) NOT NULL,
    key_prefix VARCHAR(20) NOT NULL,
    permissions JSON,
    rate_limit_per_minute INTEGER DEFAULT 60,
    rate_limit_per_hour INTEGER DEFAULT 1000,
    is_active BOOLEAN DEFAULT TRUE,
    last_used_at TIMESTAMP NULL DEFAULT NULL,
    expires_at TIMESTAMP NULL DEFAULT NULL,
    created_by BIGINT UNSIGNED,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_key_hash (key_hash),
    INDEX idx_api_keys_company (company_id),
    INDEX idx_api_keys_prefix (key_prefix),
    INDEX idx_api_keys_active (is_active)
);
```

**Key Fields**:
- `permissions`: `{"upload": true, "chat": true, "analytics": false}`
- `key_prefix`: First 8 chars for identification (e.g., "cs_live_")

---

## Migration Order

Run migrations in this order to satisfy foreign key constraints:

1. `companies`
2. `users`
3. `documents`
4. `document_versions`
5. `document_chunks`
6. `ingestion_jobs`
7. `chat_sessions`
8. `chat_messages`
9. `retrieved_chunks`
10. `feedback`
11. `usage_metrics`
12. `company_api_keys`

---

## Essential Indexes Summary

```sql
-- Performance-critical indexes
CREATE INDEX idx_document_chunks_company_doc ON document_chunks(company_id, document_id, chunk_index);
CREATE INDEX idx_chat_messages_session_created ON chat_messages(session_id, created_at);
CREATE INDEX idx_retrieved_chunks_message ON retrieved_chunks(message_id);
CREATE INDEX idx_ingestion_jobs_status_queued ON ingestion_jobs(status, queued_at);
CREATE INDEX idx_usage_metrics_company_period ON usage_metrics(company_id, period_start);

-- Multi-tenant isolation
CREATE INDEX idx_all_tables_company_id ON {table_name}(company_id) -- for all tenant tables
```

---

## Sample Data Relationships

```
Company "Acme Corp" (id: 1)
├── User "Admin" (id: 1, role: admin)
├── Document "FAQ.pdf" (id: 1, status: completed)
│   ├── Version 1 (id: 1, chunk_count: 25)
│   └── Chunks 1-25 (company_id: 1, document_id: 1)
├── Chat Session (id: 1, external_user: "customer123")
│   ├── Message "What's your refund policy?" (id: 1, role: user)
│   ├── Retrieved Chunks [chunk_id: 15, 16, 17] (similarity: 0.89, 0.85, 0.82)
│   └── Message "Our refund policy allows..." (id: 2, role: assistant)
└── Usage Metrics (daily, tokens: 1250, queries: 45)
```

This schema supports full multi-tenancy, comprehensive audit trails, and efficient querying for both operational and analytical workloads.

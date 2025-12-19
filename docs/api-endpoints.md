# API Endpoints Documentation

This document outlines all API endpoints for the AI Customer Support system, organized by functionality.

---

## Authentication

All API endpoints require authentication via:

-   **Bearer Token**: `Authorization: Bearer {api_token}` (for company API keys)
-   **Session Auth**: Laravel session authentication (for web interface)

---

## 0. Super Admin Authentication

### POST /api/superadmin/auth/login

**Description**: Authenticate super admin user
**Auth**: None (public endpoint)
**Rate Limit**: 5 attempts per minute per IP
**Body**:

```json
{
    "email": "superadmin@example.com",
    "password": "password123"
}
```

**Response**:

```json
{
    "token": "1|abcdef1234567890...",
    "token_type": "Bearer",
    "user": {
        "id": 1,
        "uuid": "uuid-string",
        "name": "Super Admin",
        "email": "superadmin@example.com",
        "role": "super_admin",
        "is_super_admin": true,
        "can_impersonate": true,
        "last_login_at": "2025-01-15T10:30:00Z"
    }
}
```

**Error Responses**:

-   `401` - Invalid credentials (does not reveal if email exists)
-   `422` - Validation error
-   `403` - Account deactivated
-   `429` - Rate limit exceeded

**Security Features**:

-   Rate limiting: 5 login attempts per minute per IP
-   Password validation: Minimum 8 characters
-   Email normalization: Automatically lowercased and trimmed
-   Account status check: Verifies account is not soft deleted
-   Super admin verification: Only users with `is_super_admin = true` can login
-   Token revocation: Option to revoke existing tokens for single-device login
-   Audit logging: All login attempts are logged in `admin_actions` table

### POST /api/superadmin/auth/logout

**Description**: Logout super admin and revoke current token
**Auth**: Super Admin (Bearer Token required)
**Headers**: `Authorization: Bearer {token}`
**Response**:

```json
{
    "message": "Logged out successfully"
}
```

**Error Responses**:

-   `401` - Unauthenticated or not a super admin
-   `500` - Server error

**Security Features**:

-   Requires valid authentication token
-   Verifies user is super admin
-   Revokes current access token
-   Audit logging: Logs logout action

### GET /api/superadmin/auth/me

**Description**: Get current authenticated super admin user details
**Auth**: Super Admin (Bearer Token required)
**Headers**: `Authorization: Bearer {token}`
**Response**:

```json
{
    "user": {
        "id": 1,
        "uuid": "uuid-string",
        "name": "Super Admin",
        "email": "superadmin@example.com",
        "role": "super_admin",
        "is_super_admin": true,
        "can_impersonate": true,
        "last_login_at": "2025-01-15T10:30:00Z",
        "created_at": "2025-01-01T00:00:00Z"
    }
}
```

**Error Responses**:

-   `401` - Unauthenticated or not a super admin
-   `500` - Server error

---

## 1. Company Management

### GET /api/companies

**Description**: List all companies (Super Admin only)
**Auth**: Super Admin
**Response**: Paginated list of companies with basic info

### POST /api/companies

**Description**: Create a new company
**Auth**: Super Admin
**Body**: `name`, `slug`, `email`, `subscription_plan`, `settings`
**Response**: Created company object

### GET /api/companies/{company}

**Description**: Get company details
**Auth**: Super Admin or Company Admin
**Response**: Full company object with settings

### PUT /api/companies/{company}

**Description**: Update company information
**Auth**: Super Admin or Company Admin
**Body**: `name`, `email`, `phone`, `settings`, `subscription_plan`
**Response**: Updated company object

### DELETE /api/companies/{company}

**Description**: Soft delete a company
**Auth**: Super Admin
**Response**: Success message

### POST /api/companies/{company}/suspend

**Description**: Suspend a company
**Auth**: Super Admin
**Response**: Updated company status

### POST /api/companies/{company}/activate

**Description**: Activate a suspended company
**Auth**: Super Admin
**Response**: Updated company status

---

## 2. User Management

### GET /api/users

**Description**: List users in current company
**Auth**: Company Admin
**Query**: `role`, `search`, `page`, `per_page`
**Response**: Paginated list of users

### POST /api/users

**Description**: Create a new user
**Auth**: Company Admin
**Body**: `name`, `email`, `password`, `role`, `permissions`
**Response**: Created user object (without password)

### GET /api/users/{user}

**Description**: Get user details
**Auth**: Company Admin or Self
**Response**: User object with permissions

### PUT /api/users/{user}

**Description**: Update user information
**Auth**: Company Admin or Self
**Body**: `name`, `email`, `role`, `permissions`
**Response**: Updated user object

### DELETE /api/users/{user}

**Description**: Soft delete a user
**Auth**: Company Admin
**Response**: Success message

### POST /api/users/{user}/generate-api-token

**Description**: Generate new API token for user
**Auth**: Company Admin or Self
**Response**: New API token (shown once)

### POST /api/users/{user}/revoke-api-token

**Description**: Revoke user's API token
**Auth**: Company Admin or Self
**Response**: Success message

---

## 3. Document Management

### GET /api/documents

**Description**: List documents in current company
**Auth**: Company User
**Query**: `status`, `file_type`, `search`, `tags`, `page`, `per_page`
**Response**: Paginated list of documents with metadata

### POST /api/documents

**Description**: Upload a new document
**Auth**: Company User with upload permission
**Body**: `file` (multipart), `title`, `description`, `tags`
**Response**: Document object with processing status

### GET /api/documents/{document}

**Description**: Get document details
**Auth**: Company User
**Response**: Full document object with versions and chunks count

### PUT /api/documents/{document}

**Description**: Update document metadata
**Auth**: Company User
**Body**: `title`, `description`, `tags`
**Response**: Updated document object

### DELETE /api/documents/{document}

**Description**: Soft delete a document
**Auth**: Company Admin
**Response**: Success message

### POST /api/documents/{document}/reprocess

**Description**: Trigger document reprocessing
**Auth**: Company Admin
**Response**: New ingestion job details

### GET /api/documents/{document}/versions

**Description**: List document versions
**Auth**: Company User
**Response**: List of document versions with processing states

### GET /api/documents/{document}/chunks

**Description**: List document chunks
**Auth**: Company User
**Query**: `page`, `per_page`
**Response**: Paginated list of chunks with content preview

### GET /api/documents/{document}/download

**Description**: Download original document file
**Auth**: Company User
**Response**: File download

---

## 4. Document Processing

### GET /api/ingestion-jobs

**Description**: List ingestion jobs
**Auth**: Company Admin
**Query**: `status`, `job_type`, `document_id`, `page`, `per_page`
**Response**: Paginated list of jobs with progress

### GET /api/ingestion-jobs/{job}

**Description**: Get job details
**Auth**: Company Admin
**Response**: Full job object with progress and logs

### POST /api/ingestion-jobs/{job}/retry

**Description**: Retry a failed job
**Auth**: Company Admin
**Response**: Updated job status

### DELETE /api/ingestion-jobs/{job}

**Description**: Cancel a queued job
**Auth**: Company Admin
**Response**: Success message

---

## 5. Chat System

### GET /api/chat-sessions

**Description**: List chat sessions
**Auth**: Company User
**Query**: `status`, `channel`, `external_user_id`, `date_from`, `date_to`, `page`, `per_page`
**Response**: Paginated list of sessions with basic info

### POST /api/chat-sessions

**Description**: Create a new chat session
**Auth**: Company User or API Key
**Body**: `external_user_id`, `channel`, `user_metadata`, `context`
**Response**: Created session object

### GET /api/chat-sessions/{session}

**Description**: Get session details
**Auth**: Company User or Session Owner
**Response**: Full session object with message count

### PUT /api/chat-sessions/{session}

**Description**: Update session metadata
**Auth**: Company User
**Body**: `title`, `status`, `context`
**Response**: Updated session object

### DELETE /api/chat-sessions/{session}

**Description**: Archive a chat session
**Auth**: Company Admin
**Response**: Success message

### GET /api/chat-sessions/{session}/messages

**Description**: List messages in session
**Auth**: Company User or Session Owner
**Query**: `page`, `per_page`
**Response**: Paginated list of messages with citations

### POST /api/chat-sessions/{session}/messages

**Description**: Send a message in session
**Auth**: Company User or Session Owner
**Body**: `content`, `role` (optional, defaults to 'user')
**Response**: User message and AI response

### GET /api/chat-sessions/{session}/export

**Description**: Export session as PDF/JSON
**Auth**: Company User
**Query**: `format` (pdf|json)
**Response**: File download

---

## 6. Widget API (Public)

### POST /api/widget/chat

**Description**: Send message via widget
**Auth**: Company API Key
**Body**: `company_id`, `message`, `session_id`, `user_metadata`
**Response**: Session ID and AI response with citations

### GET /api/widget/sessions/{session}/messages

**Description**: Get widget session messages
**Auth**: Company API Key or Session Token
**Response**: List of messages in session

### POST /api/widget/feedback

**Description**: Submit feedback on widget response
**Auth**: None (anonymous)
**Body**: `message_id`, `type`, `rating`, `comment`
**Response**: Success message

### GET /api/widget/company/{company}/config

**Description**: Get widget configuration
**Auth**: None (public)
**Response**: Widget settings and branding

---

## 7. Analytics & Reporting

### GET /api/analytics/dashboard

**Description**: Get dashboard metrics
**Auth**: Company User
**Query**: `period` (today|week|month|year)
**Response**: Key metrics and charts data

### GET /api/analytics/usage

**Description**: Get usage metrics
**Auth**: Company Admin
**Query**: `period`, `metric_type`, `date_from`, `date_to`
**Response**: Usage statistics and trends

### GET /api/analytics/documents

**Description**: Get document analytics
**Auth**: Company User
**Response**: Document processing stats, popular documents

### GET /api/analytics/chat

**Description**: Get chat analytics
**Auth**: Company User
**Query**: `period`
**Response**: Chat volume, response times, satisfaction scores

### GET /api/analytics/feedback

**Description**: Get feedback analytics
**Auth**: Company Admin
**Query**: `period`, `type`
**Response**: Feedback trends and sentiment analysis

### GET /api/analytics/export

**Description**: Export analytics data
**Auth**: Company Admin
**Query**: `type`, `format`, `date_from`, `date_to`
**Response**: CSV/Excel file download

---

## 8. Feedback Management

### GET /api/feedback

**Description**: List feedback entries
**Auth**: Company User
**Query**: `type`, `rating`, `message_id`, `session_id`, `page`, `per_page`
**Response**: Paginated list of feedback with context

### GET /api/feedback/{feedback}

**Description**: Get feedback details
**Auth**: Company User
**Response**: Full feedback object with message context

### PUT /api/feedback/{feedback}

**Description**: Update feedback (add internal notes)
**Auth**: Company Admin
**Body**: `internal_notes`, `categories`
**Response**: Updated feedback object

### DELETE /api/feedback/{feedback}

**Description**: Delete feedback entry
**Auth**: Company Admin
**Response**: Success message

---

## 9. API Key Management

### GET /api/api-keys

**Description**: List company API keys
**Auth**: Company Admin
**Response**: List of API keys (masked) with usage stats

### POST /api/api-keys

**Description**: Create new API key
**Auth**: Company Admin
**Body**: `name`, `permissions`, `rate_limits`, `expires_at`
**Response**: API key details (key shown once)

### GET /api/api-keys/{key}

**Description**: Get API key details
**Auth**: Company Admin
**Response**: API key info with usage statistics

### PUT /api/api-keys/{key}

**Description**: Update API key settings
**Auth**: Company Admin
**Body**: `name`, `permissions`, `rate_limits`, `is_active`
**Response**: Updated API key object

### DELETE /api/api-keys/{key}

**Description**: Revoke API key
**Auth**: Company Admin
**Response**: Success message

### POST /api/api-keys/{key}/regenerate

**Description**: Regenerate API key
**Auth**: Company Admin
**Response**: New API key (old one invalidated)

---

## 10. Billing & Usage

### GET /api/billing/invoices

**Description**: List company invoices
**Auth**: Company Admin
**Query**: `status`, `year`, `page`, `per_page`
**Response**: Paginated list of invoices

### GET /api/billing/invoices/{invoice}

**Description**: Get invoice details
**Auth**: Company Admin
**Response**: Full invoice with line items

### GET /api/billing/invoices/{invoice}/download

**Description**: Download invoice PDF
**Auth**: Company Admin
**Response**: PDF file download

### GET /api/billing/usage/current

**Description**: Get current billing period usage
**Auth**: Company Admin
**Response**: Current usage vs limits with cost estimates

### GET /api/billing/usage/history

**Description**: Get usage history
**Auth**: Company Admin
**Query**: `months`, `metric_type`
**Response**: Historical usage data

### POST /api/billing/subscription/change

**Description**: Change subscription plan
**Auth**: Company Admin
**Body**: `plan`, `billing_cycle`
**Response**: Updated subscription details

---

## 11. System Administration (Super Admin)

### GET /api/system/metrics

**Description**: Get system-wide metrics
**Auth**: Super Admin
**Response**: Platform statistics and health metrics

### GET /api/system/companies

**Description**: List all companies with stats
**Auth**: Super Admin
**Query**: `status`, `plan`, `search`, `page`, `per_page`
**Response**: Companies with usage and billing info

### GET /api/system/users

**Description**: List all users across companies
**Auth**: Super Admin
**Query**: `role`, `company_id`, `search`, `page`, `per_page`
**Response**: Users with company context

### POST /api/system/impersonate/{company}

**Description**: Impersonate a company
**Auth**: Super Admin
**Response**: Session updated with company context

### POST /api/system/maintenance

**Description**: Enable/disable maintenance mode
**Auth**: Super Admin
**Body**: `enabled`, `message`
**Response**: Maintenance status

### GET /api/system/logs

**Description**: Get system logs
**Auth**: Super Admin
**Query**: `level`, `date_from`, `date_to`, `page`, `per_page`
**Response**: System logs with filtering

### GET /api/system/admin-actions

**Description**: Get admin action audit log
**Auth**: Super Admin
**Query**: `admin_user_id`, `company_id`, `action`, `page`, `per_page`
**Response**: Admin actions with details

---

## 12. Health & Status

### GET /api/health

**Description**: System health check
**Auth**: None
**Response**: System status and component health

### GET /api/status

**Description**: API status and version
**Auth**: None
**Response**: API version, uptime, and feature flags

### GET /api/limits

**Description**: Get current user/company limits
**Auth**: Authenticated User
**Response**: Usage limits and current consumption

---

## Error Responses

All endpoints return consistent error responses:

```json
{
    "error": "Error message",
    "code": "ERROR_CODE",
    "details": {
        "field": ["Validation error message"]
    },
    "trace_id": "uuid-for-debugging"
}
```

**Common HTTP Status Codes**:

-   `200` - Success
-   `201` - Created
-   `400` - Bad Request
-   `401` - Unauthorized
-   `403` - Forbidden
-   `404` - Not Found
-   `422` - Validation Error
-   `429` - Rate Limited
-   `500` - Internal Server Error

---

## Rate Limiting

-   **Widget API**: 100 requests/minute per IP
-   **Company API**: Based on API key settings (default: 60/minute, 1000/hour)
-   **Web Interface**: 1000 requests/hour per user
-   **Super Admin**: No limits

Rate limit headers are included in responses:

-   `X-RateLimit-Limit`
-   `X-RateLimit-Remaining`
-   `X-RateLimit-Reset`

---

## Pagination

List endpoints support pagination with query parameters:

-   `page` - Page number (default: 1)
-   `per_page` - Items per page (default: 15, max: 100)

Response includes pagination metadata:

```json
{
  "data": [...],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 73
  },
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": "..."
  }
}
```

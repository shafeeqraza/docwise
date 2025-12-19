# API Reference Guide

Complete API reference with request/response examples for the AI Customer Support system.

---

## Base URL
- **Production**: `https://your-domain.com/api`
- **Development**: `http://localhost:8000/api`

## Authentication

### API Key Authentication
```http
Authorization: Bearer cs_live_abc123def456...
```

### Session Authentication (Web)
Uses Laravel session cookies for web interface.

---

## 1. Company Management

### List Companies
```http
GET /api/companies
Authorization: Bearer {super_admin_token}
```

**Query Parameters:**
- `page` (int): Page number
- `per_page` (int): Items per page (max 100)
- `search` (string): Search by name or email
- `status` (string): Filter by status (active|suspended|trial)

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "uuid": "123e4567-e89b-12d3-a456-426614174000",
      "name": "Acme Corporation",
      "slug": "acme-corp",
      "email": "admin@acme.com",
      "status": "active",
      "subscription_plan": "professional",
      "created_at": "2024-01-15T10:30:00Z",
      "users_count": 5,
      "documents_count": 23,
      "chat_sessions_count": 156
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 15,
    "total": 42
  }
}
```

### Create Company
```http
POST /api/companies
Authorization: Bearer {super_admin_token}
Content-Type: application/json

{
  "name": "New Company Inc",
  "slug": "new-company",
  "email": "admin@newcompany.com",
  "subscription_plan": "starter",
  "settings": {
    "max_documents": 50,
    "embedding_model": "text-embedding-3-large",
    "chunk_size": 500
  }
}
```

**Response:**
```json
{
  "data": {
    "id": 2,
    "uuid": "456e7890-e89b-12d3-a456-426614174001",
    "name": "New Company Inc",
    "slug": "new-company",
    "email": "admin@newcompany.com",
    "status": "active",
    "subscription_plan": "starter",
    "settings": {
      "max_documents": 50,
      "embedding_model": "text-embedding-3-large",
      "chunk_size": 500
    },
    "created_at": "2024-01-20T14:25:00Z"
  }
}
```

---

## 2. Document Management

### Upload Document
```http
POST /api/documents
Authorization: Bearer {api_token}
Content-Type: multipart/form-data

file: [binary file data]
title: "Product FAQ"
description: "Frequently asked questions about our products"
tags: ["faq", "products", "support"]
```

**Response:**
```json
{
  "data": {
    "id": 15,
    "uuid": "789e0123-e89b-12d3-a456-426614174002",
    "title": "Product FAQ",
    "description": "Frequently asked questions about our products",
    "file_type": "pdf",
    "status": "uploaded",
    "file_size": 2048576,
    "tags": ["faq", "products", "support"],
    "created_at": "2024-01-20T15:30:00Z",
    "processing_job": {
      "id": 42,
      "uuid": "job-uuid-here",
      "status": "queued",
      "estimated_completion": "2024-01-20T15:35:00Z"
    }
  }
}
```

### List Documents
```http
GET /api/documents?status=completed&file_type=pdf&page=1&per_page=20
Authorization: Bearer {api_token}
```

**Response:**
```json
{
  "data": [
    {
      "id": 15,
      "uuid": "789e0123-e89b-12d3-a456-426614174002",
      "title": "Product FAQ",
      "description": "Frequently asked questions about our products",
      "file_type": "pdf",
      "status": "completed",
      "file_size": 2048576,
      "file_size_formatted": "2.0 MB",
      "tags": ["faq", "products", "support"],
      "chunks_count": 45,
      "processed_at": "2024-01-20T15:34:00Z",
      "created_at": "2024-01-20T15:30:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 2,
    "per_page": 20,
    "total": 23
  }
}
```

---

## 3. Chat System

### Send Message (Widget)
```http
POST /api/widget/chat
Authorization: Bearer {company_api_key}
Content-Type: application/json

{
  "company_id": 1,
  "message": "What is your refund policy?",
  "session_id": "sess_abc123",
  "user_metadata": {
    "name": "John Doe",
    "email": "john@example.com",
    "user_id": "customer_456"
  }
}
```

**Response:**
```json
{
  "session_id": "sess_abc123",
  "message": {
    "id": 89,
    "uuid": "msg-uuid-here",
    "role": "assistant",
    "content": "Our refund policy allows returns within 30 days of purchase. Here are the key details:\n\n• Full refund for unused items\n• Original receipt required\n• Shipping costs are non-refundable\n\nFor digital products, refunds are available within 7 days if you haven't accessed the content.",
    "content_type": "markdown",
    "citations": [
      {
        "chunk_id": 156,
        "document_id": 15,
        "document_title": "Product FAQ",
        "page": 3,
        "similarity_score": 0.89,
        "preview": "Refund Policy: We offer full refunds within 30 days..."
      }
    ],
    "confidence_score": 0.92,
    "tokens_prompt": 245,
    "tokens_completion": 87,
    "latency_ms": 1250,
    "created_at": "2024-01-20T16:15:00Z"
  }
}
```

### List Chat Sessions
```http
GET /api/chat-sessions?status=active&date_from=2024-01-01&page=1
Authorization: Bearer {api_token}
```

**Response:**
```json
{
  "data": [
    {
      "id": 25,
      "uuid": "sess_abc123",
      "external_user_id": "customer_456",
      "channel": "widget",
      "title": "Refund Policy Question",
      "status": "active",
      "message_count": 4,
      "total_tokens": 456,
      "user_metadata": {
        "name": "John Doe",
        "email": "john@example.com"
      },
      "created_at": "2024-01-20T16:10:00Z",
      "last_message_at": "2024-01-20T16:15:00Z",
      "last_message": {
        "role": "assistant",
        "content": "Our refund policy allows returns within 30 days...",
        "created_at": "2024-01-20T16:15:00Z"
      }
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 8,
    "per_page": 15,
    "total": 156
  }
}
```

---

## 4. Analytics

### Dashboard Metrics
```http
GET /api/analytics/dashboard?period=month
Authorization: Bearer {api_token}
```

**Response:**
```json
{
  "data": {
    "period": "month",
    "period_label": "January 2024",
    "metrics": {
      "documents_uploaded": 12,
      "documents_processed": 11,
      "chat_sessions": 89,
      "chat_messages": 267,
      "total_tokens": 45678,
      "average_response_time": 1.8,
      "satisfaction_rating": 4.2
    },
    "trends": {
      "documents_uploaded": {
        "value": 12,
        "change": 8.3,
        "direction": "up"
      },
      "chat_sessions": {
        "value": 89,
        "change": -5.2,
        "direction": "down"
      }
    },
    "charts": {
      "chat_volume": [
        {"date": "2024-01-01", "sessions": 3, "messages": 12},
        {"date": "2024-01-02", "sessions": 5, "messages": 18}
      ],
      "token_usage": [
        {"date": "2024-01-01", "tokens": 1234},
        {"date": "2024-01-02", "tokens": 2156}
      ]
    }
  }
}
```

### Usage Metrics
```http
GET /api/analytics/usage?metric_type=daily&date_from=2024-01-01&date_to=2024-01-31
Authorization: Bearer {api_token}
```

**Response:**
```json
{
  "data": {
    "current_usage": {
      "tokens_used": 45678,
      "tokens_limit": 200000,
      "tokens_remaining": 154322,
      "percentage_used": 22.8,
      "estimated_overage": 0
    },
    "usage_history": [
      {
        "period_start": "2024-01-01",
        "period_end": "2024-01-01",
        "metric_type": "daily",
        "tokens_prompt": 1234,
        "tokens_completion": 567,
        "chat_sessions": 3,
        "chat_messages": 12,
        "vector_queries": 15,
        "api_calls": 28
      }
    ],
    "projections": {
      "monthly_tokens": 198456,
      "monthly_cost": 89.50,
      "overage_risk": "low"
    }
  }
}
```

---

## 5. Feedback Management

### Submit Feedback
```http
POST /api/widget/feedback
Content-Type: application/json

{
  "message_id": 89,
  "type": "thumbs_up",
  "comment": "Very helpful response, exactly what I needed!"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Feedback submitted successfully"
}
```

### List Feedback
```http
GET /api/feedback?type=thumbs_down&page=1
Authorization: Bearer {api_token}
```

**Response:**
```json
{
  "data": [
    {
      "id": 12,
      "uuid": "feedback-uuid",
      "feedback_type": "thumbs_down",
      "comment": "The response was not accurate for my specific situation",
      "created_at": "2024-01-20T16:20:00Z",
      "message": {
        "id": 87,
        "content": "Based on our policy, you can return items within...",
        "session": {
          "id": 24,
          "external_user_id": "customer_789"
        }
      }
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 15,
    "total": 34
  }
}
```

---

## 6. API Key Management

### Create API Key
```http
POST /api/api-keys
Authorization: Bearer {admin_token}
Content-Type: application/json

{
  "name": "Production Widget Key",
  "permissions": ["chat", "upload"],
  "rate_limit_per_minute": 100,
  "rate_limit_per_hour": 2000,
  "expires_at": "2025-01-20T00:00:00Z"
}
```

**Response:**
```json
{
  "data": {
    "id": 5,
    "uuid": "key-uuid-here",
    "name": "Production Widget Key",
    "key": "cs_live_abc123def456ghi789jkl012mno345pqr678",
    "key_prefix": "cs_live_abc1",
    "permissions": ["chat", "upload"],
    "rate_limit_per_minute": 100,
    "rate_limit_per_hour": 2000,
    "is_active": true,
    "expires_at": "2025-01-20T00:00:00Z",
    "created_at": "2024-01-20T17:00:00Z"
  },
  "warning": "This API key will only be shown once. Please store it securely."
}
```

### List API Keys
```http
GET /api/api-keys
Authorization: Bearer {admin_token}
```

**Response:**
```json
{
  "data": [
    {
      "id": 5,
      "uuid": "key-uuid-here",
      "name": "Production Widget Key",
      "key_masked": "cs_live_abc1...******************",
      "permissions": ["chat", "upload"],
      "rate_limit_per_minute": 100,
      "rate_limit_per_hour": 2000,
      "is_active": true,
      "last_used_at": "2024-01-20T16:45:00Z",
      "expires_at": "2025-01-20T00:00:00Z",
      "usage_today": 234,
      "created_at": "2024-01-20T17:00:00Z"
    }
  ]
}
```

---

## 7. Billing

### Current Usage
```http
GET /api/billing/usage/current
Authorization: Bearer {admin_token}
```

**Response:**
```json
{
  "data": {
    "billing_period": {
      "start": "2024-01-01",
      "end": "2024-01-31",
      "days_remaining": 11
    },
    "subscription": {
      "plan": "professional",
      "billing_cycle": "monthly",
      "next_billing_date": "2024-02-01"
    },
    "usage": {
      "tokens_used": 145678,
      "tokens_limit": 200000,
      "tokens_remaining": 54322,
      "percentage_used": 72.8,
      "documents_uploaded": 23,
      "documents_limit": 500,
      "chat_sessions": 156,
      "api_calls": 2847
    },
    "costs": {
      "base_amount": 99.00,
      "overage_amount": 0.00,
      "estimated_total": 99.00,
      "breakdown": {
        "subscription": 99.00,
        "token_overage": 0.00,
        "storage_overage": 0.00
      }
    },
    "projections": {
      "end_of_period_tokens": 198456,
      "estimated_overage": 0.00,
      "overage_risk": "low"
    }
  }
}
```

### List Invoices
```http
GET /api/billing/invoices?year=2024&status=paid
Authorization: Bearer {admin_token}
```

**Response:**
```json
{
  "data": [
    {
      "id": 8,
      "invoice_number": "INV-202401-0008",
      "period_start": "2024-01-01",
      "period_end": "2024-01-31",
      "period_label": "Jan 1 - Jan 31, 2024",
      "base_amount": 99.00,
      "overage_amount": 12.50,
      "total_amount": 111.50,
      "status": "paid",
      "paid_at": "2024-02-01T10:15:00Z",
      "stripe_invoice_id": "in_1234567890",
      "payment_url": "https://invoice.stripe.com/i/in_1234567890",
      "created_at": "2024-02-01T09:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 2,
    "per_page": 15,
    "total": 18
  }
}
```

---

## 8. System Administration

### System Metrics (Super Admin)
```http
GET /api/system/metrics
Authorization: Bearer {super_admin_token}
```

**Response:**
```json
{
  "data": {
    "overview": {
      "total_companies": 42,
      "active_companies": 38,
      "total_users": 156,
      "total_documents": 1247,
      "total_chat_sessions": 8934,
      "monthly_revenue": 4567.89
    },
    "growth": {
      "new_companies_this_month": 3,
      "new_users_this_month": 12,
      "revenue_growth": 8.5
    },
    "usage": {
      "total_tokens_this_month": 2456789,
      "total_api_calls_this_month": 45678,
      "average_response_time": 1.2,
      "system_uptime": 99.8
    },
    "top_companies": [
      {
        "id": 1,
        "name": "Acme Corporation",
        "tokens_used": 89456,
        "revenue": 299.00,
        "chat_sessions": 234
      }
    ]
  }
}
```

---

## Error Responses

### Validation Error (422)
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": [
      "The email field is required."
    ],
    "name": [
      "The name must be at least 2 characters."
    ]
  }
}
```

### Authentication Error (401)
```json
{
  "message": "Unauthenticated.",
  "error": "Invalid or expired API token"
}
```

### Authorization Error (403)
```json
{
  "message": "This action is unauthorized.",
  "error": "Insufficient permissions for this operation"
}
```

### Rate Limit Error (429)
```json
{
  "message": "Too Many Requests",
  "error": "Rate limit exceeded. Try again in 60 seconds.",
  "retry_after": 60
}
```

### Server Error (500)
```json
{
  "message": "Server Error",
  "error": "An unexpected error occurred. Please try again later.",
  "trace_id": "abc123-def456-ghi789"
}
```

---

## Rate Limiting Headers

All API responses include rate limiting headers:

```http
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 999
X-RateLimit-Reset: 1642694400
```

---

## Webhooks (Future)

The system supports webhooks for real-time notifications:

### Document Processing Complete
```json
{
  "event": "document.processing.completed",
  "data": {
    "document_id": 15,
    "company_id": 1,
    "status": "completed",
    "chunks_created": 45,
    "processing_time": 234
  },
  "timestamp": "2024-01-20T15:34:00Z"
}
```

### Chat Session Started
```json
{
  "event": "chat.session.started",
  "data": {
    "session_id": 25,
    "company_id": 1,
    "channel": "widget",
    "external_user_id": "customer_456"
  },
  "timestamp": "2024-01-20T16:10:00Z"
}
```

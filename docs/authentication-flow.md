# Authentication & Authorization Architecture

Complete guide to authentication and authorization for the AI Customer Support platform using **token-based authentication**.

---

## Table of Contents

1. [Overview](#1-overview)
2. [Authentication Strategy](#2-authentication-strategy)
3. [User Roles & Permissions](#3-user-roles--permissions)
4. [Complete Login Flow](#4-complete-login-flow)
5. [Portal Access Control](#5-portal-access-control)
6. [Multi-Tenant Data Isolation](#6-multi-tenant-data-isolation)
7. [API Authentication Methods](#7-api-authentication-methods)
8. [Route Structure](#8-route-structure)
9. [Security Considerations](#9-security-considerations)

---

## 1. Overview

### Architecture Summary

The platform uses a **pure API backend** with token-based authentication:

-   **API Backend** - RESTful API endpoints for all operations
-   **Frontend** - Separate repository consuming the API
-   **Token Authentication** - Laravel Sanctum tokens for all authenticated requests

### Key Technologies

-   **Backend:** Laravel (Token-based authentication via Sanctum)
-   **API:** RESTful JSON API
-   **Frontend:** Separate repository (React, Vue, or any framework)

### Authentication Types

| Portal/API     | Authentication Type | Mechanism              | Use Case                   |
| -------------- | ------------------- | ---------------------- | -------------------------- |
| **Admin API**  | Stateless (Token)   | Sanctum Personal Token | Admin dashboard (frontend) |
| **System API** | Stateless (Token)   | Sanctum Personal Token | Super admin dashboard      |
| **Widget API** | Stateless (Token)   | Company API Keys       | External chat widget       |
| **Public API** | None                | N/A                    | Public endpoints           |

---

## 2. Authentication Strategy

### 2.1 Token-Based Authentication (Sanctum)

**Why Token-Based?**

-   Frontend is a separate application (SPA or mobile app)
-   Stateless authentication allows horizontal scaling
-   Better for API-first architecture
-   Works seamlessly with any frontend framework

**Mechanism:**

-   **Token Storage:** Laravel Sanctum stores tokens in `personal_access_tokens` table
-   **Token Format:** Plain text token returned on login
-   **Header Format:** `Authorization: Bearer {token}`
-   **Expiration:** Configurable per token (default: no expiration)
-   **Revocation:** Tokens can be revoked individually

**Frontend Behavior:**

-   Frontend stores token in memory or secure storage (not localStorage for sensitive apps)
-   Token included in `Authorization` header for every request
-   Frontend handles token refresh if needed
-   Frontend handles logout by calling logout endpoint

**Code Example:**

```php
// Login endpoint returns token
public function login(LoginRequest $request)
{
    $user = User::where('email', $request->email)->first();

    if (!Hash::check($request->password, $user->password)) {
        return response()->json(['error' => 'Invalid credentials'], 401);
    }

    // Revoke existing tokens (optional - for single device)
    $user->tokens()->delete();

    // Create new token
    $token = $user->createToken('api-access')->plainTextToken;

    return response()->json([
        'token' => $token,
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_super_admin' => $user->is_super_admin,
            'company' => $user->company ? [
                'id' => $user->company->id,
                'name' => $user->company->name,
                'slug' => $user->company->slug,
            ] : null,
        ],
    ]);
}
```

### 2.2 Stateless Authentication (Company API Keys)

**When Used:**

-   Chat Widget embedded on customer websites
-   Webhook endpoints
-   Third-party integrations
-   Server-to-server communication

**Mechanism:**

-   **Company API Keys:** Stored in `company_api_keys` table
-   **Header Format:** `Authorization: Bearer {api_key}` or `X-API-Key: {api_key}`
-   **Validation:** `ApiKeyMiddleware` checks key hash against database
-   **Scoping:** Request automatically scoped to `company_id`

**Differences from Personal Tokens:**

| Aspect           | Personal Tokens (Sanctum) | API Keys        |
| ---------------- | ------------------------- | --------------- |
| **Storage**      | Database lookup           | Database lookup |
| **User Context** | Authenticated user        | Company context |
| **Expiration**   | Configurable              | Key expiration  |
| **Permissions**  | User-level                | Company-level   |
| **Revocation**   | Per token                 | Per key         |

---

## 3. User Roles & Permissions

### 3.1 Role Definitions

| Role              | `role` Column | `is_super_admin` | Scope   | Description                                            |
| ----------------- | ------------- | ---------------- | ------- | ------------------------------------------------------ |
| **Super Admin**   | `super_admin` | `true`           | Global  | Platform administrators with access to all companies   |
| **Company Admin** | `admin`       | `false`          | Company | Can manage users, documents, billing for their company |
| **Agent**         | `agent`       | `false`          | Company | Can upload docs, view chats, manage content            |
| **API User**      | `api_user`    | `false`          | Company | Restricted programmatic access (sync scripts, etc.)    |

### 3.2 Permission Matrix

| Action                    | Super Admin | Company Admin | Agent | API User |
| ------------------------- | ----------- | ------------- | ----- | -------- |
| **View System Metrics**   | ✅          | ❌            | ❌    | ❌       |
| **Manage Companies**      | ✅          | ❌            | ❌    | ❌       |
| **Manage Company Users**  | ✅          | ✅            | ❌    | ❌       |
| **Upload Documents**      | ✅          | ✅            | ✅    | ✅       |
| **View Chat Sessions**    | ✅          | ✅            | ✅    | ✅       |
| **Manage Billing**        | ✅          | ✅            | ❌    | ❌       |
| **Generate API Keys**     | ✅          | ✅            | ❌    | ❌       |
| **Impersonate Companies** | ✅          | ❌            | ❌    | ❌       |

### 3.3 Access Scope

-   **Super Admin:** Global access, can view/manage all companies
-   **Company Users:** Scoped to their `company_id`, cannot access other companies' data
-   **API Keys:** Scoped to `company_id`, no user context

---

## 4. Complete Login Flow

### Step-by-Step Process

```
Frontend → Login API → Credentials → Validation → Token Generation → Token Return → Frontend Storage
```

### Detailed Steps

#### **Step 1: Frontend Sends Login Request**

-   **URL:** `POST /api/auth/login`
-   **Payload:**
    ```json
    {
        "email": "admin@company.com",
        "password": "secret123",
        "remember": false
    }
    ```
-   **Headers:** `Content-Type: application/json`

#### **Step 2: Backend Validation**

-   **Laravel checks:**
    -   Email exists in `users` table
    -   Password hash matches
    -   User account is active (`deleted_at IS NULL`)
    -   Company is active (if not super admin)

#### **Step 3: Token Generation**

Backend creates Sanctum token:

```php
// app/Http/Controllers/Api/Auth/LoginController.php

public function login(LoginRequest $request)
{
    $request->authenticate();

    $user = $request->user();

    // Optional: Revoke existing tokens for single-device login
    // $user->tokens()->delete();

    // Create new token
    $token = $user->createToken('api-access', [
        'company_id' => $user->company_id,
        'role' => $user->role,
    ])->plainTextToken;

    return response()->json([
        'token' => $token,
        'token_type' => 'Bearer',
        'user' => new UserResource($user),
    ]);
}
```

#### **Step 4: Token Return**

Response includes token and user data:

```json
{
    "token": "1|abcdef1234567890...",
    "token_type": "Bearer",
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "admin@company.com",
        "role": "admin",
        "is_super_admin": false,
        "company": {
            "id": 1,
            "name": "Acme Corporation",
            "slug": "acme-corp"
        }
    }
}
```

#### **Step 5: Frontend Token Storage**

Frontend stores token securely:

```javascript
// Example: React/Next.js
const response = await fetch("/api/auth/login", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ email, password }),
});

const data = await response.json();

// Store token (choose based on security needs)
localStorage.setItem("token", data.token); // For web apps
// OR
sessionStorage.setItem("token", data.token); // Session-based
// OR
// Store in memory only (most secure, but lost on refresh)
```

#### **Step 6: Subsequent Requests**

Frontend includes token in all authenticated requests:

```javascript
// Example: Axios interceptor
axios.interceptors.request.use((config) => {
    const token = localStorage.getItem("token");
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});
```

### Login Flow Diagram

```
┌─────────────┐
│  Frontend   │
│  Login Form │
└──────┬──────┘
       │ POST /api/auth/login
       │ { email, password }
       ▼
┌─────────────────┐
│ Laravel Auth    │
│ Validation      │
└──────┬──────────┘
       │ Success
       ▼
┌─────────────────┐
│ Token Created   │
│ (Sanctum)       │
└──────┬──────────┘
       │
       ▼
┌─────────────────┐
│ Response        │
│ { token, user }  │
└──────┬──────────┘
       │
       ▼
┌─────────────────┐
│ Frontend Stores │
│ Token           │
└─────────────────┘
```

---

## 5. Portal Access Control

### 5.1 Company Portal API (`/api/admin`)

**Purpose:** Tenant-scoped API endpoints for company users

**Middleware Stack:**

```php
Route::middleware(['auth:sanctum', 'company.scope'])
    ->prefix('api/admin')
    ->group(function () {
        // Routes here
    });
```

**Access Control:**

-   ✅ Requires authentication (`auth:sanctum` middleware)
-   ✅ Requires company assignment (`company.scope` middleware)
-   ✅ Data automatically filtered by `company_id`
-   ❌ Cannot access other companies' data

**Features:**

-   Document management endpoints
-   Chat session endpoints
-   Analytics endpoints (company-scoped)
-   User management endpoints (within company)
-   Billing & settings endpoints

**Example Request:**

```http
GET /api/admin/documents
Authorization: Bearer 1|abcdef1234567890...
```

### 5.2 Super Admin Portal API (`/api/system`)

**Purpose:** Global API endpoints for platform administrators

**Middleware Stack:**

```php
Route::middleware(['auth:sanctum', 'super.admin'])
    ->prefix('api/system')
    ->group(function () {
        // Routes here
    });
```

**Access Control:**

-   ✅ Requires authentication
-   ✅ Requires `is_super_admin = true` flag
-   ✅ Can access all companies' data
-   ✅ Can manage companies

**Features:**

-   Company management endpoints
-   System-wide analytics endpoints
-   Billing management endpoints
-   User management endpoints (all companies)
-   Audit log endpoints
-   System settings endpoints

**Example Request:**

```http
GET /api/system/companies
Authorization: Bearer 1|abcdef1234567890...
```

### 5.3 Logout Flow

**Purpose:** Revoke authentication token

**Endpoint:**

```http
POST /api/auth/logout
Authorization: Bearer {token}
```

**Implementation:**

```php
public function logout(Request $request)
{
    // Revoke current token
    $request->user()->currentAccessToken()->delete();

    // OR revoke all tokens
    // $request->user()->tokens()->delete();

    return response()->json([
        'message' => 'Logged out successfully',
    ]);
}
```

**Frontend Implementation:**

```javascript
const logout = async () => {
    await fetch("/api/auth/logout", {
        method: "POST",
        headers: {
            Authorization: `Bearer ${token}`,
        },
    });

    // Remove token from storage
    localStorage.removeItem("token");

    // Redirect to login
    window.location.href = "/login";
};
```

---

## 6. Multi-Tenant Data Isolation

### 6.1 CompanyScopeMiddleware

**Purpose:** Automatically scope all database queries to user's company

**Implementation:**

```php
// app/Http/Middleware/CompanyScopeMiddleware.php

public function handle($request, $next)
{
    $user = $request->user();

    // 1. Check authentication
    if (!$user) {
        abort(401, 'Unauthenticated');
    }

    // 2. Super Admin Bypass
    if ($user->isSuperAdmin()) {
        // Super admin can access all companies
        // Optionally set company_id from query parameter
        if ($request->has('company_id')) {
            app()->instance('current_company_id', $request->company_id);
        }
        return $next($request);
    }

    // 3. Regular User Scoping
    if (!$user->company_id) {
        abort(403, 'No company assigned');
    }

    // Set global company ID for request lifecycle
    app()->instance('current_company_id', $user->company_id);

    return $next($request);
}
```

**How It Works:**

-   Sets `current_company_id` in Laravel's service container
-   Available throughout request lifecycle via `app('current_company_id')`
-   Models use this value for automatic filtering

### 6.2 Global Scope on Models

**Purpose:** Automatically filter queries by `company_id` without manual `where` clauses

**Implementation:**

```php
// app/Models/Document.php

use Illuminate\Database\Eloquent\Builder;

protected static function boot()
{
    parent::boot();

    // Add global scope
    static::addGlobalScope('company', function (Builder $builder) {
        if (app()->has('current_company_id')) {
            $builder->where('company_id', app('current_company_id'));
        }
    });
}
```

**Benefits:**

-   ✅ No need to manually add `->where('company_id', ...)` to every query
-   ✅ Prevents accidental data leakage
-   ✅ Works with relationships automatically
-   ✅ Can be bypassed with `withoutGlobalScope('company')` if needed

**Example Usage:**

```php
// This query automatically filters by company_id
$documents = Document::all();
// SQL: SELECT * FROM documents WHERE company_id = 1

// Works with relationships too
$company = Company::find(1);
$documents = $company->documents; // Already scoped

// Bypass scope if needed (super admin only)
$allDocuments = Document::withoutGlobalScope('company')->get();
```

### 6.3 Models with Company Scoping

The following models automatically use company scoping:

-   ✅ `Document`
-   ✅ `DocumentChunk`
-   ✅ `ChatSession`
-   ✅ `ChatMessage`
-   ✅ `IngestionJob`
-   ✅ `UsageMetric`
-   ✅ `CompanyApiKey`
-   ✅ `Feedback`

**Models WITHOUT Company Scoping:**

-   ❌ `Company` (needs global access)
-   ❌ `User` (scoped via `company_id` but not global scope)
-   ❌ `BillingInvoice` (scoped via `company_id` but not global scope)

---

## 7. API Authentication Methods

### 7.1 Personal Access Tokens (Sanctum)

**Use Case:** Admin dashboards, user-specific API access

**How It Works:**

1. **Token Generation:**

    ```php
    $token = $user->createToken('api-access', [
        'company_id' => $user->company_id,
        'role' => $user->role,
    ])->plainTextToken;
    ```

2. **Storage:**

    - Token stored in `personal_access_tokens` table
    - Token name stored for identification
    - Abilities/permissions stored as JSON
    - Last used timestamp tracked

3. **Authentication:**

    ```http
    Authorization: Bearer 1|abcdef1234567890...
    ```

4. **Validation:**

    ```php
    // Sanctum middleware automatically validates token
    // $request->user() returns authenticated user
    ```

5. **Scoping:**
    - Request automatically scoped to user's `company_id`
    - User context available via `$request->user()`

**Token Abilities:**

```php
// Create token with specific abilities
$token = $user->createToken('api-access', [
    'documents:read',
    'documents:write',
    'chat:read',
])->plainTextToken;

// Check abilities in middleware or controller
if (!$request->user()->tokenCan('documents:write')) {
    abort(403);
}
```

### 7.2 Company API Keys

**Use Case:** External integrations, chat widget, webhooks

**How It Works:**

1. **Key Generation:**

    ```php
    $keyData = CompanyApiKey::generateKey('cs_live');
    // Returns: ['key' => 'cs_live_abc123...', 'hash' => 'sha256...', 'prefix' => 'cs_live_abc1']
    ```

2. **Storage:**

    - Key stored in `company_api_keys` table
    - Only hash stored (never plain key after generation)
    - Prefix stored for identification

3. **Authentication:**

    ```http
    Authorization: Bearer cs_live_abc123def456...
    # OR
    X-API-Key: cs_live_abc123def456...
    ```

4. **Validation:**

    ```php
    // ApiKeyMiddleware validates
    $apiKey = $request->bearerToken() ?? $request->header('X-API-Key');
    $keyHash = hash('sha256', $apiKey);
    $keyRecord = CompanyApiKey::where('key_hash', $keyHash)
        ->where('expires_at', '>', now())
        ->first();
    ```

5. **Scoping:**
    - Request automatically scoped to `company_id`
    - No user context (acts as company itself)

**Rate Limiting:**

-   Per-key limits: `rate_limit_per_minute`, `rate_limit_per_hour`
-   Tracked via Laravel's `RateLimiter`
-   Headers returned: `X-RateLimit-Limit`, `X-RateLimit-Remaining`

**Differences:**

| Feature          | Sanctum Tokens | API Keys     |
| ---------------- | -------------- | ------------ |
| **Scope**        | User           | Company      |
| **User Context** | Yes            | No           |
| **Permissions**  | Token-level    | Key-level    |
| **Expiration**   | Configurable   | Configurable |
| **Revocation**   | Per token      | Per key      |

---

## 8. Route Structure

### 8.1 Public Routes

**Public API Routes:**

```php
Route::prefix('api')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/register', [AuthController::class, 'register']); // If applicable
    Route::get('/health', [HealthController::class, 'health']);
    Route::get('/status', [HealthController::class, 'status']);
});
```

**Widget Routes:**

```php
Route::get('/widget/{company:slug}', [WidgetController::class, 'show']);
```

### 8.2 Authenticated API Routes

**Company Portal Routes:**

```php
Route::middleware(['auth:sanctum', 'company.scope'])
    ->prefix('api/admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);
        Route::apiResource('documents', DocumentController::class);
        Route::apiResource('chat-sessions', ChatController::class);
        Route::get('/analytics', [AnalyticsController::class, 'index']);
        // ... more routes
    });
```

**System Portal Routes:**

```php
Route::middleware(['auth:sanctum', 'super.admin'])
    ->prefix('api/system')
    ->name('system.')
    ->group(function () {
        Route::get('/dashboard', [SystemController::class, 'dashboard']);
        Route::apiResource('companies', CompanyController::class);
        Route::get('/metrics', [SystemController::class, 'metrics']);
        // ... more routes
    });
```

**Widget API Routes:**

```php
Route::prefix('api/widget')
    ->middleware('api.key')
    ->group(function () {
        Route::post('/chat', [WidgetApiController::class, 'chat']);
        Route::get('/sessions/{session}/messages', [WidgetApiController::class, 'getMessages']);
        Route::post('/feedback', [WidgetApiController::class, 'feedback']);
    });
```

### 8.3 Middleware Order

**Important:** Middleware executes in order:

```
Request → auth:sanctum → company.scope → super.admin → Controller
```

**Why Order Matters:**

-   `auth:sanctum` must run first (validates token, establishes user context)
-   `company.scope` needs authenticated user
-   `super.admin` checks user after authentication

---

## 9. Security Considerations

### 9.1 Token Security

**Best Practices:**

-   ✅ **HTTPS Only:** Always use HTTPS in production
-   ✅ **Token Storage:** Store tokens securely (not in localStorage for sensitive apps)
-   ✅ **Token Expiration:** Set expiration dates for tokens
-   ✅ **Token Rotation:** Implement token refresh mechanism
-   ✅ **Revocation:** Immediate revocation capability

**Token Storage Options:**

```javascript
// Option 1: Memory only (most secure, lost on refresh)
let token = null; // Store in component state

// Option 2: SessionStorage (cleared on tab close)
sessionStorage.setItem("token", token);

// Option 3: LocalStorage (persists, but accessible to JS)
localStorage.setItem("token", token);

// Option 4: HTTP-only cookie (most secure, but requires CORS setup)
// Backend sets cookie, frontend can't access via JS
```

**Configuration:**

```php
// config/sanctum.php
'expiration' => 60 * 24, // 24 hours in minutes
'token_prefix' => '', // Optional prefix for tokens
```

### 9.2 API Key Security

**Best Practices:**

-   ✅ **Hash Storage:** Only store SHA-256 hash, never plain key
-   ✅ **Key Prefix:** Store first 12 chars for identification
-   ✅ **Expiration:** Set expiration dates for keys
-   ✅ **Rotation:** Regular key rotation policy
-   ✅ **Revocation:** Immediate revocation capability

**Key Generation:**

```php
// Secure random generation
$key = 'cs_live_' . Str::random(32);
$hash = hash('sha256', $key);
// Store hash, never store $key after generation
```

### 9.3 CORS Configuration

**For Frontend Integration:**

```php
// config/cors.php
'allowed_origins' => [
    env('FRONTEND_URL', 'http://localhost:3000'),
],
'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
'exposed_headers' => ['X-RateLimit-Limit', 'X-RateLimit-Remaining'],
'supports_credentials' => false, // Set to true if using cookies
```

### 9.4 Rate Limiting

**Implementation:**

-   **Login:** 5 attempts per minute per IP
-   **API Keys:** Configurable per key (default: 60/min, 1000/hour)
-   **Widget:** 100 requests/minute per IP
-   **General API:** 60 requests/minute per token

**Configuration:**

```php
// routes/api.php
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    // Rate limited routes
});

// Custom rate limiting for API keys
// Handled in ApiKeyMiddleware
```

### 9.5 Password Security

**Requirements:**

-   Minimum 8 characters (configurable)
-   Laravel's `Hash::make()` uses bcrypt
-   Password reset tokens expire after 1 hour
-   Rate limiting on login attempts

### 9.6 Token Refresh (Optional)

**Implementation:**

```php
// POST /api/auth/refresh
public function refresh(Request $request)
{
    $user = $request->user();

    // Revoke old token
    $request->user()->currentAccessToken()->delete();

    // Create new token
    $token = $user->createToken('api-access')->plainTextToken;

    return response()->json([
        'token' => $token,
        'token_type' => 'Bearer',
    ]);
}
```

**Frontend Implementation:**

```javascript
// Axios interceptor for token refresh
axios.interceptors.response.use(
    (response) => response,
    async (error) => {
        if (error.response?.status === 401) {
            // Try to refresh token
            const newToken = await refreshToken();
            if (newToken) {
                // Retry original request
                error.config.headers.Authorization = `Bearer ${newToken}`;
                return axios.request(error.config);
            }
        }
        return Promise.reject(error);
    }
);
```

---

## Summary

### Key Takeaways

1. **Token-Based Authentication:**

    - **Sanctum Tokens** for user authentication → Stateless, scalable
    - **API Keys** for company-level access → External integrations

2. **Role-Based Access:**

    - Super Admin → Global access
    - Company Users → Scoped to their company only

3. **Data Isolation:**

    - Automatic via `CompanyScopeMiddleware` and global scopes
    - Prevents accidental data leakage
    - Super admins can bypass when needed

4. **Security Layers:**
    - Token expiration and rotation
    - API key hashing and rotation
    - Rate limiting per endpoint
    - HTTPS enforcement
    - CORS configuration

### Architecture Benefits

-   ✅ **API-First:** Clean separation between frontend and backend
-   ✅ **Stateless:** Horizontal scaling without session storage
-   ✅ **Secure by Default:** Middleware enforces isolation
-   ✅ **Scalable:** Easy to add new roles or permissions
-   ✅ **Maintainable:** Clear separation of concerns
-   ✅ **Flexible:** Works with any frontend framework

This architecture ensures strict data isolation for customers while providing a secure, scalable API backend that can be consumed by any frontend application.

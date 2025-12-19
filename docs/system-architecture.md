# System Architecture & Business Logic

This document answers key architectural and business questions for the AI Customer Support system.

---

## 1. Customer Chat Interaction Methods

### Option A: Embeddable Widget (Recommended)

```javascript
// Company embeds this on their website
<script src="https://your-domain.com/widget.js"></script>
<script>
  CustomerSupport.init({
    companyId: 'acme-corp',
    apiKey: 'cs_live_abc123...',
    theme: 'light',
    position: 'bottom-right'
  });
</script>
```

**Implementation**:

-   Widget loads in iframe for security isolation
-   Communicates via `POST /api/chat` with company API key
-   Stores session in localStorage, creates anonymous user ID
-   Responsive design works on mobile/desktop

### Option B: Direct API Integration

```bash
# Company's backend calls your API
curl -X POST https://your-domain.com/api/chat \
  -H "Authorization: Bearer cs_live_abc123..." \
  -H "Content-Type: application/json" \
  -d '{
    "message": "What is your refund policy?",
    "session_id": "sess_xyz789",
    "user_id": "customer_456"
  }'
```

### Option C: Hosted Chat Page

-   Direct URL: `https://your-domain.com/chat/acme-corp`
-   Company redirects customers to this page
-   Branded with company colors/logo

**Database Flow**:

```
1. Customer message → chat_sessions (if new) → chat_messages
2. Retrieve relevant chunks → retrieved_chunks
3. Generate AI response → chat_messages (assistant role)
4. Update usage_metrics (tokens, queries)
```

---

## 2. Billing System Architecture

### Pricing Model

```json
{
    "plans": {
        "starter": {
            "monthly_fee": 29,
            "included_tokens": 50000,
            "max_documents": 50,
            "max_api_calls": 1000,
            "overage_per_1k_tokens": 0.002
        },
        "professional": {
            "monthly_fee": 99,
            "included_tokens": 200000,
            "max_documents": 500,
            "max_api_calls": 10000,
            "overage_per_1k_tokens": 0.0015
        },
        "enterprise": {
            "monthly_fee": 299,
            "included_tokens": 1000000,
            "max_documents": -1,
            "max_api_calls": -1,
            "overage_per_1k_tokens": 0.001
        }
    }
}
```

### Implementation Tables

```sql
-- Add to companies table
ALTER TABLE companies ADD COLUMN subscription_plan VARCHAR(50) DEFAULT 'starter';
ALTER TABLE companies ADD COLUMN billing_cycle ENUM('monthly', 'yearly') DEFAULT 'monthly';
ALTER TABLE companies ADD COLUMN next_billing_date DATE;
ALTER TABLE companies ADD COLUMN payment_status ENUM('active', 'past_due', 'cancelled') DEFAULT 'active';

-- New billing table
CREATE TABLE billing_invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    base_amount DECIMAL(10,2) NOT NULL,
    overage_amount DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('draft', 'sent', 'paid', 'overdue') DEFAULT 'draft',
    stripe_invoice_id VARCHAR(255),
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,

    FOREIGN KEY (company_id) REFERENCES companies(id)
);
```

### Billing Job (Daily Cron)

```php
// app/Console/Commands/ProcessBilling.php
class ProcessBilling extends Command
{
    public function handle()
    {
        $companies = Company::where('next_billing_date', '<=', now())->get();

        foreach ($companies as $company) {
            $usage = $this->calculateMonthlyUsage($company);
            $invoice = $this->generateInvoice($company, $usage);
            $this->sendToStripe($invoice);
        }
    }

    private function calculateMonthlyUsage($company)
    {
        return UsageMetric::where('company_id', $company->id)
            ->whereBetween('period_start', [
                $company->last_billing_date,
                $company->next_billing_date
            ])
            ->sum(['tokens_prompt', 'tokens_completion', 'api_calls']);
    }
}
```

---

## 3. Token Limiting System

### Rate Limiting Middleware

```php
// app/Http/Middleware/TokenLimitMiddleware.php
class TokenLimitMiddleware
{
    public function handle($request, Closure $next)
    {
        $company = $request->user()->company;
        $plan = config("billing.plans.{$company->subscription_plan}");

        // Check monthly token usage
        $monthlyUsage = $this->getMonthlyTokenUsage($company);

        if ($monthlyUsage >= $plan['included_tokens']) {
            // Check if they allow overages
            if (!$company->allow_overages) {
                return response()->json([
                    'error' => 'Monthly token limit exceeded',
                    'usage' => $monthlyUsage,
                    'limit' => $plan['included_tokens']
                ], 429);
            }
        }

        // Check rate limits (per minute/hour)
        $apiKey = $request->bearerToken();
        $rateLimiter = app(RateLimiter::class);

        if ($rateLimiter->tooManyAttempts("api:{$apiKey}", 60)) {
            return response()->json(['error' => 'Rate limit exceeded'], 429);
        }

        return $next($request);
    }
}
```

### Usage Tracking

```php
// app/Services/UsageTracker.php
class UsageTracker
{
    public function recordChatUsage($company, $tokensPrompt, $tokensCompletion)
    {
        // Real-time tracking
        Cache::increment("usage:{$company->id}:tokens_today", $tokensPrompt + $tokensCompletion);

        // Daily aggregation
        UsageMetric::updateOrCreate([
            'company_id' => $company->id,
            'period_start' => now()->startOfDay(),
            'metric_type' => 'daily'
        ], [
            'tokens_prompt' => DB::raw("tokens_prompt + {$tokensPrompt}"),
            'tokens_completion' => DB::raw("tokens_completion + {$tokensCompletion}"),
            'chat_messages' => DB::raw('chat_messages + 1')
        ]);
    }

    public function checkLimits($company): array
    {
        $plan = config("billing.plans.{$company->subscription_plan}");
        $usage = $this->getMonthlyUsage($company);

        return [
            'tokens_used' => $usage['tokens'],
            'tokens_limit' => $plan['included_tokens'],
            'tokens_remaining' => max(0, $plan['included_tokens'] - $usage['tokens']),
            'percentage_used' => ($usage['tokens'] / $plan['included_tokens']) * 100,
            'will_incur_overage' => $usage['tokens'] > $plan['included_tokens']
        ];
    }
}
```

---

## 4. Super Admin Role & Multi-Level Access

### User Role Hierarchy

```php
// config/roles.php
return [
    'super_admin' => [
        'level' => 100,
        'permissions' => ['*'], // All permissions
        'can_access_all_companies' => true,
        'can_manage_billing' => true,
        'can_view_system_metrics' => true
    ],
    'company_admin' => [
        'level' => 50,
        'permissions' => ['manage_users', 'manage_documents', 'view_analytics'],
        'company_scoped' => true
    ],
    'company_agent' => [
        'level' => 25,
        'permissions' => ['upload_documents', 'view_chats'],
        'company_scoped' => true
    ],
    'api_user' => [
        'level' => 10,
        'permissions' => ['chat_api', 'upload_api'],
        'company_scoped' => true
    ]
];
```

### Super Admin Features

```php
// Super admin can switch between companies
class SuperAdminController extends Controller
{
    public function switchCompany($companyId)
    {
        if (!auth()->user()->isSuperAdmin()) {
            abort(403);
        }

        session(['impersonating_company' => $companyId]);
        return redirect()->route('admin.dashboard');
    }

    public function systemMetrics()
    {
        return [
            'total_companies' => Company::count(),
            'active_companies' => Company::where('status', 'active')->count(),
            'total_documents' => Document::count(),
            'total_chat_sessions' => ChatSession::count(),
            'monthly_revenue' => $this->calculateMonthlyRevenue(),
            'top_companies_by_usage' => $this->getTopCompaniesByUsage()
        ];
    }
}
```

### Database Changes

```sql
-- Add super admin flag
ALTER TABLE users ADD COLUMN is_super_admin BOOLEAN DEFAULT FALSE;
ALTER TABLE users ADD COLUMN can_impersonate BOOLEAN DEFAULT FALSE;

-- Super admin audit log
CREATE TABLE admin_actions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_user_id BIGINT UNSIGNED NOT NULL,
    target_company_id BIGINT UNSIGNED,
    action VARCHAR(100) NOT NULL,
    details JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP NULL DEFAULT NULL,

    FOREIGN KEY (admin_user_id) REFERENCES users(id),
    FOREIGN KEY (target_company_id) REFERENCES companies(id)
);
```

---

## 5. Portal Management Strategy

### Multi-Portal Architecture

#### Company Portal (`/admin/*`)

-   **URL**: `https://your-domain.com/admin`
-   **Users**: Company admins, agents
-   **Features**: Document management, chat logs, analytics, settings
-   **Scoping**: All data filtered by `company_id`

#### Super Admin Portal (`/system/*`)

-   **URL**: `https://your-domain.com/system`
-   **Users**: Platform super admins only
-   **Features**: Company management, billing, system metrics, support
-   **Access**: Cross-company data visibility

### Route Structure

```php
// routes/web.php
Route::middleware(['auth'])->group(function () {
    // Company-scoped admin routes
    Route::prefix('admin')->middleware('company.scope')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);
        Route::resource('documents', DocumentController::class);
        Route::resource('chat-sessions', ChatSessionController::class);
        Route::get('/analytics', [AnalyticsController::class, 'index']);
    });

    // Super admin routes
    Route::prefix('system')->middleware('super.admin')->group(function () {
        Route::get('/dashboard', [SystemController::class, 'dashboard']);
        Route::resource('companies', CompanyController::class);
        Route::get('/billing', [BillingController::class, 'index']);
        Route::get('/metrics', [SystemController::class, 'metrics']);
    });
});
```

### Middleware for Scoping

```php
// app/Http/Middleware/CompanyScope.php
class CompanyScope
{
    public function handle($request, Closure $next)
    {
        $user = $request->user();

        if ($user->is_super_admin && session('impersonating_company')) {
            $companyId = session('impersonating_company');
        } else {
            $companyId = $user->company_id;
        }

        // Set global scope for all queries
        app()->instance('current_company_id', $companyId);

        return $next($request);
    }
}
```

---

## 6. Frontend Architecture

> **Note:** The frontend has been moved to a separate repository. This backend serves as a pure API.

### Frontend Repository

The frontend should be built as a separate application that consumes this API backend. Recommended approaches:

**Option A: Vue 3 + Vite + Pinia + Vue Router**

```bash
# Separate frontend project
npm create vue@latest customer-support-admin
cd customer-support-admin
npm install axios pinia @vueuse/core chart.js
```

**Option B: React + Vite + Zustand**

```bash
npm create vite@latest customer-support-admin -- --template react-ts
cd customer-support-admin
npm install axios zustand react-query
```

**Option C: Next.js (React)**

```bash
npx create-next-app@latest customer-support-admin
cd customer-support-admin
npm install axios
```

### API Integration

The frontend should consume the REST API endpoints documented in:

-   `docs/api-reference.md`
-   `docs/api-endpoints.md`
-   `docs/authentication-flow.md`

### Authentication Flow

1. Frontend calls `POST /api/auth/login` with credentials
2. Backend returns authentication token
3. Frontend stores token and includes in `Authorization: Bearer {token}` header
4. Backend validates token on each request

### Widget Implementation

The widget (`resources/views/widget/chat.blade.php`) remains server-side rendered and can be embedded directly on customer websites.

This architecture provides complete separation of concerns, allowing the frontend and backend to scale independently.

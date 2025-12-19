# SOLID Architecture Implementation

This document outlines how the AI Customer Support system implements SOLID principles in its architecture.

---

## Architecture Overview

The system follows a layered architecture with clear separation of concerns:

```
Controllers → Services → Repositories → Models → Database
     ↓           ↓           ↓
  Requests   Interfaces   Contracts
     ↓
 Resources
```

---

## SOLID Principles Implementation

### 1. Single Responsibility Principle (SRP)

Each class has a single, well-defined responsibility:

#### Controllers
- **DocumentController**: Handle HTTP requests for document operations
- **ChatController**: Handle HTTP requests for chat operations  
- **WidgetApiController**: Handle widget-specific API requests

#### Services
- **DocumentService**: Business logic for document management
- **ChatService**: Business logic for chat and AI responses
- **UsageTrackingService**: Track and calculate usage metrics

#### Repositories
- **DocumentRepository**: Data access for documents
- **ChatRepository**: Data access for chat sessions and messages

#### Middleware
- **CompanyScopeMiddleware**: Handle multi-tenant data scoping
- **ApiKeyMiddleware**: Handle API key authentication and rate limiting
- **SuperAdminMiddleware**: Handle super admin authorization

### 2. Open/Closed Principle (OCP)

The system is open for extension but closed for modification:

#### Interface-Based Design
```php
interface DocumentServiceInterface
{
    public function uploadDocument(Company $company, UploadedFile $file, array $metadata): Document;
    // ... other methods
}
```

#### Extensible Services
- New document processors can be added without modifying existing code
- New embedding providers can be implemented via `EmbeddingServiceInterface`
- New vector stores can be added via `VectorStoreInterface`

#### Example Extension
```php
// Add new embedding provider without changing existing code
class HuggingFaceEmbeddingService implements EmbeddingServiceInterface
{
    public function generateEmbedding(string $text): array
    {
        // Implementation for Hugging Face
    }
}
```

### 3. Liskov Substitution Principle (LSP)

All implementations can be substituted for their interfaces:

#### Service Implementations
```php
// Any implementation of DocumentServiceInterface can be used
class DocumentService implements DocumentServiceInterface { }
class AdvancedDocumentService implements DocumentServiceInterface { }

// Both work identically in controllers
public function __construct(DocumentServiceInterface $documentService) { }
```

#### Repository Pattern
```php
// Any repository implementation works the same way
class DocumentRepository implements DocumentRepositoryInterface { }
class CachedDocumentRepository implements DocumentRepositoryInterface { }
```

### 4. Interface Segregation Principle (ISP)

Interfaces are focused and clients only depend on what they need:

#### Focused Interfaces
```php
// Separate interfaces for different concerns
interface DocumentServiceInterface { } // Document operations
interface ChatServiceInterface { }     // Chat operations  
interface AnalyticsServiceInterface { } // Analytics operations
interface VectorStoreInterface { }     // Vector operations only
interface EmbeddingServiceInterface { } // Embedding operations only
```

#### No Fat Interfaces
Each interface contains only methods relevant to its specific domain.

### 5. Dependency Inversion Principle (DIP)

High-level modules don't depend on low-level modules. Both depend on abstractions:

#### Controller Dependencies
```php
class DocumentController extends Controller
{
    public function __construct(
        private DocumentServiceInterface $documentService // Depends on interface
    ) {}
}
```

#### Service Dependencies
```php
class ChatService implements ChatServiceInterface
{
    public function __construct(
        private ChatRepository $chatRepository,
        private VectorStoreInterface $vectorStore,      // Interface dependency
        private EmbeddingServiceInterface $embeddingService, // Interface dependency
        private LLMService $llmService,
        private UsageTrackingService $usageTracker
    ) {}
}
```

#### Dependency Injection Container
```php
// ServiceLayerServiceProvider.php
$this->app->bind(DocumentServiceInterface::class, DocumentService::class);
$this->app->bind(VectorStoreInterface::class, QdrantService::class);
$this->app->bind(EmbeddingServiceInterface::class, OpenAIEmbeddingService::class);
```

---

## Additional Design Patterns

### Repository Pattern
Abstracts data access logic:

```php
interface DocumentRepositoryInterface
{
    public function findByCompany(Company $company, array $filters = []): LengthAwarePaginator;
    public function create(array $data): Document;
    // ...
}
```

### Service Layer Pattern
Encapsulates business logic:

```php
class DocumentService implements DocumentServiceInterface
{
    public function uploadDocument(Company $company, UploadedFile $file, array $metadata): Document
    {
        $this->validateFile($file);
        $storagePath = $this->storeFile($company, $file);
        $document = $this->documentRepository->create([...]);
        $this->queueProcessingJob($document);
        return $document;
    }
}
```

### Request/Response Pattern
Separates validation and data transformation:

```php
// Request validation
class StoreDocumentRequest extends FormRequest
{
    public function rules(): array { }
}

// Response transformation  
class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array { }
}
```

---

## Benefits Achieved

### 1. Testability
- Easy to mock interfaces for unit testing
- Services can be tested independently
- Clear boundaries between layers

### 2. Maintainability
- Changes in one layer don't affect others
- Easy to locate and fix bugs
- Clear code organization

### 3. Extensibility
- New features can be added without modifying existing code
- Easy to swap implementations (e.g., different vector stores)
- Plugin-like architecture for new services

### 4. Scalability
- Services can be extracted to microservices easily
- Clear API boundaries
- Stateless design

### 5. Code Reusability
- Services can be reused across different controllers
- Repositories can be shared between services
- Interfaces enable polymorphic usage

---

## File Structure

```
app/
├── Contracts/                    # Interfaces (DIP)
│   ├── DocumentServiceInterface.php
│   ├── ChatServiceInterface.php
│   ├── VectorStoreInterface.php
│   └── EmbeddingServiceInterface.php
├── Services/                     # Business Logic (SRP)
│   ├── DocumentService.php
│   ├── ChatService.php
│   └── UsageTrackingService.php
├── Repositories/                 # Data Access (SRP)
│   ├── DocumentRepository.php
│   └── ChatRepository.php
├── Http/
│   ├── Controllers/Api/          # HTTP Layer (SRP)
│   │   ├── DocumentController.php
│   │   ├── ChatController.php
│   │   └── WidgetApiController.php
│   ├── Requests/                 # Validation (ISP)
│   │   ├── StoreDocumentRequest.php
│   │   └── WidgetChatRequest.php
│   ├── Resources/                # Response Transformation (SRP)
│   │   ├── DocumentResource.php
│   │   └── ChatMessageResource.php
│   └── Middleware/               # Cross-cutting Concerns (SRP)
│       ├── CompanyScopeMiddleware.php
│       ├── ApiKeyMiddleware.php
│       └── SuperAdminMiddleware.php
└── Providers/
    └── ServiceLayerServiceProvider.php # DI Container (DIP)
```

---

## Testing Strategy

### Unit Tests
```php
class DocumentServiceTest extends TestCase
{
    public function test_upload_document()
    {
        $mockRepository = Mockery::mock(DocumentRepositoryInterface::class);
        $service = new DocumentService($mockRepository);
        
        // Test business logic in isolation
    }
}
```

### Integration Tests
```php
class DocumentControllerTest extends TestCase
{
    public function test_store_document_endpoint()
    {
        // Test full HTTP request flow
        $response = $this->postJson('/api/documents', [...]);
        $response->assertStatus(201);
    }
}
```

This architecture ensures the codebase is maintainable, testable, and extensible while following industry best practices and SOLID principles.

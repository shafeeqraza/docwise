# DocWise - AI Customer Support Platform

<p align="center">
  <strong>Multi-tenant SaaS platform for AI-powered customer support with RAG (Retrieval-Augmented Generation)</strong>
</p>

DocWise is a comprehensive Laravel-based platform that enables companies to provide intelligent customer support through AI-powered chat. The system uses document ingestion, vector embeddings, and retrieval-augmented generation to deliver accurate, context-aware responses to customer queries.

---

## 🚀 Features

### Core Capabilities

-   **Document Management**: Upload, process, and manage documents with automatic chunking and embedding generation
-   **AI Chat Support**: Intelligent chat interface powered by RAG (Retrieval-Augmented Generation) with citation support
-   **Multi-Tenant Architecture**: Complete data isolation per company with role-based access control
-   **Widget Integration**: Embeddable chat widget for customer websites
-   **API-First Design**: RESTful API for all operations, suitable for any frontend framework

### Advanced Features

-   **Super Admin Portal**: Platform-wide management and analytics
-   **Usage Tracking**: Comprehensive metrics for tokens, API calls, and chat sessions
-   **Billing System**: Subscription plans with usage-based billing and invoice generation
-   **Analytics Dashboard**: Real-time insights into documents, chats, and user engagement
-   **Feedback System**: Collect and analyze customer feedback on AI responses
-   **Rate Limiting**: Configurable rate limits per API key and subscription plan
-   **Audit Logging**: Complete audit trail for admin actions and system events

---

## 🛠 Tech Stack

-   **Framework**: Laravel 12.x
-   **PHP**: ^8.4
-   **Database**: MySQL
-   **Authentication**: Laravel Sanctum (Token-based)
-   **Vector Store**: Qdrant (for embeddings and similarity search)
-   **Frontend**: Separate repository (React/Vue/Next.js recommended)
-   **Queue System**: Laravel Queues for async document processing
-   **Caching**: Redis (recommended)

---

## 📋 Requirements

-   PHP >= 8.4 (or Docker, see below)
-   Composer
-   MySQL >= 8.0
-   Node.js >= 18.x & NPM
-   Redis (optional, for caching and queues)
-   Qdrant (for vector storage)

---

## 🐳 Run with Docker

`docker compose up -d` starts everything: the PHP 8.4 app runtime (`app` php-fpm, `nginx`, `queue` worker) and its own three data stores. These are separate from any standalone DB containers you already run, and compose never touches those.

| Container          | Role                  | Host port     | Volume                |
| ------------------ | --------------------- | ------------- | --------------------- |
| `docwise-postgres` | PostgreSQL + pgvector | `5433`        | `docwise-pgdata`      |
| `docwise-mysql`    | MySQL                 | `3308`        | `docwise-mysql-data`  |
| `docwise-qdrant`   | Qdrant vector store   | `6335`/`6336` | `docwise-qdrant-data` |

On first start the databases are created from `DB_USERNAME` / `DB_PASSWORD` / `DB_DATABASE` in `.env`. MySQL's root password is `MYSQL_ROOT_PASSWORD`, which defaults to `DB_PASSWORD`. Data lives in named volumes, so it survives `docker compose down` and container rebuilds.

> ⚠️ `docker compose down -v` **deletes the database volumes**. Don't use `-v` unless you want a clean slate.

Pick one pairing. **pgsql + pgvector** (`DB_CONNECTION=pgsql`, `VECTOR_STORE_DRIVER=pgsql`) is the default. The alternative is **mysql + qdrant** (`DB_CONNECTION=mysql`, `VECTOR_STORE_DRIVER=qdrant`, plus `DOCKER_DB_HOST=docwise-mysql` and `DOCKER_DB_PORT=3306`). pgvector stores embeddings in the main `document_chunks` table, so it only works with Postgres as the primary DB.

```bash
# 1. First time only: create .env, then fill in keys and DB passwords
cp .env.example .env

# 2. Build and start everything (the first boot runs composer install)
docker compose up -d --build
docker compose exec app php artisan key:generate    # first time only
docker compose exec app php artisan migrate

# 3. Use it
#    API:    http://localhost:8000              (APP_PORT)
#    Widget: http://localhost:8000/widget-demo.html
docker compose exec app php artisan test
docker compose logs -f queue
```

Notes:

-   Inside the containers, `DB_HOST`/`DB_PORT` point at the compose DB service (`DOCKER_DB_HOST`/`DOCKER_DB_PORT`), `QDRANT_HOST` at `docwise-qdrant`, and `PDF_TO_TEXT_PATH` at `/usr/bin/pdftotext`. The `DB_HOST`/`DB_PORT` values in `.env` stay free for a non-Docker setup.
-   `vendor/` lives in a Docker volume, not the bind-mounted source tree. That keeps requests fast on Windows/macOS. The host `vendor/` is only for IDE autocompletion. After changing dependencies, run `docker compose exec app composer install`.

---

## 🔧 Installation

### 1. Clone the Repository

```bash
git clone https://github.com/your-org/docwise.git
cd docwise
```

### 2. Install Dependencies

```bash
composer install
npm install
```

### 3. Environment Configuration

Copy the `.env.example` file to `.env`:

```bash
cp .env.example .env
```

Generate application key:

```bash
php artisan key:generate
```

Configure your `.env` file with database credentials, Qdrant connection, and other required settings:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=docwise
DB_USERNAME=your_username
DB_PASSWORD=your_password

QDRANT_HOST=localhost
QDRANT_PORT=6333
QDRANT_API_KEY=your_qdrant_api_key

# Add other required configuration...
```

### 4. Run Migrations and Seeders

```bash
php artisan migrate
php artisan db:seed --class=SuperAdminSeeder
```

### 5. Build Frontend Assets

```bash
npm run build
```

### 6. Start Development Server

Using Laravel's built-in server:

```bash
php artisan serve
```

Or use the provided dev script (includes queue worker and logs):

```bash
composer run dev
```

---

## 📚 Documentation

Comprehensive documentation is available in the `docs/` directory:

-   **[API Endpoints](docs/api-endpoints.md)** - Complete API reference with request/response examples
-   **[API Reference](docs/api-reference.md)** - Detailed API documentation
-   **[Authentication Flow](docs/authentication-flow.md)** - Authentication and authorization guide
-   **[System Architecture](docs/system-architecture.md)** - Architecture overview and design decisions
-   **[MySQL Schema](docs/mysql-schema.md)** - Database schema documentation
-   **[SOLID Architecture](docs/solid-architecture.md)** - Code organization and patterns
-   **[Widget Implementation](docs/widget-implementation.md)** - Chat widget integration guide
-   **[Build Plan](docs/build-plan.md)** - Development roadmap and implementation guide

---

## 🏗 Project Structure

```
docwise/
├── app/
│   ├── Contracts/V1/          # Service interfaces
│   ├── Exceptions/             # Custom exceptions
│   ├── Http/
│   │   ├── Controllers/V1/     # API controllers
│   │   ├── Middleware/         # Custom middleware
│   │   ├── Requests/           # Form requests
│   │   └── Resources/          # API resources
│   ├── Models/                 # Eloquent models
│   ├── Providers/              # Service providers
│   ├── Repositories/V1/        # Repository pattern implementations
│   └── Services/V1/            # Business logic services
├── database/
│   ├── migrations/             # Database migrations
│   └── seeders/                # Database seeders
├── docs/                       # Documentation
├── routes/
│   ├── api.php                 # API routes
│   └── web.php                 # Web routes
└── tests/                      # Test suites
```

---

## 🔐 Authentication

The platform uses **Laravel Sanctum** for token-based authentication. There are three main authentication methods:

1. **Super Admin Authentication**: Token-based auth for platform administrators

    - Endpoint: `POST /api/superadmin/auth/login`
    - Rate limit: 5 attempts per minute per IP

2. **Company User Authentication**: Token-based auth for company admins and agents

    - Endpoint: `POST /api/auth/login` (to be implemented)

3. **API Key Authentication**: Company API keys for widget and external integrations
    - Header: `Authorization: Bearer {api_key}`

See [Authentication Flow Documentation](docs/authentication-flow.md) for detailed information.

---

## 🧪 Testing

Run the test suite:

```bash
composer run test
```

Or use PHPUnit directly:

```bash
php artisan test
```

---

## 📝 Code Style

This project uses Laravel Pint for code style enforcement:

```bash
./vendor/bin/pint
```

---

## 🚀 Deployment

### Production Checklist

1. Set `APP_ENV=production` in `.env`
2. Run `php artisan config:cache`
3. Run `php artisan route:cache`
4. Run `php artisan view:cache`
5. Ensure queue workers are running: `php artisan queue:work`
6. Set up cron job for scheduled tasks:
    ```bash
    * * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
    ```

### Docker Deployment

Docker configuration files are available in the `infrastructure/docker/` directory (if present).

---

## 🔄 API Versioning

The API uses version prefixes (`/api/v1/`) for future compatibility. Current version is **v1**.

---

## 👥 User Roles

-   **Super Admin**: Platform-wide access, can manage all companies
-   **Company Admin**: Full access within their company
-   **Company Agent**: Limited access for customer support operations
-   **API User**: Programmatic access via API keys

---

## 📊 Key Endpoints

### Super Admin

-   `POST /api/superadmin/auth/login` - Authenticate super admin
-   `POST /api/superadmin/auth/logout` - Logout super admin
-   `GET /api/superadmin/auth/me` - Get current super admin

### Company Management

-   `GET /api/companies` - List companies (Super Admin)
-   `POST /api/companies` - Create company (Super Admin)
-   `GET /api/companies/{id}` - Get company details
-   `PUT /api/companies/{id}` - Update company

### Documents

-   `GET /api/documents` - List documents
-   `POST /api/documents` - Upload document
-   `GET /api/documents/{id}` - Get document details

### Chat

-   `GET /api/chat-sessions` - List chat sessions
-   `POST /api/chat-sessions` - Create chat session
-   `POST /api/chat-sessions/{id}/messages` - Send message

See [API Endpoints Documentation](docs/api-endpoints.md) for complete reference.

---

## 🤝 Contributing

Contributions are welcome! Please follow these guidelines:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Code Standards

-   Follow PSR-12 coding standards
-   Write tests for new features
-   Update documentation as needed
-   Ensure all tests pass before submitting PR

---

## 📄 License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

---

## 🆘 Support

For support, please open an issue in the GitHub repository or contact the development team.

---

## 🙏 Acknowledgments

-   Built with [Laravel](https://laravel.com)
-   Vector storage powered by [Qdrant](https://qdrant.tech)

---

<p align="center">Made with ❤️ for intelligent customer support</p>

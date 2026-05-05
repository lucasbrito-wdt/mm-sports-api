# mm-sports-api

Advanced Laravel E-commerce API for sports merchandise, built with a Domain-Driven Design (DDD) approach.

## Project Overview

This project is a high-performance backend for a sports store, featuring complex catalog management, commerce integrations (Asaas, Correios), and multi-tenant support. It leverages modern Laravel 12 features and a modular domain architecture.

### Tech Stack
- **Framework:** Laravel 12 (PHP 8.4)
- **Architecture:** Domain-Driven Design (DDD)
- **Authentication:** JWT (Tymon JWT-Auth)
- **Database:** PostgreSQL (with Full-Text Search support)
- **Testing:** Pest 3
- **Monitoring:** Laravel Telescope with MCP support
- **Storage:** AWS S3 (via Flysystem)

## Architecture & Conventions

### Domain-Driven Design (DDD)
The application logic is organized into `app/Domains`. Each domain typically contains:
- `Models/`: Eloquent models (often extending `BaseModel`)
- `Controllers/`: Domain-specific controllers (often extending `BaseController`)
- `Services/`: Business logic and data orchestration
- `Enums/`: PHP Enums for status and types
- `Traits/`: Reusable domain logic

Key Domains:
- `Auth`: User management and JWT authentication
- `Catalog`: Products, Categories, Attributes, and Variants
- `Commerce`: Orders, Payments (Asaas), and Shipping (Correios)
- `Sports`: Sports-specific data (Teams, Competitions)
- `Shared`: Base classes (`BaseModel`, `BaseController`) and common traits (`TenantScope`, `HasUlids`)

### Routing
Routes are modularized in `routes/domains/` and required in `routes/api.php`.

### Coding Standards
- **Models:** Use ULIDs (`HasUlids` trait) and Tenant Scoping (`TenantScope` trait). Define casts in the `casts()` method.
- **Controllers:** Prefer `BaseController` which provides standard CRUD actions and ACL integration.
- **Services:** Heavy lifting and third-party integrations (Asaas, Correios) reside here.
- **Form Requests:** Resolved automatically in `BaseController` via `ResolvesFormRequest` trait.

## Building and Running

### Prerequisites
- PHP 8.4+
- Composer
- Node.js & NPM/PNPM

### Setup
```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
# Configure your DB in .env
php artisan migrate
```

### Key Commands
- **Dev Server:** `composer run dev` (starts server, queue, and vite)
- **Run Tests:** `php artisan test --compact`
- **Lint Code:** `vendor/bin/pint --dirty --format agent`
- **Telescope MCP:** Use `lucianotonet/laravel-telescope-mcp` tools for debugging.
- **CRUD Generator:** `php artisan make:crud` (custom tool for domain-based CRUD generation)

## Development Guidelines

- **Always run tests** before pushing. This project uses Pest for testing.
- **Use Factories and Seeders** for testing data.
- **Domain First:** When adding features, identify the appropriate domain in `app/Domains` or create a new one.
- **Tenant Awareness:** Most models should use the `TenantScope` to ensure data isolation.
- **JWT Auth:** Use `jwtHeaders($user)` helper in tests for authenticated requests.

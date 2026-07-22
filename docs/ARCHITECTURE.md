# Architecture

## Overview

Rent2Proof is built as a **modular monolith** using Laravel 13. The application is organized into 12 bounded contexts (modules), each responsible for a specific domain area.

## Tech Stack

- **Backend:** Laravel 13.x, PHP 8.4
- **Frontend:** Inertia.js v3, Vue 3 (Composition API), Vite, Tailwind CSS v4
- **Admin Panel:** Filament 5.x
- **Database:** PostgreSQL 17
- **Cache/Queue:** Redis 7
- **Storage:** MinIO (S3-compatible)
- **Internationalization:** vue-i18n, spatie/laravel-translatable

## Bounded Contexts (ADR-002)

The application is divided into 12 modules:

| Module | Description |
|--------|-------------|
| **Identity** | User authentication and registration |
| **Property** | Property/real estate management |
| **Protocol** | Inspection protocols (check-in/check-out) |
| **Participation** | Counterparty participation via magic-links |
| **Acceptance** | Protocol acceptance and signatures |
| **Billing** | Payments and entitlements |
| **Notification** | Email/SMS notifications |
| **Document** | PDF generation |
| **Evidence** | Photo/file storage with SHA-256 hashing |
| **Lifecycle** | Data retention and archival policies |
| **Catalog** | Room/item templates with translations |
| **Localization** | Multi-language support and locales |

## Module Structure

Each module follows this structure:

```
app/Modules/{ModuleName}/
├── Domain/                 # Domain logic, entities, value objects
├── Application/
│   ├── Actions/           # Business operations (single responsibility)
│   └── Data/              # DTOs using spatie/laravel-data
├── Infrastructure/
│   ├── Models/            # Eloquent models
│   └── Migrations/        # Database migrations
├── Http/
│   ├── Controllers/       # HTTP controllers
│   └── Requests/          # Form requests
└── Providers/
    └── {ModuleName}ServiceProvider.php
```

## Data Flow (ADR-003)

```
HTTP Request
    ↓
Data::from(request)  →  Typed DTO (spatie/laravel-data)
    ↓
Action Class         →  Business logic
    ↓
Data Object          →  Output DTO
    ↓
Inertia Props        →  Vue component (PHP enums passed directly)
```

## Key Conventions

### 1. Actions as Business Operations
- Each action class represents a single business operation
- Actions receive typed DTOs and return typed DTOs
- Example: `FinalizeCheckInAction`, `IssueCheckOutAction`

### 2. Cross-Module Communication
- Modules communicate ONLY through public services or events
- Direct access to another module's Eloquent models is **prohibited**
- Use service classes or Laravel events for inter-module communication

### 3. DTOs with spatie/laravel-data
- All data transfer uses typed Data classes
- PHP enums are passed directly to Inertia props
- Validation rules defined in Data classes

### 4. Translations with spatie/laravel-translatable
- Catalog items use translatable `name` JSON field
- Snapshots capture translations at protocol creation time
- UI strings stored in `lang/` files (not database)

## Module Discovery

Modules are auto-discovered and registered via `ModuleServiceProvider`:

```php
// app/Providers/ModuleServiceProvider.php
protected array $moduleProviders = [
    \App\Modules\Identity\Providers\IdentityServiceProvider::class,
    \App\Modules\Property\Providers\PropertyServiceProvider::class,
    // ... all 12 modules
];
```

Each module's service provider:
- Loads migrations from `Infrastructure/Migrations`
- Registers module-specific routes (when needed)
- Binds services to the container

## Docker Architecture (ADR-004)

The application runs in Docker with a "thick container" pattern:
- Single `app` container with PHP-FPM + Nginx + Supervisord
- PostgreSQL for database
- Redis for cache/queue/sessions
- MinIO for S3-compatible file storage
- Mailpit for development email

## Security Considerations

- No PESEL or sensitive personal data stored globally
- User roles are per-protocol, not global account types
- Session-based authentication for registered users
- Magic-link tokens for counterparty access (72h expiry)
- SHA-256 hashing for evidence integrity
- RODO/GDPR compliance built into design

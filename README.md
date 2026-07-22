# Rent2Proof / Etalon

System for documenting rental property condition during check-in and check-out.

## Tech Stack

- **Backend:** Laravel 13.8.x, PHP 8.4
- **Frontend:** Inertia.js v3, Vue 3, Vite, Tailwind CSS v4
- **Admin:** Filament 5.6.x
- **Database:** PostgreSQL 17
- **Cache/Queue:** Redis 7
- **Storage:** MinIO (S3-compatible)
- **Mail:** Mailpit (development)

## Requirements

- Docker & Docker Compose
- Make

## Quick Start

```bash
# Copy environment file
cp .env.example .env

# Start all containers
make up

# Wait for containers to be healthy, then access:
# App: http://127.0.0.1:7777
# MinIO Console: http://127.0.0.1:9001
# Mailpit UI: http://127.0.0.1:8026
```

## Available Commands

```bash
make help           # Show all available commands

# Docker
make up             # Start all containers
make down           # Stop all containers
make rebuild        # Rebuild and restart containers
make status         # Show container status
make logs           # Show container logs

# Shell
make shell          # Enter app container as www-data
make shell-root     # Enter app container as root

# Database
make migrate        # Run migrations
make migrate-fresh  # Fresh migration
make seed           # Run seeders
make fresh          # Fresh migration + seed

# Development
make test           # Run tests
make pint           # Run code formatter
make phpstan        # Run static analysis
make lint           # Run all linters

# Artisan
make artisan CMD="route:list"   # Run any artisan command
make tinker                      # Start Tinker REPL

# Assets
make npm CMD="install"    # Run npm commands
make npm-build            # Build production assets
```

## Project Structure

```
app/
  Modules/           # Modular monolith (12 bounded contexts)
    Identity/        # User authentication
    Property/        # Property management
    Protocol/        # Inspection protocols (check-in/check-out)
    Participation/   # Counterparty participation
    Acceptance/      # Protocol acceptance/signatures
    Billing/         # Payments and entitlements
    Notification/    # Email/SMS notifications
    Document/        # PDF generation
    Evidence/        # Photo/file storage with hashing
    Lifecycle/       # Data retention policies
    Catalog/         # Room/item templates
    Localization/    # Multi-language support
docker/
  php/               # Dockerfile and PHP config
  nginx/             # Nginx configs (local/production)
  entrypoint.sh      # Container initialization
  supervisord.conf   # Process management
```

## Environment Variables

Key variables in `.env`:

| Variable | Description | Default |
|----------|-------------|---------|
| `DB_HOST` | PostgreSQL host | `db` |
| `REDIS_HOST` | Redis host | `redis` |
| `AWS_ENDPOINT` | MinIO endpoint | `http://minio:9000` |
| `MAIL_HOST` | Mail server | `mailpit` |
| `HORIZON_ENABLED` | Enable Horizon | `false` |

## Services

| Service | Port | Description |
|---------|------|-------------|
| App | 7777 | Laravel application |
| PostgreSQL | 5432 | Database |
| Redis | 6381 | Cache/Queue |
| MinIO API | 9000 | S3-compatible storage |
| MinIO Console | 9001 | MinIO web UI |
| Mailpit SMTP | 1026 | Mail server |
| Mailpit UI | 8026 | Mail web UI |

## License

Proprietary - All rights reserved.

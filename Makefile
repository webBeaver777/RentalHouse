# Rent2Proof Docker Makefile
# All development commands run through Docker containers

.PHONY: help up down build rebuild fresh shell shell-root status logs \
        migrate migrate-fresh seed test pint phpstan artisan \
        npm composer queue-restart tinker restore-db admin admin-dev

# Default target
.DEFAULT_GOAL := help

# Colors for terminal output
BLUE := \033[34m
GREEN := \033[32m
YELLOW := \033[33m
RED := \033[31m
NC := \033[0m # No Color

help: ## Show this help message
	@echo "$(BLUE)Rent2Proof Docker Commands$(NC)"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "$(GREEN)%-20s$(NC) %s\n", $$1, $$2}'

# === Docker Commands ===

up: ## Start all containers
	docker compose up -d
	@echo "$(GREEN)Containers started. App available at http://127.0.0.1:7777$(NC)"
	@echo "$(GREEN)MinIO Console: http://127.0.0.1:9001$(NC)"
	@echo "$(GREEN)Mailpit UI: http://127.0.0.1:8026$(NC)"

down: ## Stop all containers
	docker compose down

stop: ## Stop all containers (alias for down)
	docker compose down

build: ## Build containers without cache
	docker compose build --no-cache

rebuild: down build up ## Rebuild and restart all containers

restart: ## Restart all containers
	docker compose restart

status: ## Show status of all containers
	docker compose ps -a

logs: ## Show logs from all containers
	docker compose logs -f

logs-app: ## Show logs from app container only
	docker compose logs -f app

# === Shell Access ===

shell: ## Enter app container as www-data
	docker compose exec -u www-data app sh

shell-root: ## Enter app container as root
	docker compose exec app sh

# === Database Commands ===

migrate: ## Run database migrations
	docker compose exec app php artisan migrate

migrate-fresh: ## Drop all tables and re-run migrations
	docker compose exec app php artisan migrate:fresh

migrate-rollback: ## Rollback the last database migration
	docker compose exec app php artisan migrate:rollback

seed: ## Run database seeders
	docker compose exec app php artisan db:seed

fresh: migrate-fresh seed ## Fresh migration with seeders
	@echo "$(GREEN)Database refreshed with seeders$(NC)"

restore-db: ## Restore database from backup (usage: make restore-db FILE=backup.sql)
	@if [ -z "$(FILE)" ]; then \
		echo "$(RED)Error: FILE parameter required. Usage: make restore-db FILE=backup.sql$(NC)"; \
		exit 1; \
	fi
	docker compose exec -T db psql -U $${DB_USERNAME:-rent2proof} -d $${DB_DATABASE:-rent2proof} < $(FILE)

# === Testing & Quality ===

test: ## Run PHPUnit tests
	docker compose exec app php artisan test

test-coverage: ## Run tests with coverage report
	docker compose exec app php artisan test --coverage

pint: ## Run Laravel Pint (code style fixer)
	docker compose exec app ./vendor/bin/pint

pint-test: ## Run Laravel Pint in test mode (no changes)
	docker compose exec app ./vendor/bin/pint --test

phpstan: ## Run PHPStan static analysis
	docker compose exec app ./vendor/bin/phpstan analyse

lint: pint-test phpstan ## Run all linters

# === Artisan Commands ===

artisan: ## Run artisan command (usage: make artisan CMD="route:list")
	@if [ -z "$(CMD)" ]; then \
		echo "$(RED)Error: CMD parameter required. Usage: make artisan CMD=\"route:list\"$(NC)"; \
		exit 1; \
	fi
	docker compose exec app php artisan $(CMD)

tinker: ## Start Laravel Tinker
	docker compose exec app php artisan tinker

route-list: ## Show all registered routes
	docker compose exec app php artisan route:list

cache-clear: ## Clear all caches
	docker compose exec app php artisan cache:clear
	docker compose exec app php artisan config:clear
	docker compose exec app php artisan route:clear
	docker compose exec app php artisan view:clear

optimize: ## Optimize application for production
	docker compose exec app php artisan optimize
	docker compose exec app php artisan view:cache

# === Queue Commands ===

queue-restart: ## Restart queue workers
	docker compose exec app php artisan queue:restart

queue-work: ## Run queue worker (foreground)
	docker compose exec app php artisan queue:work

queue-failed: ## List failed jobs
	docker compose exec app php artisan queue:failed

queue-retry: ## Retry all failed jobs
	docker compose exec app php artisan queue:retry all

# === Composer & NPM ===

composer: ## Run composer command (usage: make composer CMD="require package/name")
	@if [ -z "$(CMD)" ]; then \
		docker compose exec app composer install; \
	else \
		docker compose exec app composer $(CMD); \
	fi

npm: ## Run npm command (usage: make npm CMD="install")
	@if [ -z "$(CMD)" ]; then \
		docker compose exec app npm install; \
	else \
		docker compose exec app npm $(CMD); \
	fi

npm-build: ## Build frontend assets
	docker compose exec app npm run build

npm-dev: ## Start Vite dev server (runs in foreground)
	docker compose exec app npm run dev

# === Admin Commands ===

admin: ## Create admin user interactively
	docker compose exec app php artisan app:create-admin

admin-dev: ## Create default dev admin (admin@rent2proof.local / admin123)
	docker compose exec app php artisan db:seed --class=AdminSeeder

# === Utility Commands ===

key-generate: ## Generate application key
	docker compose exec app php artisan key:generate

storage-link: ## Create storage symlink
	docker compose exec app php artisan storage:link

ide-helper: ## Generate IDE helper files
	docker compose exec app php artisan ide-helper:generate
	docker compose exec app php artisan ide-helper:meta
	docker compose exec app php artisan ide-helper:models --nowrite

# === Deployment Commands ===

deploy: ## Deploy application (production)
	docker compose exec app composer install --no-dev --optimize-autoloader
	docker compose exec app php artisan migrate --force
	docker compose exec app php artisan optimize
	docker compose exec app php artisan view:cache
	docker compose exec app npm run build

reload: ## Reload application after code changes
	docker compose exec app composer dump-autoload
	docker compose exec app php artisan optimize:clear
	docker compose exec app php artisan queue:restart

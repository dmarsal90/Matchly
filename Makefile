# Common development commands. Run `make` to list them.
.DEFAULT_GOAL := help
SHELL := /bin/sh

COMPOSE := docker compose
EXEC    := $(COMPOSE) exec app
RUN     := $(COMPOSE) run --rm --no-deps app

.PHONY: help setup up down build rebuild restart ps logs shell tinker \
        migrate fresh seed test test-php test-js e2e e2e-install coverage lint lint-php lint-js \
        analyse types format check horizon-restart composer npm prod-image

help: ## List available commands
	@awk 'BEGIN {FS = ":.*##"} /^[a-zA-Z0-9_-]+:.*##/ {printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

setup: ## First run: create .env, build images, install dependencies, start the stack
	@sh docker/scripts/init-env.sh
	$(COMPOSE) build
	$(RUN) composer install --no-interaction
	$(RUN) sh -c 'grep -q "^APP_KEY=base64" .env || php artisan key:generate --ansi'
	$(RUN) npm ci --no-audit --no-fund
	$(MAKE) up

up: ## Start the stack in the background and wait until it is healthy
	$(COMPOSE) up -d --wait
	@echo "App:     http://localhost:$$(grep -E '^APP_PORT=' .env | cut -d= -f2)"
	@echo "Mailpit: http://localhost:$$(grep -E '^FORWARD_MAILPIT_PORT=' .env | cut -d= -f2)"
	@echo "Horizon: http://localhost:$$(grep -E '^APP_PORT=' .env | cut -d= -f2)/horizon"

down: ## Stop the stack
	$(COMPOSE) down

build: ## Build the development image
	$(COMPOSE) build

rebuild: ## Rebuild the development image without cache
	$(COMPOSE) build --no-cache

restart: ## Restart every service
	$(COMPOSE) restart

ps: ## Show service status
	$(COMPOSE) ps

logs: ## Follow logs (make logs s=horizon)
	$(COMPOSE) logs -f --tail=100 $(s)

shell: ## Open a shell in the app container
	$(EXEC) sh

tinker: ## Open Laravel Tinker
	$(EXEC) php artisan tinker

migrate: ## Run database migrations
	$(EXEC) php artisan migrate

fresh: ## Drop all tables, migrate and seed
	$(EXEC) php artisan migrate:fresh --seed

seed: ## Seed the database
	$(EXEC) php artisan db:seed

test: test-php test-js ## Run PHP and JS test suites

test-php: ## Run Pest (pass args: make test-php a="--filter=Locale")
	$(EXEC) php artisan test --compact $(a)

test-js: ## Run Vitest
	$(EXEC) npm run test

e2e: ## End-to-end tests with Playwright on their own database (make e2e a="--headed")
	$(EXEC) npm run build
	npx playwright test $(a)

e2e-install: ## Install the Playwright browser on the host (once)
	npx playwright install chromium

coverage: ## PHP coverage report; fails under 90%
	$(COMPOSE) exec -e XDEBUG_MODE=coverage app php artisan test --compact --coverage --min=90

lint: lint-php lint-js ## Check code style (Pint, ESLint, Prettier)

lint-php: ## Check PHP style with Pint
	$(EXEC) vendor/bin/pint --test

lint-js: ## Check JS/TS/Vue with ESLint and Prettier
	$(EXEC) sh -c 'npm run lint && npm run format:check'

analyse: ## Static analysis with Larastan
	$(EXEC) vendor/bin/phpstan analyse --memory-limit=1G

types: ## Type-check the frontend with vue-tsc
	$(EXEC) npm run types:check

format: ## Fix code style (Pint, ESLint, Prettier)
	$(EXEC) sh -c 'vendor/bin/pint && npm run lint:fix && npm run format'

check: lint analyse types test ## Everything CI runs

horizon-restart: ## Reload queue workers after code changes
	$(EXEC) php artisan horizon:terminate

composer: ## Run composer (make composer a="require foo/bar")
	$(EXEC) composer $(a)

npm: ## Run npm (make npm a="install foo")
	$(EXEC) npm $(a)

prod-image: ## Build the production image locally
	docker build --target prod -t $${COMPOSE_PROJECT_NAME:-jobs-platform}:local .

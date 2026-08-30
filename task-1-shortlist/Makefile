HTTP_PORT ?= 8000
.DEFAULT_GOAL := help
# Compose bierze nazwe projektu z katalogu, a ten u kazdego nazywa sie tak samo — wiec
# dwa rozwiazania na jednej maszynie dzielilyby wolumen bazy. Nazwa z hasha sciezki daje
# kazdej kopii wlasna baze. To wazne przy ocenianiu kilku rozwiazan po kolei.
COMPOSE_PROJECT_NAME ?= shortlist-$(shell pwd | cksum | cut -d' ' -f1)
DC = COMPOSE_PROJECT_NAME=$(COMPOSE_PROJECT_NAME) HOST_UID=$(shell id -u) HOST_GID=$(shell id -g) docker compose
RUN = $(DC) run --rm app
RUN_TEST = $(DC) run --rm -e APP_ENV=test app

.PHONY: help migration start stop restart install css watch db test shell logs console consumer fresh

help: ## Show this list
	@grep -hE '^[a-z-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN{FS=":.*?## "}{printf "  make %-10s %s\n", $$1, $$2}'

start: ## Build, install, migrate, compile CSS and serve (HTTP_PORT, domyslnie 8000)
	$(DC) build
	$(DC) up -d db
	$(RUN) composer install --no-interaction
	$(MAKE) db
	$(RUN) php bin/console tailwind:build
	$(DC) up -d app
	@echo
	@echo "  Ready:  http://localhost:$(HTTP_PORT)"

stop: ## Stop the containers, keep the data
	$(DC) stop

restart: ## Restart the application container
	$(DC) restart app

fresh: ## Throw everything away, including the database, and start over
	$(DC) down -v
	$(MAKE) start

install: ## Install PHP dependencies
	$(RUN) composer install --no-interaction

css: ## Build the CSS once. Run it after changing a template or app.css.
	$(RUN) php bin/console tailwind:build

watch: ## Build the CSS and keep rebuilding on every change. Ctrl+C to stop.
	$(RUN) php bin/console tailwind:build --watch

db: ## Apply migrations to the development and the test database
	$(RUN) php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
	$(RUN_TEST) php bin/console doctrine:database:create --if-not-exists
	$(RUN_TEST) php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

test: ## Run the test suite
	$(RUN) vendor/bin/phpunit

migration: ## Generate a migration from your mapping
	$(RUN) php bin/console doctrine:migrations:diff

consumer: ## Handle everything waiting in the async queue, then exit
	$(RUN) php bin/console ecotone:run async --finishWhenNoMessages=true -vv

console: ## Any Symfony command, e.g. make console C="debug:router"
	$(RUN) php bin/console $(C)

shell: ## Open a shell inside the application container
	$(DC) run --rm app bash

logs: ## Follow the application logs
	$(DC) logs -f app

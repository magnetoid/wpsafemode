COMPOSE := docker compose -f docker/compose.yml
DEV     := $(COMPOSE) run --rm dev

.PHONY: install test up down build shell logs test-host check-host

install:
	$(DEV) composer install
	cd frontend && npm install

test:
	$(DEV) vendor/bin/phpunit

up:
	$(COMPOSE) up -d wordpress
	@echo "WordPress:    http://localhost:8080"
	@echo "WP Safe Mode: http://localhost:8080/wpsafemode/"

down:
	$(COMPOSE) down

build:
	cd frontend && npm run build

shell:
	$(DEV) bash

logs:
	$(COMPOSE) logs -f wordpress

# Host fallbacks for when Docker is unavailable. `test-host` runs the suite on
# whatever PHP is installed locally, which does NOT enforce the 8.0 floor on its
# own — `check-host` adds the PHPStan gate that does (phpstan.neon.dist pins
# phpVersion to 80000). Use `check-host`, not `test-host`, before committing.
test-host:
	vendor/bin/phpunit

check-host:
	vendor/bin/phpstan analyse --no-progress
	vendor/bin/phpunit

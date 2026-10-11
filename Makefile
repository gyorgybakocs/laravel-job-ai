export ENV ?= dev
export RUN_COMPOSER ?= 0
export RUN_NPM ?= 1
export RUN_MIGRATE ?= 0

ENV_FILE := environment/.env.$(ENV)
SECRET_FILE := environment/.env.secrets.$(ENV)
-include $(ENV_FILE)
-include $(SECRET_FILE)

.PHONY: provision provision-app provision-node provision-nginx provision-postgres provision-adminer provision-redis provision-composer ci ci-install ci-composer ci-npm ci-npm-audit ci-migrate own dump-autoload test test-coverage test-coverage-unit test-coverage-feature up upd down restart

# 1. Provision: create Images
provision-app:
	docker compose --env-file $(ENV_FILE) --env-file $(SECRET_FILE) -f environment/docker-compose.provision-$(ENV).yaml build --no-cache app
provision-node:
	docker compose --env-file $(ENV_FILE) --env-file $(SECRET_FILE) -f environment/docker-compose.provision-$(ENV).yaml build --no-cache node
provision-nginx:
	docker compose --env-file $(ENV_FILE) --env-file $(SECRET_FILE) -f environment/docker-compose.provision-$(ENV).yaml build --no-cache nginx
provision-postgres:
	docker compose --env-file $(ENV_FILE) --env-file $(SECRET_FILE) -f environment/docker-compose.provision-$(ENV).yaml build --no-cache postgres
provision-adminer:
	docker compose --env-file $(ENV_FILE) --env-file $(SECRET_FILE) -f environment/docker-compose.provision-$(ENV).yaml build --no-cache adminer
provision-redis:
	docker compose --env-file $(ENV_FILE) --env-file $(SECRET_FILE) -f environment/docker-compose.provision-$(ENV).yaml build --no-cache redis
provision-composer:
	docker compose --env-file $(ENV_FILE) --env-file $(SECRET_FILE) -f environment/docker-compose.provision-$(ENV).yaml build --no-cache composer

provision:
	@echo "Provisioning for environment: $(ENV)"
	@$(MAKE) provision-app
	@$(MAKE) provision-node
	@$(MAKE) provision-nginx
	@$(MAKE) provision-postgres
	@if [ "$(ENV)" = "dev" ]; then \
    	$(MAKE) provision-adminer; \
    fi;
	@$(MAKE) provision-redis
	@$(MAKE) provision-composer

# 2. CI: Temporary containers (install.sh + DB setup)
ci-install:
	@echo "---------------------------------------------------"
	@echo "--- Starting INSTALL for ENV: $(ENV) ---"
	@echo "---------------------------------------------------"
	docker compose --env-file $(ENV_FILE) --env-file $(SECRET_FILE) -f environment/docker-compose.ci-$(ENV).yaml run --rm -e CI_SCRIPT=install composer
	docker compose --env-file $(ENV_FILE) --env-file $(SECRET_FILE) -f environment/docker-compose.ci-$(ENV).yaml down
	@echo "---------------------------------------------------"
	@echo "--- INSTALL finished for ENV: $(ENV) ---"
	@echo "---------------------------------------------------"

ci-composer:
	@echo "---------------------------------------------------"
	@echo "--- Starting COMPOSER for ENV: $(ENV) ---"
	@echo "---------------------------------------------------"
	docker compose --env-file $(ENV_FILE) --env-file $(SECRET_FILE) -f environment/docker-compose.ci-$(ENV).yaml run --rm -e CI_SCRIPT=composer composer
	docker compose --env-file $(ENV_FILE) --env-file $(SECRET_FILE) -f environment/docker-compose.ci-$(ENV).yaml down
	@echo "---------------------------------------------------"
	@echo "--- COMPOSER finished for ENV: $(ENV) ---"
	@echo "---------------------------------------------------"

ci-npm:
	@echo "---------------------------------------------------"
	@echo "--- Starting NPM for ENV: $(ENV) ---"
	@echo "---------------------------------------------------"
	docker compose --env-file $(ENV_FILE) --env-file $(SECRET_FILE) -f environment/docker-compose.ci-$(ENV).yaml run --rm -e CI_SCRIPT=npm node
	docker compose --env-file $(ENV_FILE) --env-file $(SECRET_FILE) -f environment/docker-compose.ci-$(ENV).yaml down
	@echo "---------------------------------------------------"
	@echo "--- NPM finished for ENV: $(ENV) ---"
	@echo "---------------------------------------------------"

ci-npm-audit:
	@echo "---------------------------------------------------"
	@echo "--- Starting NPM AUDIT for ENV: $(ENV) ---"
	@echo "---------------------------------------------------"
	docker compose --env-file $(ENV_FILE) --env-file $(SECRET_FILE) -f environment/docker-compose.ci-$(ENV).yaml run --rm -e CI_SCRIPT=npm-audit node
	docker compose --env-file $(ENV_FILE) --env-file $(SECRET_FILE) -f environment/docker-compose.ci-$(ENV).yaml down
	@echo "---------------------------------------------------"
	@echo "--- NPM AUDIT finished for ENV: $(ENV) ---"
	@echo "---------------------------------------------------"

ci-migrate:
	@echo "---------------------------------------------------"
	@echo "--- Starting MIGRATE for ENV: $(ENV) ---"
	@echo "---------------------------------------------------"
	docker compose --env-file $(ENV_FILE) --env-file $(SECRET_FILE) -f environment/docker-compose.ci-$(ENV).yaml run --rm -e CI_SCRIPT=migrate ci-worker
	docker compose --env-file $(ENV_FILE) --env-file $(SECRET_FILE) -f environment/docker-compose.ci-$(ENV).yaml down
	@echo "---------------------------------------------------"
	@echo "--- MIGRATE finished for ENV: $(ENV) ---"
	@echo "---------------------------------------------------"

ci:
	@echo "---------------------------------------------------"
	@echo "--- Starting Full CI Process for ENV: $(ENV) ---"
	@echo "---------------------------------------------------"
	@-$(MAKE) down
	@$(MAKE) ci-install
	@$(MAKE) ci-migrate
	@$(MAKE) ci-npm
	@echo "---------------------------------------------------"
	@echo "--- Full CI process finished for ENV: $(ENV) ---"
	@echo "---------------------------------------------------"

own:
	@echo "---------------------------------------------------"
	@echo "--- Resetting app ownership for ENV: $(ENV) ---"
	@echo "---------------------------------------------------"
	@-$(MAKE) down
	docker compose --env-file $(ENV_FILE) --env-file $(SECRET_FILE) -f environment/docker-compose.ci-$(ENV).yaml run --rm composer sh -c "chown -R 1000:1000 /app && chown -R www-data:www-data /app/storage /app/bootstrap/cache && chmod -R 775 /app/storage /app/bootstrap/cache"
	docker compose --env-file $(ENV_FILE) --env-file $(SECRET_FILE) -f environment/docker-compose.ci-$(ENV).yaml down
	@echo "---------------------------------------------------"
	@echo "--- Ownership reset finished for ENV: $(ENV) ---"
	@echo "---------------------------------------------------"

dump-autoload:
	@echo "---------------------------------------------------"
	@echo "--- Running composer dump-autoload for ENV: $(ENV) ---"
	@echo "---------------------------------------------------"
	docker compose --env-file $(ENV_FILE) --env-file $(SECRET_FILE) -f environment/docker-compose.ci-$(ENV).yaml run --rm composer sh -c "composer dump-autoload"
	docker compose --env-file $(ENV_FILE) --env-file $(SECRET_FILE) -f environment/docker-compose.ci-$(ENV).yaml down
	@echo "---------------------------------------------------"
	@echo "--- dump-autoload finished for ENV: $(ENV) ---"
	@echo "---------------------------------------------------"

test:
	docker compose --env-file $(ENV_FILE) --env-file $(SECRET_FILE) -f environment/docker-compose.$(ENV).yaml exec -T app sh -lc "php artisan config:clear --ansi && php artisan test"

test-coverage:
	docker compose --env-file $(ENV_FILE) --env-file $(SECRET_FILE) -f environment/docker-compose.$(ENV).yaml exec -T app sh -lc "php artisan config:clear --ansi && XDEBUG_MODE=coverage php artisan test --coverage-html storage/coverage"

test-coverage-unit:
	docker compose --env-file $(ENV_FILE) --env-file $(SECRET_FILE) -f environment/docker-compose.$(ENV).yaml exec -T app sh -lc "php artisan config:clear --ansi && XDEBUG_MODE=coverage php artisan test --testsuite Unit --coverage-html storage/coverage-unit"

test-coverage-feature:
	docker compose --env-file $(ENV_FILE) --env-file $(SECRET_FILE) -f environment/docker-compose.$(ENV).yaml exec -T app sh -lc "php artisan config:clear --ansi && XDEBUG_MODE=coverage php artisan test --testsuite Feature --coverage-html storage/coverage-feature"

# 3. Dev environment
up:
	docker compose --env-file $(ENV_FILE) --env-file $(SECRET_FILE) -f environment/docker-compose.$(ENV).yaml up

upd:
	docker compose --env-file $(ENV_FILE) --env-file $(SECRET_FILE) -f environment/docker-compose.$(ENV).yaml up -d --force-recreate

down:
	docker compose --env-file $(ENV_FILE) --env-file $(SECRET_FILE) -f environment/docker-compose.$(ENV).yaml down

restart:
	docker compose --env-file $(ENV_FILE) --env-file $(SECRET_FILE) -f environment/docker-compose.$(ENV).yaml restart

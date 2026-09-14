.DEFAULT_GOAL := help

API     := api
WEB     := web
CONSOLE := cd $(API) && php bin/console

.PHONY: help up down api stop web start db db-test test test-web cs stan qa

help: ## Liste des commandes
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-10s\033[0m %s\n", $$1, $$2}'

## —— Environnement ————————————————————————————————————————
up: ## Démarre Postgres et Redis
	docker compose up -d --wait

down: ## Arrête Postgres et Redis
	docker compose down

api: ## Lance l'API en arrière-plan (http://127.0.0.1:8000/api)
	cd $(API) && symfony serve -d --no-tls

stop: ## Arrête le serveur Symfony
	cd $(API) && symfony server:stop

web: ## Lance le front (http://localhost:4200)
	cd $(WEB) && npm start

start: up api web ## Tout démarrer

## —— Base de données ——————————————————————————————————————
db: ## Recrée la base de dev et joue les migrations
	$(CONSOLE) doctrine:database:drop --force --if-exists
	$(CONSOLE) doctrine:database:create
	$(CONSOLE) doctrine:migrations:migrate -n --allow-no-migration

db-test: ## Recrée la base de test et joue les migrations
	$(CONSOLE) doctrine:database:drop --force --if-exists --env=test
	$(CONSOLE) doctrine:database:create --env=test
	$(CONSOLE) doctrine:migrations:migrate -n --allow-no-migration --env=test

## —— Qualité ——————————————————————————————————————————————
test: ## Tests de l'API
	cd $(API) && vendor/bin/phpunit

test-web: ## Tests du front
	cd $(WEB) && npm test -- --watch=false

cs: ## Corrige le style PHP
	cd $(API) && vendor/bin/php-cs-fixer fix

stan: ## Analyse statique PHP (niveau 8)
	cd $(API) && php bin/console cache:warmup -q && vendor/bin/phpstan analyse --memory-limit=512M

qa: ## Ce que la CI vérifie : style, PHPStan, tests
	cd $(API) && vendor/bin/php-cs-fixer check --diff
	$(MAKE) stan test

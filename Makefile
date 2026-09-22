.DEFAULT_GOAL := help

API      := api
WEB      := web
CONSOLE  := cd $(API) && php bin/console
# Le front (proxy /api dans web/proxy.conf.json) attend l'API sur ce port, pas un autre.
API_PORT := 8001
API_URL  := http://127.0.0.1:$(API_PORT)

.PHONY: help up down api wait-api stop status web start migrate fixtures db db-test test test-web cs stan qa

help: ## Liste des commandes
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-10s\033[0m %s\n", $$1, $$2}'

## —— Environnement ————————————————————————————————————————
up: ## Démarre Postgres et Redis
	docker compose up -d --wait

down: ## Arrête Postgres et Redis
	docker compose down

api: ## (Re)lance l'API sur le port 8001, avec le worker, et attend qu'elle réponde
	@# Un serveur lancé par erreur à la racine du dépôt occupe le port sans servir l'API.
	@symfony server:stop >/dev/null 2>&1 || true
	@cd $(API) && symfony server:stop >/dev/null 2>&1 || true
	cd $(API) && symfony server:start -d --no-tls --port=$(API_PORT)
	@$(MAKE) --no-print-directory wait-api

wait-api:
	@for i in $$(seq 1 30); do \
		code=$$(curl -s -o /dev/null -w '%{http_code}' $(API_URL)/api/districts); \
		if [ "$$code" = "200" ]; then echo "API prête : $(API_URL)/api"; exit 0; fi; \
		sleep 1; \
	done; \
	echo "L'API ne répond pas (dernier code : $$code). Journal : cd $(API) && symfony server:log"; exit 1

stop: ## Arrête l'API et son worker (Docker reste lancé : make down)
	@symfony server:stop >/dev/null 2>&1 || true
	cd $(API) && symfony server:stop

status: ## Qui tourne : serveurs Symfony, conteneurs, réponse de l'API
	@symfony server:list
	@docker compose ps --format 'table {{.Service}}\t{{.Status}}'
	@echo "API : $$(curl -s -o /dev/null -w '%{http_code}' $(API_URL)/api/districts) sur $(API_URL)"

web: ## Lance le front (http://localhost:4201), Ctrl+C pour l'arrêter
	cd $(WEB) && npm start

start: up migrate api web ## Tout démarrer : Docker, migrations, API + worker, front

migrate: ## Joue les migrations en attente (sans rien casser si tout est à jour)
	cd $(API) && symfony console doctrine:migrations:migrate -n --allow-no-migration

fixtures: ## Recharge les données de démo (efface la base de dev)
	cd $(API) && symfony console doctrine:fixtures:load -n && rm -rf var/cache/dev

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

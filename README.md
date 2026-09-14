# Cap Monta

Location de mobil-homes, bungalows et caravanes au CHM Montalivet et à Euronat, avec un calendrier de disponibilités fiable.

[![API](https://github.com/DamienCH33/cap-monta/actions/workflows/api.yml/badge.svg)](https://github.com/DamienCH33/cap-monta/actions/workflows/api.yml)
[![Web](https://github.com/DamienCH33/cap-monta/actions/workflows/web.yml/badge.svg)](https://github.com/DamienCH33/cap-monta/actions/workflows/web.yml)

## Stack

| Couche | Techno |
| --- | --- |
| API | PHP 8.4, Symfony 8.1, API Platform, Doctrine ORM |
| Base | PostgreSQL 17 (`daterange` + contrainte `EXCLUDE USING gist`) |
| Cache / verrous / rate limiting | Redis |
| Tâches différées | Symfony Messenger (transport Doctrine) |
| Front | Angular 22, SSR hybride |
| IA | Symfony AI, en assistance (import d'annonce, qualification des demandes) |
| Hébergement | Railway |

## Structure

```
api/        API Symfony
web/        Front Angular
docs/       Décisions d'architecture
compose.yaml  Postgres + Redis pour le dev
Makefile      Commandes du quotidien
```

## Démarrer

Prérequis : PHP 8.4 (`pdo_pgsql`, `redis`), Composer, Symfony CLI, Docker Compose v2, Node 24.

```bash
cd api && composer install && cd ..
cd web && npm install && cd ..

make up        # Postgres + Redis
make db        # base de dev + migrations
make api       # http://127.0.0.1:8000/api
make web       # http://localhost:4200
```

`make` sans argument liste toutes les commandes.

## Qualité

```bash
make qa        # php-cs-fixer, PHPStan niveau 8, PHPUnit — la même chose que la CI
make test-web
```

## Feuille de route

| Lot | Contenu | État |
| --- | --- | --- |
| 0 | Initialisation, CI, environnement de dev | ✅ |
| 1 | Domaine réservation : logements, périodes tarifaires, indisponibilités, contrainte d'exclusion, workflow des demandes | ⏳ |
| 2 | Front public : recherche, fiche logement, calendrier, demande | |
| 3 | Espace propriétaire : dispos, tarifs, demandes, export iCal | |
| 4 | Agent d'import d'annonce + jeu d'évaluation de 20 annonces | |
| 5 | Agent de qualification des demandes | |

Les choix structurants et leurs raisons sont dans [`docs/decisions.md`](docs/decisions.md).

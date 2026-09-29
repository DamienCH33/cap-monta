# Cap Monta

Location de bungalows, mobil-homes et chalets entre particuliers au **CHM Montalivet** et à **Euronat**, avec les semaines libres visibles d'un coup d'œil.

**En ligne : [cap-monta.up.railway.app](https://cap-monta.up.railway.app)**

[![API](https://github.com/DamienCH33/cap-monta/actions/workflows/api.yml/badge.svg)](https://github.com/DamienCH33/cap-monta/actions/workflows/api.yml)
[![Web](https://github.com/DamienCH33/cap-monta/actions/workflows/web.yml/badge.svg)](https://github.com/DamienCH33/cap-monta/actions/workflows/web.yml)

## Le problème

Sur les sites d'annonces existants, les disponibilités sont écrites à la main dans le texte : « 19/07 au 22/08 non disponible ». Pour trouver une semaine libre en août, il faut lire chaque annonce, puis écrire à plusieurs propriétaires et attendre leurs réponses.

Cap Monta fait l'inverse : chaque logement a un vrai calendrier, les semaines libres s'affichent directement dans les résultats de recherche, et le locataire envoie sa demande au propriétaire, qui répond sous 48 heures. Pas de commission, pas de paiement sur le site.

## Ce que fait le site

**Pour le locataire**
- recherche par dates, voyageurs, quartier, équipements, animaux ;
- une bande des semaines à venir sur chaque carte (plein = libre, hachuré = pris : lisible sans distinguer les couleurs) ;
- fiche logement avec calendrier sur 12 mois et devis calculé en direct ;
- demande de séjour sans compte, suivie par un lien personnel ; favoris et partage ;
- site en français, anglais, néerlandais et allemand (la clientèle du CHM est très européenne).

**Pour le propriétaire**
- **import de son annonce existante** : il colle son texte, l'IA en tire le logement, les tarifs par période et les dates déjà prises, il vérifie et publie ;
- calendrier, tarifs par période, photos, demandes à accepter ou refuser ;
- emails à chaque étape, relance à 24 h, expiration automatique à 48 h.

## Stack

| Couche | Techno |
| --- | --- |
| API | PHP 8.4, Symfony 8.1, API Platform 4, Doctrine ORM |
| Base | PostgreSQL 18 en prod (17 en dev et CI) |
| Front | Angular 22, rendu serveur (SSR), `$localize` en 4 langues |
| Asynchrone | Symfony Messenger (emails), tâche planifiée horaire |
| IA | Symfony AI + Mistral (`ministral-14b`, formule gratuite) |
| Cache, verrous | Redis (confort uniquement, voir plus bas) |
| Emails | Brevo (API HTTP) |
| Hébergement | Railway, région EU (Amsterdam) : 6 services |

```
                 Internet (HTTPS)
                        │
               ┌────────▼────────┐
               │   web (Node)    │  Angular SSR, 4 langues
               │                 │  relaie /api et /media/photos ─┐
               └─────────────────┘                                │ réseau privé
                                                                  │
 ┌──────────────┐  ┌─────────────┐  ┌──────────────────────┐      │
 │ cron (1 h)   │  │ worker      │  │ api (FrankenPHP)     │◄─────┘
 │ expirations, │  │ emails      │  │ Symfony, photos      │
 │ purge RGPD   │  │ (Messenger) │  │ sur volume           │
 └──────┬───────┘  └──────┬──────┘  └──────────┬───────────┘
        └─────────────────┼────────────────────┘
                 ┌────────▼───────┐   ┌─────────┐
                 │ PostgreSQL 18  │   │  Redis  │
                 └────────────────┘   └─────────┘
```

Un seul domaine public : l'API n'est joignable que par le réseau privé, via le serveur du front. Pas de CORS, cookie de session en `SameSite=Lax` sur le même domaine.

## Les choix qui comptent

Chaque décision est écrite avec son contexte et son coût dans [`docs/decisions.md`](docs/decisions.md) (34 ADR). Les principales :

**Une double réservation est impossible, et c'est la base qui le garantit** (ADR 003, 010). Les indisponibilités portent une contrainte d'exclusion PostgreSQL sur `daterange(arrivée, départ, '[)')` : deux séjours qui se chevauchent sur le même logement sont refusés par la base elle-même, quelle que soit la course entre deux requêtes. Départ exclu : le samedi du départ est libre pour l'arrivée suivante. Le verrou Redis par logement ne sert qu'à transformer une course en 409 propre.

**Redis n'est jamais indispensable** (ADR 021). Ce qui protège (limiteurs de débit) ou décide (réservations) vit dans PostgreSQL. Redis ne porte que le confort : cache du calendrier, verrou. Testé : Redis coupé, le site continue.

**L'IA assiste, elle ne décide pas** (ADR 008, 023, 025, 028).
- Le jeu d'évaluation a été écrit **avant** l'agent : de vraies annonces avec la réponse attendue, écrite à la main, et une commande qui note chaque version.
- Règle dure : **aucun prix absent du texte**, vérifiée automatiquement à chaque passage. Résultat : 0 prix inventé sur tous les passages.
- Score honnête sur 10 annonces jamais vues pendant les réglages : 2/10 parfaites, 11/17 périodes justes. Le réglage s'est arrêté là : c'est le plafond d'un petit modèle gratuit, et l'écran de vérification fait le reste. Le propriétaire corrige, son texte sous les yeux ; rien n'est enregistré sans lui.
- Téléphones et emails masqués avant l'envoi au modèle ; si Mistral tombe, le site continue sans import.

**Une demande a un cycle de vie strict** (ADR 012) : machine à états (Symfony Workflow) `envoyée → acceptée / refusée / expirée / annulée`, une seule porte d'entrée pour chaque transition, verrou optimiste contre deux réponses simultanées.

**Données personnelles** (ADR 013, 032) : suivi sans compte par jeton, coordonnées du locataire montrées seulement après acceptation, purge automatique (anonymisation des vieilles demandes, comptes jamais confirmés), suppression de compte par l'utilisateur. Mesure d'audience sans cookie (Umami), sans bandeau.

## Qualité

| | |
| --- | --- |
| Tests API | 324 (PHPUnit : unitaires, intégration sur vrai PostgreSQL, API) |
| Tests web | 103 (Vitest) |
| Analyse statique | PHPStan niveau 7, TypeScript strict, php-cs-fixer, Prettier |
| CI | GitHub Actions, API et web |
| PageSpeed (accueil) | ordinateur 100 / 100 / 100 / 100, mobile 93 / 100 / 100 / 100 |
| Sécurité | audit de la production : 83 contrôles, 0 échec ([`docs/audits.md`](docs/audits.md)) |

L'audit couvre notamment : fichiers sensibles et outils de debug inaccessibles, erreurs sans trace, en-têtes de sécurité, empoisonnement d'en-tête `Host`, CORS, contrôle d'origine et de `Content-Type` sur les écritures, limiteurs qui ignorent un `X-Forwarded-For` falsifié, espace propriétaire fermé sans session.

## Lancer le projet

Prérequis : PHP 8.4 (`pdo_pgsql`, `redis`, `intl`, `gd`), Composer, Symfony CLI, Docker Compose v2, Node 24.

```bash
cd api && composer install && cd ..
cd web && npm install && cd ..

make up        # PostgreSQL + Redis (Docker)
make db        # base de dev + migrations
make fixtures  # données de démonstration
make api       # http://127.0.0.1:8000/api
make web       # http://localhost:4200
```

Compte de démonstration : `proprietaire@example.com` / `motdepasse`.

```bash
make qa        # php-cs-fixer, PHPStan, PHPUnit, tests web : la même chose que la CI
make           # liste toutes les commandes
```

L'import d'annonce demande une clé Mistral (`MISTRAL_API_KEY` dans `api/.env.local`) ; sans elle, le reste du site fonctionne.

## Structure

```
api/          API Symfony (src/, tests/, migrations/, evals/ pour l'agent)
web/          Front Angular (SSR, locales dans src/locale/)
docs/         decisions.md (ADR), audits.md, deploiement.md
compose.yaml  PostgreSQL + Redis pour le dev
Makefile      commandes du quotidien
```

Mise en production pas à pas : [`docs/deploiement.md`](docs/deploiement.md).



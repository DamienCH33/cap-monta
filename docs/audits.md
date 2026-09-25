# Audits — Cap Monta

Passe complète du **25/09/2026**, sur le code des lots 0 à 4, les traductions et les conditions du séjour. Chaque ligne dit ce qui a été **constaté**, et comment : test automatisé, mesure, commande ou parcours dans un navigateur. Les grilles se rejouent avant chaque mise en production.

Légende : **OK** constaté bon · **Corrigé** faux le 25/09, corrigé et couvert par un test · **À faire** identifié, reste ouvert · **Au déploiement** ne se vérifie qu'en ligne (voir `deploiement.md`).

## Bilan

| | |
|---|---|
| Failles et bugs trouvés | 2 bloquants, 7 importants, une trentaine de moindres |
| Corrigés dans le code | tous, sauf ceux listés « À faire » en fin de document |
| Tests | API : 322 (PHPUnit), front : 102 (Vitest), parcours navigateur : 29 étapes |
| Restent | la configuration Railway, et les choix listés en fin de document |

Les deux bloquants :
1. **En production, le site aurait été cassé** : le serveur Node ne relayait pas `/api` vers Symfony. Toutes les fiches auraient répondu 404 (et Google les aurait retirées), rien n'aurait marché dans le navigateur. Invisible en développement, où `ng serve` a son propre relais.
2. **Calendrier de réservation décalé d'un jour** : l'API envoyait `2027-07-03T00:00:00+02:00`, comparé à `2027-07-03`. La première nuit prise restait cliquable ; le samedi de départ, qui est le jour où l'on peut arriver, était grisé.

---

# 1 — Front

| Point | Comment | État |
|---|---|---|
| Toutes les routes répondent, avec le bon code | Parcours navigateur + `curl` sur le build de prod, 4 langues | **OK** — 404 réel sur une route inconnue |
| Parcours voyageur complet | Script Playwright sur le build de prod : recherche → fiche → devis → favoris → partage → demande → Mes demandes → acceptation par le propriétaire → annulation → déconnexion → anglais | **OK** — 22 étapes, 0 erreur console, 0 réponse 5xx |
| Parcours propriétaire complet | Même script : brouillon → photo → conditions → publication → tarifs → calendrier → fiche publique | **OK** — 7 étapes |
| Relais `/api` en production | `server.ts` | **Corrigé** — relais `/api` et `/media` |
| Données du rendu serveur réutilisées par le navigateur | Nombre d'appels API après chargement d'une fiche | **Corrigé** — 3 appels en double avant, 0 après |
| Session expirée dans l'espace propriétaire | Lecture + test | **Corrigé** — retour à la connexion, puis à la page quittée |
| Retour après connexion vers une adresse extérieure | `?suite=//ailleurs.fr` | **Corrigé** — pages du site seulement |
| Fiche : état d'une fiche à l'autre, erreur qui coupe le chargement | Lecture | **Corrigé** |
| Devis : réponse en retard qui écrase la bonne | Lecture | **Corrigé** — `switchMap` |
| Double envoi d'une demande | Lecture | **Corrigé** |
| Accès à `window` / `document` hors navigateur | Revue complète | **OK** |
| Désabonnements | Revue complète | **OK** |
| TypeScript strict, gabarits stricts | `tsconfig.json` | **Corrigé** — activés, 0 erreur |
| `innerHTML`, `bypassSecurityTrust`, liens `target=_blank` | Revue | **OK** — aucun / `rel="noopener"` partout |
| Mobile et tablette | Captures à 5 largeurs, test de débordement | **OK** (passe du 24/09, refaite en EN/NL/DE) |
| Barre de progression des photos | Essai | **Corrigé** — restait à 0 % |

# 2 — Back et API

| Point | Comment | État |
|---|---|---|
| Invariants garantis par la base | Migrations, `DatabaseConstraintsTest` | **Corrigé** — la base acceptait capacité 0, prix négatifs, statuts inconnus : contraintes CHECK ajoutées |
| Migrations rejouables de zéro, **et à l'envers** | Base jetable : tout monter, tout descendre, tout remonter | **Corrigé** — deux `down()` échouaient |
| Schéma = entités | `doctrine:schema:validate` | **Corrigé** — vert (les index d'exclusion sont déclarés), vérifié en CI |
| Codes HTTP | Tests | **Corrigé** — deux cas de 500 devenus 400/409 |
| Requêtes en boucle (N+1) | Requêtes comptées par endpoint | **Corrigé** — liste : 17 → 9 requêtes ; « Mes logements » et boîte de réception : 1 requête au lieu d'une par ligne |
| Temps de réponse | `curl`, à chaud | **OK** — tout sous 60 ms avec les données de démo |
| Tenue à 20 000 annonces | Base jetable remplie | **À faire** (lointain) — pagination faite en PHP : 900 ms à 20 000 annonces, seuil de 200 ms vers 4 000 |
| Mots de passe | `security.yaml` | **OK** — bcrypt coût 13, 10 caractères minimum, refus des mots de passe fuités |
| Code inutile | Revue | **Corrigé** — config IA générique, méthodes mortes, favicon, `dump.rdb` |

# 3 — Sécurité (tests d'intrusion en local)

| Test | Résultat | État |
|---|---|---|
| Lire ou modifier le logement, le calendrier, les tarifs, les photos, les demandes d'un autre propriétaire | 404 partout | **OK** |
| Envoyer `status`, `estimatedPrice`, `trackingToken` à la création d'une demande | 400 | **OK** |
| Deviner un lien de suivi | Jeton de 192 bits | **OK** |
| Injection SQL (tous les filtres, toutes les requêtes) | Paramètres liés partout | **OK** |
| Injection dans les emails | Échappement partout | **OK** |
| **Hameçonnage** : s'inscrire avec l'adresse d'une victime et un nom `Jean\nhttps://piege.fr` | La victime recevait un vrai email Cap Monta avec un faux bouton | **Corrigé** — ni lien ni retour à la ligne dans les noms |
| **Qui a un compte ?** chronométrer la connexion | 25 ms pour une adresse inconnue, 500 ms pour une connue | **Corrigé** — même temps dans les deux cas |
| Connexion avec `Proprietaire@Example.com` | Refusée | **Corrigé** |
| Demande pour l'an 9998 | Acceptée | **Corrigé** — 2 ans au plus, séjour de 120 nuits au plus |
| 9 223 372 036 854 775 807 voyageurs | Erreur 500 | **Corrigé** — 400 |
| Deux acceptations simultanées sur les mêmes dates | 500 | **Corrigé** — 409 |
| Formulaire piégé en `text/plain`, ou envoyé depuis un autre site | Passait (SameSite=Lax seulement) | **Corrigé** — 415 / 403 |
| Envoi de photos : type, taille, pixels, nom de fichier, métadonnées GPS | Réencodage WebP, nom aléatoire | **OK** |
| Envoi de photos en rafale | Pas de plafond | **Corrigé** — 60 par heure |
| Limiteurs de débit, contournement par `X-Forwarded-For` | En local, l'en-tête n'est pas cru | **OK** — **Au déploiement** : à refaire en ligne |
| Secrets dans l'historique Git | Tout l'historique parcouru | **OK** — aucune vraie clé |
| Dépendances vulnérables | `npm audit` : `sharp` (libvips) | **Corrigé** — 0.35.4 ; audits ajoutés à la CI (`composer audit` à lancer chez toi : Packagist bloqué ici) |
| En-têtes de sécurité | `curl -I` | **Corrigé** — CSP, X-Frame-Options, nosniff, Referrer-Policy, HSTS, sur le front **et** l'API |
| Traces d'erreur en production | Config | **Au déploiement** — `APP_ENV=prod`, `APP_DEBUG=0` |
| Documentation interactive de l'API | Publique | **Corrigé** — coupée en production |

# 4 — Base de données

| Point | État |
|---|---|
| Contraintes d'exclusion (double réservation impossible) | **OK** |
| Contraintes CHECK sur les nombres, montants, statuts, formats | **Corrigé** |
| Deux demandes identiques envoyées en même temps | **Corrigé** — index unique |
| Emails des voyageurs en minuscules | **Corrigé** |
| Index adaptés aux requêtes (EXPLAIN ANALYZE sur 20 000 annonces) | **Corrigé** — index des indisponibilités ; « retrouver mes demandes » par email |
| Sessions stockées dans des fichiers du conteneur | **Corrigé** — en base : un déploiement ne déconnecte plus personne |
| Rôle de connexion superutilisateur | **Au déploiement** — rôle dédié sans superutilisateur, voir `deploiement.md` |
| Sauvegarde et **restauration testée** | **Au déploiement** |
| Horodatages sans fuseau | **À faire** (faible) — ambiguïté d'une heure au passage à l'heure d'hiver |

# 5 — SEO

| Point | État |
|---|---|
| Rendu serveur de toutes les pages publiques | **OK** |
| Titre et description propres à chaque page, dans chaque langue | **OK** |
| `hreflang` + `x-default`, canonique par langue | **OK** |
| Sitemap : toutes les annonces | **Corrigé** — n'en listait que 15 (1re page) ; une entrée par langue |
| `robots.txt` | **Corrigé** — la page propriétaires était interdite par erreur ; adresse du sitemap selon le domaine |
| Quartier inconnu, API en panne | **Corrigé** — 404 + noindex / 503 (Google réessaie au lieu de désindexer) |
| JSON-LD (VacationRental, FAQPage) | **OK** — FAQ alignée sur le texte affiché |
| Open Graph pour le partage | **OK** |
| Un `h1` par page | **Corrigé** — manquait sur la recherche |

# 6 — Agent IA (assistant d'import)

| Point | État |
|---|---|
| Aucune invention possible (0 prix inventé sur tous les passages) | **OK** (ADR 028) |
| Relecture humaine avant tout enregistrement | **OK** |
| Sortie revalidée comme une saisie | **OK** |
| Plafonds (10 par propriétaire et par jour, 300 pour le site) | **OK** |
| Délai maximal sur les appels à Mistral | **Corrigé** — 30 s / 90 s ; un appel bloqué ne tient plus le worker (qui envoie aussi les emails) |
| Numéros de téléphone étrangers envoyés à Mistral | **Corrigé** — masqués comme les français |
| Un texte piégé pouvait mettre l'assistant en pause pour tout le monde | **Corrigé** |
| Usage des données par Mistral pour l'entraînement | **Au déploiement** — à désactiver dans la console Mistral |

# 7 — Accessibilité

| Point | État |
|---|---|
| Lien « Aller au contenu », zone `<main>` | **Corrigé** |
| Erreurs du formulaire de demande reliées à leur champ, message général annoncé | **Corrigé** |
| Jours pris du calendrier annoncés aux lecteurs d'écran | **Corrigé** |
| Information jamais portée par la couleur seule | **OK** — hachures + barré, cœur plein/vide, + / − dans la FAQ |
| Focus visible | **OK** |
| Menu Partager | **Corrigé** — rôle `menu` retiré (il promettait une navigation aux flèches absente) |

# 8 — Performance

| Point | État |
|---|---|
| Bundle initial | **OK** — ≈ 120 kB transférés |
| Images avec dimensions, formats modernes | **OK** |
| Cache HTTP des fichiers statiques | **Corrigé** — 1 an pour les fichiers à empreinte seulement, 1 h pour les autres |
| Réponses publiques de l'API | **Corrigé** — `Cache-Control: public` (60 s, 5 min pour les quartiers) |
| Redis qui ne répond plus | **Corrigé** — 0,3 s d'attente au lieu de 2 s par fiche ; le site continue sans Redis (testé) |

# Ajoutés à la passe

## RGPD

| Point | État |
|---|---|
| Durées de conservation appliquées | **Corrigé** — `app:privacy:purge`, tous les jours (ADR 032) |
| Droit à l'effacement du propriétaire | **Corrigé** — « Supprimer mon compte » dans Mon profil |
| Droit à l'effacement du voyageur | **OK** — sur demande écrite (politique de confidentialité) |
| Politique de confidentialité conforme à ce que fait le site | **Corrigé** — durées, Mistral, stockage dans le navigateur |

## Déploiement

Tout est prêt dans le dépôt : `api/Dockerfile`, `web/Dockerfile`, les fichiers `railway*.json` et `docs/deploiement.md` (services, variables, vérifications après mise en ligne, sauvegarde, retour arrière). Les images n'ont pas pu être construites ici (registre Docker bloqué) : premier `docker build` à faire chez toi.

# Reste ouvert

1. **Pagination en SQL** (recherche, suggestions) : utile vers 4 000 annonces.
2. **Horodatages avec fuseau** (`timestamptz`).
3. **Jetons de suivi stockés en clair** : à hacher un jour (ils apparaissent dans les URL, donc dans les journaux).
4. **Un seul format d'erreur** pour toute l'API (`problem+json`).
5. **Emails aux voyageurs en français** dans toutes les langues.
6. **Écart « adultes 13 ans et plus » / taxe de séjour à partir de 18 ans** : à trancher.

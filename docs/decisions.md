# Décisions d'architecture

Format court : le contexte, la décision, ce qu'elle coûte. Une décision remplacée n'est pas effacée, elle est marquée comme telle.

---

## 001 — Monorepo `api/` + `web/`

**Contexte.** Un seul développeur, un contrat d'API qui va bouger souvent pendant les lots 1 à 3.

**Décision.** Un dépôt, deux dossiers. Railway déploie un service par dossier (Root Directory), la CI filtre par chemin.

**Coût.** Historique Git commun. Séparable plus tard avec `git filter-repo` si besoin.

---

## 002 — Symfony 8.1 plutôt que 7.4 LTS

**Contexte.** Projet en développement actif, pas une appli à laisser tourner des années sans y toucher.

**Décision.** Symfony 8.1, montée vers 8.2 (novembre 2026), 8.3, puis 8.4 LTS (novembre 2027).

**Coût.** Une montée de version mineure tous les six mois. Sur Symfony, c'est quelques dépréciations à traiter, et ça se montre.

---

## 003 — Les chevauchements de séjours sont interdits par PostgreSQL

**Contexte.** Le bug classique des moteurs de réservation maison : deux requêtes concurrentes vérifient la dispo, toutes les deux voient « libre », toutes les deux écrivent.

**Décision.** Colonne `sejour daterange` en bornes `[)` (arrivée incluse, départ exclu) et contrainte `EXCLUDE USING gist (logement_id WITH =, sejour WITH &&)` avec l'extension `btree_gist`. Le code applicatif vérifie aussi, pour renvoyer un message propre, mais c'est la base qui garantit.

**Coût.** Type non géré nativement par Doctrine : type DBAL custom + migration écrite à la main pour la contrainte.

---

## 004 — Redis : trois usages, pas plus

1. Cache du calendrier 12 mois par logement, invalidé à chaque changement d'indisponibilité.
2. Verrou `resa:logement:{id}` (Symfony Lock) pendant la création d'une demande. Il évite de faire échouer une transaction sur la contrainte d'exclusion quand on peut l'éviter ; la contrainte reste le filet de sécurité.
3. Rate limiter sur `/api/agent/*` et `/api/demandes`, pour protéger le quota du fournisseur d'IA.

La recherche n'est pas mise en cache : quelques dizaines d'annonces, PostgreSQL répond en millisecondes.

---

## 005 — Messenger sur transport Doctrine

**Contexte.** Besoin de messages différés (expiration d'une demande à 48 h).

**Décision.** Transport Doctrine sur PostgreSQL. Le `DelayStamp` y fonctionne, les messages survivent à un redémarrage de Redis, et ça évite une dépendance à `ext-redis` côté transport.

**Coût.** Un worker `messenger:consume` à faire tourner sur Railway.

---

## 006 — Calendrier géré sur Cap Monta en V1, iCal en deux temps

**Contexte.** Certains propriétaires diffusent peut-être aussi sur Airbnb ou Abritel.

**Décision.**
- V1 : le propriétaire gère ses disponibilités sur Cap Monta.
- Lot 3 : **export** iCal (un flux `.ics` par logement, URL à jeton). Peu coûteux, utile tout de suite : le proprio l'ajoute à son agenda ou à Airbnb.
- **Import** iCal seulement quand un propriétaire multi-diffuse réellement. L'import est un polling toutes les X heures : il réduit le risque de double réservation, il ne le supprime pas. Pas la peine de le construire pour un cas hypothétique.
- Le modèle est prêt dès la première migration : `Indispo.source` accepte `ical`, et `Indispo.uidExterne` (nullable) stocke l'UID de l'événement importé.

---

## 007 — Angular en SSR hybride dès le premier commit

**Contexte.** Le trafic de ce type de site vient de Google. Une SPA pure n'est pas indexée correctement.

**Décision.** Pages publiques (accueil, recherche, fiche logement) rendues côté serveur avec meta et JSON-LD. Espace propriétaire en rendu client derrière authentification.

**Coût.** Discipline sur l'accès à `window` / `document` dès le début.

---

## 008 — L'IA assiste, elle ne décide pas

Si le fournisseur d'IA tombe, le calendrier, la recherche et les demandes fonctionnent. Les agents proposent (extraction d'annonce, brouillon de réponse), un humain valide. Aucun agent ne confirme une réservation ni n'invente un tarif.

---

## 003 bis — Révision de 003 : deux colonnes DATE, contrainte sur expression (14/09/2026)

**Ce qui change.** Pas de colonne `daterange`, donc pas de type DBAL custom ni de fonction DQL pour `&&`. Les entités portent `startDate` et `endDate` en `date_immutable`, types gérés nativement par Doctrine.

**La garantie ne bouge pas.** PostgreSQL accepte une contrainte d'exclusion sur une expression :

    EXCLUDE USING gist (accommodation_id WITH =, daterange(start_date, end_date, '[)') WITH &&)

Mêmes bornes `[)` — arrivée incluse, départ exclu — même index gist, même impossibilité d'insérer deux séjours qui se chevauchent.

**Côté requêtes.** Le chevauchement s'écrit en DQL ordinaire : `u.startDate < :departure AND u.endDate > :arrival`.

---

## 010 — Les contraintes d'exclusion échappent à Doctrine

Doctrine ne sait ni générer ni reconnaître une contrainte `EXCLUDE`. À chaque `make:migration`, il propose donc un `DROP INDEX <table>_no_overlap` : **ces lignes doivent être supprimées de la migration générée**, dans `up()` comme dans `down()`.

`tests/Integration/DatabaseConstraintsTest.php` vérifie que les contraintes sont toujours en base. Si une migration en supprime une, la CI casse.


---

## 011 — Règles de séjour : le minimum de nuits bloque, le jour d'arrivée informe (22/09/2026)

**Contexte.** Au CHM, beaucoup de propriétaires louent du samedi au samedi en haute saison. Refuser d'avance un mardi–jeudi, c'est parfois leur faire perdre une location qu'ils auraient prise (deux nuits entre deux semaines).

**Décision.** Deux natures de règles, par période tarifaire :
- **Minimum de nuits : bloquant.** Une demande plus courte est refusée (devis `stay_too_short`, 409 à la création). Décision de Damien : « si ça ne respecte pas le nombre de nuits, c'est mort ».
- **Arrivée le samedi : préférence.** `PricePeriod.saturdayArrival`. Le devis renvoie `outsideRules: true`, le visiteur le voit, la demande part quand même et arrive marquée « hors de vos règles » (`BookingRequest.outsideRules`). Le propriétaire décide.

Les trous entre périodes sont permis : le séjour est « à convenir », le propriétaire fixe le prix en acceptant (obligatoire dans ce cas).

---

## 012 — Cycle de vie d'une demande : une machine à états, une seule porte (22/09/2026)

**Décision.** Workflow Symfony `booking_request` (state machine) : `pending → accepted | declined | expired`, `pending | accepted → cancelled`. Seul `App\Service\Booking\BookingDesk` applique les transitions ; il tient le calendrier à jour (accepter crée l'indisponibilité liée à la demande, annuler la supprime), invalide le cache Redis du calendrier public et envoie les emails **après** l'écriture en base.

- **Accepter** prend le verrou Redis `resa:logement:{id}` (ADR 004), revérifie les dates, puis refuse d'office les autres demandes en attente sur les mêmes dates, avec un email : le voyageur n'attend pas 48 h un refus déjà certain.
- **Expiration à 48 h** : message `ExpireBookingRequest` avec `DelayStamp`, transport Doctrine (ADR 005). Filet : `app:booking-requests:expire` (cron horaire en production). Une réponse arrivée après le délai fait expirer la demande au lieu de l'accepter.
- **Emails par le worker** (`SendEmailMessage` routé sur `async`) : une panne SMTP ne fait plus échouer la requête HTTP. Synchrones en test pour pouvoir les vérifier.

**Coût.** Un worker `messenger:consume async` à faire tourner partout : en dev il démarre avec `symfony server:start` (`api/.symfony.local.yaml`), sur Railway ce sera un service à part.

---

## 013 — Suivi sans compte par jeton, coordonnées après acceptation (22/09/2026)

**Contexte.** `GET /api/booking-requests/{id}` renvoyait l'email et le téléphone du voyageur à quiconque connaissait l'identifiant. Or un UUID v7 commence par un horodatage : ce n'est pas un secret.

**Décision.** La lecture par identifiant est supprimée. Le voyageur reçoit un lien privé `/demande/{jeton}` (48 caractères hexadécimaux aléatoires, `BookingRequest.trackingToken`) pour suivre et annuler sa demande. Le propriétaire voit nom, message, dates et voyageurs ; **email et téléphone seulement après acceptation**, et le voyageur reçoit alors ceux du propriétaire. C'est la promesse écrite sous le formulaire de demande.

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

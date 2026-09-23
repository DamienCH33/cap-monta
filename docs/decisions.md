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

---

## 014 — Redis en panne : le site dégrade, il ne tombe pas (22/09/2026) — révisé par 021

**Décision.** Redis sert au confort, pas à la vérité (ADR 004) ; sa panne ne doit donc bloquer personne.
- **Cache du calendrier public** : lecture en base si le cache ne répond pas ; l'invalidation qui échoue est journalisée, pas propagée.
- **Limiteurs de débit** (`FloodGuard`) : ils laissent passer et journalisent. Un site ouvert quelques minutes sans plafond vaut mieux qu'un site fermé.
- **Verrou `resa:logement:{id}`** : l'acceptation continue sans lui. La contrainte d'exclusion PostgreSQL reste le vrai garde-fou contre la double réservation.

Tests : `tests/Unit/Service/RedisOutageTest.php`.

---

## 015 — Animaux : une règle par logement, trois valeurs (22/09/2026)

**Décision.** `Accommodation.petsPolicy` : `allowed`, `on_request` (défaut, et valeur des logements existants), `not_allowed`.
- `not_allowed` **bloque** comme le minimum de nuits (ADR 011) : devis `pets_not_allowed`, 409 à la création, et la recherche avec `pets ≥ 1` écarte ces logements.
- `on_request` **informe** : la demande part, le voyageur est invité à préciser l'animal dans son message.

---

## 016 — Relance du propriétaire à H+24 (22/09/2026)

**Décision.** À la création d'une demande, deux messages différés : `RemindOwnerOfBookingRequest` à +24 h puis `ExpireBookingRequest` à +48 h. La relance n'envoie rien si la demande a déjà reçu une réponse ou si elle a expiré entre-temps : le gestionnaire relit l'état au moment où il s'exécute, jamais celui de la création.

---

## 017 — Emails HTML dérivés du texte (22/09/2026)

**Décision.** Les mailers écrivent un seul texte ; `App\Service\Mail\MailComposer` en tire la version HTML (gabarit `templates/emails/layout.html.twig`) : un lien seul sur sa ligne devient un bouton, les lignes « Libellé : valeur » un tableau, un message entre « » une citation ; les lignes coupées à la main sont recollées. Un seul texte à maintenir, les tests continuent de lire le texte, et un client mail sans HTML ne perd rien. Ce que le voyageur écrit est échappé.

**Limite assumée.** La mise en forme dépend de conventions d'écriture du texte. Si un email a besoin un jour d'une mise en page propre (facture, récapitulatif riche), il aura son propre gabarit Twig.

---

## 018 — Équipements : liste fermée, par rubriques (22/09/2026)

**Décision.** 19 équipements en 4 rubriques (confort, cuisine, extérieur, pratique), définis dans `web/src/app/core/models/search-filters.ts`. Pas de texte libre : « clim », « climatisation » et « Clim réversible » casseraient le filtre de recherche. Ce qui manque va dans la description. Le filtre de la recherche ne propose que les 8 plus demandés (`filter: true`) ; les clés ne changent jamais, elles sont en base et dans les liens partagés.

---

## 019 — Prix d'un séjour : semaines entières, puis prorata (22/09/2026)

**Contexte.** Avec un prix à la semaine seul, un séjour de 4 nuits coûtait une semaine entière (arrondi à la semaine supérieure), alors que le propriétaire acceptait 2 nuits minimum.

**Décision.** Par période tarifaire : chaque semaine entière au prix semaine ; les nuits restantes à **1/7 du prix semaine** (arrondi à l'euro) si le séjour fait une semaine ou plus, sinon au prix à la nuit (ou 1/7 de la semaine s'il n'y en a pas) ; jamais plus cher qu'une semaine. Une semaine à cheval sur deux périodes est partagée au prorata. La fiche affiche « ≈ 93 € / nuit » quand seul le prix semaine existe, et la règle en une phrase.

---

## 020 — Réponses concurrentes et délai de réponse (22/09/2026)

- **Verrouillage optimiste** (`BookingRequest.version`) : deux changements d'état lus en même temps (le propriétaire accepte pendant que le voyageur annule, ou le worker fait expirer) ne s'écrivent plus l'un sur l'autre ; le second reçoit un 409 « rechargez la page ». Indépendant de Redis.
- **Délai de réponse** : 48 h, mais jamais au-delà de minuit la veille de l'arrivée ; relance à mi-délai. Arrivée au plus tôt **demain** (le propriétaire doit pouvoir répondre). Airbnb, Abritel et Booking.com donnent 24 h : 48 h est déjà large pour des particuliers, on n'allonge pas (le voyageur attend et ses autres options partent).
- **Fuseau** : toute l'API raisonne en heure de Paris (`Kernel::boot`), le serveur Railway étant en UTC.

---

## 021 — Redis : rien de ce qui protège ou décide n'en dépend (22/09/2026)

**Révision de l'ADR 014.** « Laisser passer quand Redis tombe » gardait le site debout mais ouvrait les protections : une panne Redis, et la connexion redevenait testable à l'infini. Nouvelle règle, par rôle :

| Rôle | Où | Si Redis tombe |
|---|---|---|
| Vérité (pas de double réservation, pas de réponses qui s'écrasent) | PostgreSQL : contrainte d'exclusion, `BookingRequest.version` | rien ne change |
| Protections (limites de connexion, d'inscription, de demandes…) | PostgreSQL : pool `rate_limiter.cache` (table `cache_items`), sans verrou | rien ne change |
| Confort (cache du calendrier, verrou `resa:logement:{id}` qui transforme une course en 409 propre) | Redis | lecture en base / acceptation sans verrou, journalisé |

- Délais Redis à 1 s (service `app.redis`, `LOCK_DSN`) au lieu de 30 s par défaut : un Redis muet ne fige pas les pages.
- `GET /api/health` : `database` / `cache` / `worker` ; 503 seulement si la base est tombée (le seul cas où l'hébergeur doit redémarrer), `degraded` sinon. À brancher sur le health check Railway et un service de surveillance (UptimeRobot ou équivalent) qui alerte sur `degraded`.
- `ResilientLoginRateLimiter` supprimé : sans Redis dans la boucle, il n'a plus de raison d'être.

**Pourquoi garder Redis ?** Le cache calendrier et le verrou restent de vrais gains, mesurables, et c'est un point d'architecture à savoir défendre : « Redis accélère, PostgreSQL décide ».

---

## 022 — Test d'intrusion du 22/09 : IP du client, en-têtes, signalements (22/09/2026)

Test d'intrusion local (autorisation, injections, XSS, logique de réservation, upload, CORS, CSRF, en-têtes). Tenu : accès entre propriétaires (404 partout), injections SQL/DQL, XSS (y compris le JSON-LD rendu côté serveur), affectation de masse, jeton de suivi, énumération, upload, CORS, CSRF.

Corrigé :
- **IP du client falsifiable** : `TRUSTED_PROXIES=127.0.0.1` faisait croire l'en-tête `X-Forwarded-For` → chaque requête pouvait se donner une IP et remettre les limites à zéro. Vide par défaut ; en production, seulement le réseau privé de l'hébergeur (`PRIVATE_SUBNETS` sur Railway, Symfony garde l'adresse ajoutée par le dernier proxy de confiance).
- **Connexion** : `App\Security\LoginRateLimiter`, trois compteurs dont un **par adresse email quelle que soit l'IP** (20 / heure). Contrepartie assumée : un attaquant peut bloquer la connexion d'un compte une heure ; « mot de passe oublié » reste ouvert.
- **Front** : `X-Powered-By` retiré, CSP minimale (`frame-ancestors 'none'`, `object-src 'none'`, `base-uri`, `form-action`), `X-Frame-Options`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, HSTS derrière HTTPS. Pas de `script-src` : Angular injecte des scripts en ligne au rendu serveur (à reprendre avec `autoCsp` plus tard).
- **`/api/health`** : état global seul pour tout le monde ; détail avec l'en-tête `X-Health-Token` = `HEALTH_TOKEN`.
- `expose_php = Off` (php.ini local ; à reprendre dans l'image de production).

**Signaler une annonce** (fin du lot 3) : `POST /api/accommodations/{slug}/reports`, entité `ListingReport` gardée (preuve de traitement, LCEN/DSA), email à `APP_MODERATION_EMAIL` (« URGENT » si une personne est reconnaissable), commande `app:accommodation:suspend <slug> --reason=…` qui retire l'annonce et prévient le propriétaire. Vrai code **404** (et 503 si l'API ne répond pas) pour un logement ou un quartier inexistant, via `RESPONSE_INIT`.

**À régler au déploiement** : `APP_ENV=prod` et `APP_DEBUG=0` (sinon traces complètes dans les erreurs), `TRUSTED_PROXIES`, `NG_ALLOWED_HOSTS` (sinon le serveur Angular répond 400), `HEALTH_TOKEN`, `APP_MODERATION_EMAIL`, type MIME et `nosniff` sur les photos (stockage objet).

---

## 023 — Import d'annonce : on mesure avant de construire (23/09/2026)

**Contexte.** L'agent du lot 4 lit une annonce en texte libre et propose des périodes tarifaires. Un agent qui « a l'air de marcher » sur trois exemples ne prouve rien ; un tarif inventé publié au nom d'un propriétaire, c'est grave.

**Décision.** Le jeu d'évaluation existe avant l'agent : 20 annonces réelles de location au CHM avec la réponse attendue, écrite à la main, et une commande `app:listing-import:eval` qui note n'importe quelle série de réponses. Règles principales (détail dans `api/evals/listing-import/README.md`) :
- **Aucun montant absent du texte.** Mesuré automatiquement : chaque montant renvoyé est cherché parmi les nombres de l'annonce.
- Un prix sans dates garde son prix et laisse les dates vides ; un prix sans unité est marqué `unknown`. Le propriétaire complète, il n'a jamais à corriger une valeur devinée.
- Le modèle **ne calcule pas** : « 1200 € pour 2 semaines » reste un prix de séjour, la conversion est faite en PHP.
- Notation stricte : une période « presque juste » est fausse.
- Le même objet `ListingExtraction` valide la sortie de l'agent et les réponses attendues.
- **Formulaire de l'annonce** (type, capacité, chambres, surface, quartier, équipements, animaux) : ce que renvoie le modèle est trié par le PHP en quatre cases — équipement de la liste (pré-coché), hors liste (`otherFeatures`, montré au propriétaire, laissé dans sa description), règle de la maison (son champ), valeur inventée ou impossible (refusée, jamais enregistrée). Rien n'est coché s'il n'est pas écrit ; une photo ne pourra que suggérer. Les éléments hors liste seront journalisés : la liste grandira d'après ce que les propriétaires écrivent (ADR 018).
- Téléphones et emails du texte : repérés par expression régulière (`ContactDetector`), pas par l'IA.

**Coût.** Les annonces réelles sont des textes de tiers : elles restent hors du dépôt public (`evals/listing-import/cases/` ignoré par git). La CI ne vérifie que trois exemples inventés ; le score réel se mesure en local.

---

## 024 — La liste des équipements est vérifiée par l'API (23/09/2026)

**Contexte.** L'ADR 018 fermait la liste des équipements, mais seul le front la connaissait : l'API acceptait n'importe quelle chaîne de 40 caractères. Une requête forgée pouvait enregistrer « jacuzzi », et l'agent du lot 4 aurait pu en faire autant.

**Décision.** Enum `App\Enum\Amenity` (19 clés, les mêmes que le front) et `Assert\Choice` sur la création et la modification d'un logement : une clé inconnue → 422 « Équipement inconnu ». `AmenityListSyncTest` compare l'enum à `web/src/app/core/models/search-filters.ts` et casse si l'une des deux listes change seule.

**Coût.** Ajouter un équipement = deux fichiers (l'enum et le fichier du front, qui porte le libellé et la rubrique). Les logements déjà en base ne sont pas revérifiés : en développement, les fixtures n'utilisent que des clés de la liste.

---

## 025 — L'import d'annonce : Mistral gratuit, un appel, un schéma strict, et le PHP qui vérifie (23/09/2026)

**Contexte.** Lot 4b : brancher un modèle sur le jeu d'évaluation de l'ADR 023. Contrainte posée par Damien : **ne rien payer**, ni pour le développement ni plus tard.

**Décision.**
- **Mistral, formule gratuite « Experiment »** : un numéro de téléphone, pas de carte bancaire. Modèle `mistral-small-2603` par défaut : le niveau gratuit ne sert que les modèles listés sur sa page Limites (20 000 tokens par minute, 1 requête par seconde pour celui-ci), et l'alias `mistral-small-latest` n'en fait pas partie (vu le 23/09 : refus 429 dès le premier appel). Une lecture d'annonce consomme 4 000 à 5 000 tokens : environ 4 par minute. Société et hébergement européens, bon niveau en français. Contrepartie écrite par Mistral : les requêtes de cette formule peuvent servir à entraîner ses modèles. D'où la règle suivante.
- **Les coordonnées ne partent jamais** : `ContactDetector::mask()` remplace téléphones et emails par `[téléphone]` / `[email]` avant l'envoi. Le reste du texte est l'annonce que le propriétaire publie déjà en public.
- **`symfony/ai-bundle` + `symfony/ai-mistral-platform`**, sans `symfony/ai-agent` : **un seul appel** au modèle, pas une boucle d'agent. Les vérifications prévues comme « outils » (chevauchement, question au propriétaire) sont sûres et gratuites en PHP : `ExtractionRules` ajoute la question quel que soit l'avis du modèle. Un outil ne sera ajouté que si l'évaluation montre qu'il améliore le score.
- **Changer de fournisseur** (un autre fournisseur, ou un modèle local avec Ollama) = installer le pont `symfony/ai-*-platform` voulu et changer le service injecté dans `ListingImporter`. Le code métier ne bouge pas ; l'évaluation compare sur les mêmes 20 annonces.
- **Schéma JSON strict** (`ListingImportSchema`) : toutes les clés, rien d'autre, listes fermées en `enum` (équipements, type, animaux, unités, quartiers connus). Le PHP revérifie tout (`ListingExtraction::fromArray()`, quartier ramené à un nom connu) : le schéma est une première barrière, pas la seule.
- **La consigne** (`config/prompts/listing-import.md`) ne reprend **aucun exemple du jeu d'évaluation** : sinon le score mesurerait la mémoire du modèle, pas sa lecture.
- **Une panne n'est pas une exception** : `ListingImportResult` sans extraction et avec l'erreur (ADR 008).
- **Questions de métier tranchées** : un prix sans unité reste `unknown` (l'écran d'import proposera « semaine », usage du CHM, confirmé d'un clic) ; « septembre (disponible à partir du 29 août) » commence le 29 août (location du samedi au samedi, le 29/08/2026 est un samedi) ; des lignes barrées perdues au copier-coller donnent des périodes qui se chevauchent → question, jamais de choix à la place du propriétaire.

**Coût.** Zéro euro, mais une formule gratuite a des plafonds de débit que Mistral ne publie pas (visibles dans son espace d'administration) et peut changer. Si elle disparaît, le site continue sans import (ADR 008) et on bascule sur un autre fournisseur. `symfony/ai` est en 0.x : `ListingImporterMistralTest` fait passer une vraie requête par le pont Mistral avec une réponse simulée pour détecter une rupture à la montée de version.

---

## 026 — Types de logement : chalet et studio, pas de tente ni d'emplacement (23/09/2026)

**Contexte.** Damien a relevé que le CHM propose aussi des tentes, des studios, des hébergements insolites et des emplacements. Vérification le 23/09 sur les sites du CHM, d'Euronat et de CôtéMonta.

**Décision.** Deux types de plus : **`chalet`** et **`studio`**, ce que les particuliers louent à Euronat (annonces CôtéMonta Euronat : studios, chalet bois, « chalet-appartement »). **Pas** de tente, d'hébergement insolite ni d'emplacement : au CHM comme à Euronat, ce sont les locations de l'exploitant du camping, pas des biens de propriétaires, et Cap Monta met en relation des particuliers. Une villa pourra s'ajouter le jour où un propriétaire d'Euronat en propose une.

**Coût.** Front et API à tenir d'accord (`AccommodationType`, `ACCOMMODATION_TYPES`). Le type est une chaîne en base : aucune migration. Les fixtures restent sur les trois types du CHM.

---

## 027 — L'assistant de lecture tourne à part, et échoue proprement (23/09/2026)

**Contexte.** Première version : un appel au fournisseur d'IA, et en cas de panne « échec » et un formulaire vide. Damien : « fais quelque chose de plus propre ». Deux autres contraintes : ne rien payer (formule gratuite à plafonds, ADR 025) et qu'un robot ne puisse pas épuiser ces plafonds.

**Décision.**
- **Jamais pendant la requête du propriétaire.** `POST /api/owner/listing-imports` enregistre le texte (entité `ListingImport`) et répond 202 tout de suite ; le worker Messenger fait la lecture (`RunListingImport`) ; le front interroge `GET /api/owner/listing-imports/{id}`. Un fournisseur lent ne fige aucune page.
- **Le texte n'est jamais perdu** : il est en base dès le POST et revient dans chaque réponse, même en échec. Le propriétaire peut quitter la page et revenir.
- **Chaque panne a sa conduite** (`ListingImportFailure`, `ListingImporter::classify()`) :
  | Cause | Nouveaux essais | Pause de l'assistant pour tous |
  |---|---|---|
  | fournisseur injoignable, erreur 5xx | 3 essais en tout (15 s, puis 1 min) | 5 min après le dernier échec |
  | plafond du fournisseur (429) | idem, en respectant le délai qu'il demande (2 min au plus) | 30 min |
  | réponse illisible | 1 essai de plus | aucune |
  | mauvaise clé, modèle inconnu | aucun, l'erreur est journalisée | 1 h |
  Les nouveaux essais sont programmés par le handler lui-même (`DelayStamp`), pas par la stratégie de Messenger (1 min à 4 h, faite pour les emails).
- **Pause partagée** (`AssistantAvailability`, pool `assistant.cache` dans PostgreSQL : le serveur web et le worker voient la même) : pendant une pause, `GET …/assistant` dit `available: false` et le front ne propose pas un bouton qui ne mènerait qu'à une erreur. Sans clé configurée, l'assistant est simplement `disabled`. `/api/health` affiche `assistant: ok | paused | off` ; `paused` rend l'état `degraded`.
- **Messages pour le propriétaire**, en clair, qui disent toujours que son texte est gardé et que le formulaire marche sans l'assistant.
- **Protection du quota gratuit** : adresse email confirmée ; 10 lectures par propriétaire et par jour ; 300 pour tout le site ; le même texte (à la casse et aux espaces près) collé à nouveau dans les 30 jours renvoie la lecture précédente sans rappeler le fournisseur ; texte de 30 à 12 000 caractères.

**Coût.** Une table de plus, un message Messenger de plus (le worker tourne déjà), et un écran qui interroge l'API toutes les deux ou trois secondes au lieu d'attendre une seule réponse. Les textes collés restent en base : à purger après quelques mois (tâche à prévoir avant la mise en ligne).

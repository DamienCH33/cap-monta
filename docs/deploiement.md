# Déploiement sur Railway

État au 25/09/2026 : tout est prêt dans le dépôt (Dockerfiles, fichiers `railway*.json`, variables listées ci-dessous). Le site n'est pas encore en ligne. Compte 5 à 10 $/mois (offre Hobby).

## Architecture

```
                 Internet (HTTPS, domaine public)
                              │
                     ┌────────▼────────┐
                     │   web (Node)    │  Angular SSR, 4 langues
                     │  server.mjs     │  relaie /api et /media ─┐
                     └─────────────────┘                         │ réseau privé
                                                                 │
   ┌──────────────────────┐   ┌─────────────────┐   ┌────────────▼───────────┐
   │ cron (toutes les h)  │   │ worker          │   │ api (FrankenPHP)       │
   │ expire, purge RGPD,  │   │ messenger:      │   │ Symfony, photos sur    │
   │ nettoyage limiteurs  │   │ consume async   │   │ volume /app/public/media│
   └──────────┬───────────┘   └────────┬────────┘   └───────────┬────────────┘
              └────────────────────────┼────────────────────────┘
                              ┌────────▼───────┐   ┌──────────┐
                              │  PostgreSQL 18 │   │  Redis   │
                              └────────────────┘   └──────────┘
```

Un seul domaine public (décision 009) : seul le service **web** a un domaine. L'API n'est joignable que par le réseau privé, via le relais de `server.ts`.

## Services à créer

Tous depuis le même dépôt GitHub, région **EU West (Amsterdam)**. Railway a abandonné « Config as Code » : depuis le 28/08/2026 un nouveau service ne peut plus lire `railway*.json`. Ces fichiers restent dans le dépôt comme référence, mais les réglages se saisissent à la main dans *Settings*.

| Service | Root Directory | Réglages (Settings) | Domaine public |
|---|---|---|---|
| `web` | `/web` | Healthcheck `/robots.txt`, restart On Failure ×5, variable `PORT=4000` | `cap-monta.up.railway.app`, port 4000 |
| `api` | `/api` | Pre-deploy `sh -c 'php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration && php bin/console app:districts:sync'`, healthcheck `/api/health`, restart On Failure ×5, variable `PORT=8080` | **non** |
| `worker` | `/api` | Start `php bin/console messenger:consume async --time-limit=3600 --memory-limit=200M -vv`, restart Always, pas de healthcheck | non |
| `cron` | `/api` | Start `sh -c 'php bin/console app:booking-requests:expire && php bin/console app:privacy:purge && php bin/console cache:pool:prune'`, Cron Schedule `17 * * * *`, restart Never | non |
| `Postgres` | modèle PostgreSQL de Railway | — | non |
| `Redis` | modèle Redis de Railway | — | non |

Le Builder passe seul sur « Dockerfile » grâce au Dockerfile du Root Directory.

`api` : **volume** monté sur `/app/public/media` (les photos). Sans volume, toutes les photos disparaissent au déploiement suivant.

Les migrations passent avant chaque déploiement de `api` (pre-deploy), suivies de `app:districts:sync` qui crée les quartiers du CHM manquants (sans eux, aucune annonce CHM ne peut être créée ; les fixtures ne tournent jamais en prod). Si une étape échoue, l'ancienne version reste en ligne.

## Variables

Les valeurs `${{…}}` sont des références Railway : elles se mettent à jour seules.

### api, worker et cron (les mêmes pour les trois : *Shared Variables*)

| Variable | Valeur |
|---|---|
| `APP_ENV` | `prod` |
| `APP_DEBUG` | `0` |
| `APP_SECRET` | 32 caractères aléatoires, `openssl rand -hex 16`. **Jamais celui de dev** : il signe les liens de mot de passe et de vérification. |
| `DATABASE_URL` | `${{Postgres.DATABASE_URL}}?serverVersion=17&charset=utf8` |
| `REDIS_URL` | `${{Redis.REDIS_URL}}` |
| `LOCK_DSN` | `${{Redis.REDIS_URL}}?timeout=1&read_timeout=1` |
| `MESSENGER_TRANSPORT_DSN` | `doctrine://default?auto_setup=0` |
| `TRUSTED_PROXIES` | `PRIVATE_SUBNETS,100.64.0.0/10` |
| `CORS_ALLOW_ORIGIN` | `^https://cap-monta\.up\.railway\.app$` (avec `^` et `$` : sans eux, `cap-monta.up.railway.app.pirate.com` passerait) |
| `DEFAULT_URI` | `https://cap-monta.up.railway.app` |
| `APP_FRONT_URL` | `https://cap-monta.up.railway.app` |
| `MAILER_DSN` | `smtp://bb76d4001%40smtp-brevo.com:CLE_SMTP@smtp-relay.brevo.com:587` (clé `cap-monta` de Brevo, jamais dans le dépôt) |
| `MAILER_FROM` | `noreply@damienchauveau-dev.fr` (domaine authentifié chez Brevo) |
| `APP_MODERATION_EMAIL` | ton adresse |
| `HEALTH_TOKEN` | aléatoire, pour voir le détail de `/api/health` |
| `PHOTOS_DIR` | `public/media/photos` |
| `PHOTOS_BASE_URL` | `https://cap-monta.up.railway.app/media/photos` |
| `MISTRAL_API_KEY` | la clé Mistral |
| `SUPPORT_URL` | `https://ko-fi.com/capmonta` |

### web

| Variable | Valeur |
|---|---|
| `SITE_URL` | `https://cap-monta.up.railway.app` (obligatoire : le serveur refuse de démarrer sans) |
| `API_URL` | `http://${{api.RAILWAY_PRIVATE_DOMAIN}}:8080` |
| `NG_ALLOWED_HOSTS` | `cap-monta.up.railway.app` (le nom exact seulement, **jamais** `*.up.railway.app` : n'importe qui peut créer un sous-domaine Railway) |

Domaine : `cap-monta.up.railway.app`, fourni par Railway (*Networking → Generate Domain*, puis renommé). Pour passer un jour à un domaine acheté : *Custom Domain*, CNAME chez le registrar, puis changer les variables ci-dessus, `web/src/environments/environment.ts`, le canonical de `web/src/index.html` et les pages légales.

## Avant la première mise en ligne

1. `cd api && composer remove symfony/ai-generic-platform` (paquet inutilisé, sa configuration est déjà retirée ; le proxy du bac à sable n'a pas pu le faire).
2. Chez Mistral (admin.mistral.ai) : **désactiver l'usage des données pour l'entraînement**, et fixer un plafond de dépense.
3. Tester les deux images en local :
   ```bash
   docker build -t cap-monta-api api && docker build -t cap-monta-web web
   ```
4. Pages légales : remplies le 28/09 (Railway, région EU West, contact@damienchauveau-dev.fr redirigé chez OVH).
5. Base de données : l'utilisateur fourni par Railway est superutilisateur. Créer un rôle pour l'application, sans ce droit :
   ```sql
   CREATE ROLE capmonta_app LOGIN PASSWORD '…';
   GRANT CONNECT ON DATABASE railway TO capmonta_app;
   GRANT USAGE ON SCHEMA public TO capmonta_app;
   GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO capmonta_app;
   GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO capmonta_app;
   ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO capmonta_app;
   ```
   L'application (`api`, `worker`, `cron`) se connecte avec ce rôle ; les migrations (`preDeployCommand`) gardent le rôle propriétaire. Optionnel au lancement, conseillé ensuite.
6. Photos du site (accueil, quartiers) : elles ne sont pas dans le dépôt. Soit les générer (`npm run photos`) et les versionner une fois les droits vérifiés, soit laisser l'aplat océan actuel.

## Après chaque mise en ligne : les vérifications

```bash
SITE=https://cap-monta.up.railway.app

# 1. Les pages répondent, dans les 4 langues, et le relais vers l'API marche
for p in / /en/ /nl/recherche /de/comment-ca-marche /api/districts; do
  curl -s -o /dev/null -w "$p %{http_code}\n" $SITE$p
done

# 2. Aucune trace d'erreur exposée (APP_DEBUG=0) : pas de "trace" dans la réponse
curl -s $SITE/api/accommodations/nexiste-pas | grep -c trace        # doit afficher 0

# 3. En-têtes de sécurité
curl -sI $SITE | grep -iE "content-security|x-frame|strict-transport|referrer"

# 4. Le limiteur voit la vraie IP : 6 demandes d'affilée → la 6e en 429,
#    même en changeant X-Forwarded-For à chaque fois
for i in 1 2 3 4 5 6; do
  curl -s -o /dev/null -w "%{http_code} " -H "X-Forwarded-For: 10.0.0.$i" \
    -H 'Content-Type: application/json' -d '{"email":"x@example.com"}' \
    $SITE/api/booking-requests/recover
done; echo

# 5. Santé détaillée
curl -s -H "X-Health-Token: $HEALTH_TOKEN" $SITE/api/health

# 6. Sitemap et robots
curl -s $SITE/robots.txt; curl -s $SITE/sitemap.xml | grep -c "<url>"
```

Si le test 4 renvoie six 200 : `TRUSTED_PROXIES` est trop large. Si tous les visiteurs se bloquent entre eux : il est trop étroit. Regarder alors l'IP vue par l'API dans les journaux Railway.

## Exploitation

- **Sauvegardes** : l'offre Hobby de Railway n'en fait pas (réservé à Pro). Elles se font depuis le PC, **une fois par semaine** et avant toute migration délicate, par le tunnel chiffré de la CLI Railway : la base n'a **pas** d'accès public (ne pas activer *Public Access*).
  ```bash
  # Une seule fois
  npm install -g @railway/cli && railway login
  railway link            # à la racine du dépôt : projet aware-heart, environnement production

  make backup-prod        # backups/cap-monta-AAAA-MM-JJ.dump, les 12 dernières gardées
  make restore-check      # restaure dans un Postgres 18 jetable et compte comptes / logements / demandes
  ```
  La prod est en **PostgreSQL 18** (image `postgres-ssl:18`) : `pg_dump` et `pg_restore` doivent être en 18, d'où les conteneurs `postgres:18-alpine` des scripts. Restaurer en prod (seulement en cas de perte) : ouvrir `railway connect Postgres --tunnel-only -P 54329`, puis `pg_restore --clean --if-exists --no-owner --no-acl` vers ce port. `backups/` est ignoré par Git : données personnelles, elles ne quittent pas le PC.
  Les photos (volume de `api`) ne sont pas dans cette sauvegarde : un propriétaire peut les renvoyer, pas ses demandes.
- **Surveillance** : UptimeRobot (gratuit) sur `https://cap-monta.up.railway.app/api/health`, alerte si le code n'est pas 200 ou si la réponse contient `degraded`.
- **Worker** : s'il s'arrête, les emails et les expirations attendent. `/api/health` le signale (`worker`). La tâche horaire rattrape les expirations.
- **Emails en échec** : `php bin/console messenger:failed:show` (shell Railway sur `worker`). Purgés après 30 jours.
- **Retour arrière** : Railway → service → *Deployments* → le déploiement précédent → *Redeploy*. Une migration déjà passée ne se défait pas toute seule : `php bin/console doctrine:migrations:migrate prev` (toutes les migrations savent redescendre, testé le 25/09).
- **Si Redis tombe** : le site continue (calendrier relu en base, réservations protégées par la contrainte d'exclusion). Testé le 25/09.
- **Tâche horaire** (`cron`) : expiration des demandes, purge RGPD, nettoyage des compteurs des limiteurs.

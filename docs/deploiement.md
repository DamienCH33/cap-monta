# Déploiement sur Railway

État au 25/09/2026 : tout est prêt dans le dépôt (Dockerfiles, fichiers `railway*.json`, variables listées ci-dessous). Le site n'est pas encore en ligne. Compte 10 à 15 €/mois.

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
                              │  PostgreSQL 17 │   │  Redis   │
                              └────────────────┘   └──────────┘
```

Un seul domaine public (décision 009) : seul le service **web** a un domaine. L'API n'est joignable que par le réseau privé, via le relais de `server.ts`.

## Services à créer

Tous depuis le même dépôt GitHub. Pour chacun : *Settings → Source → Root Directory*, et *Settings → Config-as-code → Railway Config File*.

| Service | Root Directory | Config File | Domaine public |
|---|---|---|---|
| `web` | `web` | `web/railway.json` | oui (le domaine du site) |
| `api` | `api` | `api/railway.json` | **non** |
| `worker` | `api` | `api/railway.worker.json` | non |
| `cron` | `api` | `api/railway.cron.json` | non |
| `postgres` | modèle PostgreSQL de Railway | — | non |
| `redis` | modèle Redis de Railway | — | non |

`api` : ajouter un **volume** monté sur `/app/public/media` (les photos). Sans volume, toutes les photos disparaissent au déploiement suivant.

Les migrations passent avant chaque déploiement de `api` (`preDeployCommand`). Si une migration échoue, l'ancienne version reste en ligne.

## Variables

Les valeurs `${{…}}` sont des références Railway : elles se mettent à jour seules.

### api, worker et cron (les mêmes pour les trois : *Shared Variables*)

| Variable | Valeur |
|---|---|
| `APP_ENV` | `prod` |
| `APP_DEBUG` | `0` |
| `APP_SECRET` | 32 caractères aléatoires, `openssl rand -hex 16`. **Jamais celui de dev** : il signe les liens de mot de passe et de vérification. |
| `DATABASE_URL` | `${{postgres.DATABASE_URL}}?serverVersion=17&charset=utf8` |
| `REDIS_URL` | `${{redis.REDIS_URL}}` |
| `LOCK_DSN` | `${{redis.REDIS_URL}}?timeout=1&read_timeout=1` |
| `MESSENGER_TRANSPORT_DSN` | `doctrine://default?auto_setup=0` |
| `TRUSTED_PROXIES` | `PRIVATE_SUBNETS,100.64.0.0/10` |
| `CORS_ALLOW_ORIGIN` | `^https://(www\.)?cap-monta\.fr$` (avec `^` et `$` : sans eux, `cap-monta.fr.pirate.com` passerait) |
| `DEFAULT_URI` | `https://cap-monta.fr` |
| `APP_FRONT_URL` | `https://cap-monta.fr` |
| `MAILER_DSN` | celui du service d'envoi (Brevo, Scaleway TEM…) |
| `MAILER_FROM` | `bonjour@cap-monta.fr` (domaine avec SPF, DKIM et DMARC configurés) |
| `APP_MODERATION_EMAIL` | ton adresse |
| `HEALTH_TOKEN` | aléatoire, pour voir le détail de `/api/health` |
| `PHOTOS_DIR` | `public/media/photos` |
| `PHOTOS_BASE_URL` | `https://cap-monta.fr/media/photos` |
| `MISTRAL_API_KEY` | la clé Mistral |

### web

| Variable | Valeur |
|---|---|
| `SITE_URL` | `https://cap-monta.fr` (obligatoire : le serveur refuse de démarrer sans) |
| `API_URL` | `http://${{api.RAILWAY_PRIVATE_DOMAIN}}:${{api.PORT}}` |
| `NG_ALLOWED_HOSTS` | `cap-monta.fr,www.cap-monta.fr` (**jamais** `*.up.railway.app` : n'importe qui peut créer un sous-domaine Railway) |

Remplacer `cap-monta.fr` partout par le domaine réellement acheté.

## Avant la première mise en ligne

1. `cd api && composer remove symfony/ai-generic-platform` (paquet inutilisé, sa configuration est déjà retirée ; le proxy du bac à sable n'a pas pu le faire).
2. Chez Mistral (admin.mistral.ai) : **désactiver l'usage des données pour l'entraînement**, et fixer un plafond de dépense.
3. Tester les deux images en local :
   ```bash
   docker build -t cap-monta-api api && docker build -t cap-monta-web web
   ```
4. Pages légales : remplacer les `[CROCHETS]` (hébergeur, région, adresse de contact).
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
SITE=https://cap-monta.fr

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

- **Sauvegardes** : activer les sauvegardes du service PostgreSQL. **Tester une restauration** sur une base jetable avant d'en avoir besoin :
  ```bash
  pg_dump "$DATABASE_URL" -Fc -f sauvegarde.dump
  createdb capmonta_restore && pg_restore -d capmonta_restore sauvegarde.dump
  ```
  Le volume des photos se sauvegarde à part (Railway : *Volume → Backups*).
- **Surveillance** : UptimeRobot (gratuit) sur `https://cap-monta.fr/api/health`, alerte si le code n'est pas 200 ou si la réponse contient `degraded`.
- **Worker** : s'il s'arrête, les emails et les expirations attendent. `/api/health` le signale (`worker`). La tâche horaire rattrape les expirations.
- **Emails en échec** : `php bin/console messenger:failed:show` (shell Railway sur `worker`). Purgés après 30 jours.
- **Retour arrière** : Railway → service → *Deployments* → le déploiement précédent → *Redeploy*. Une migration déjà passée ne se défait pas toute seule : `php bin/console doctrine:migrations:migrate prev` (toutes les migrations savent redescendre, testé le 25/09).
- **Si Redis tombe** : le site continue (calendrier relu en base, réservations protégées par la contrainte d'exclusion). Testé le 25/09.
- **Tâche horaire** (`cron`) : expiration des demandes, purge RGPD, nettoyage des compteurs des limiteurs.

#!/usr/bin/env bash
# Sauvegarde la base de prod sans l'exposer sur Internet : tunnel chiffré de la CLI Railway
# (railway connect --tunnel-only), pg_dump 18 (version de la prod) dans un conteneur jetable.
# Prérequis une seule fois : CLI Railway installée, `railway login`, `railway link` à la racine.
set -euo pipefail

PORT=54329                     # port local du tunnel, loin du 5432 de la base de dev
IMAGE=postgres:18-alpine       # même version majeure que la prod (postgres-ssl:18)
KEEP=12
out="backups/cap-monta-$(date +%F).dump"

command -v railway >/dev/null || { echo "CLI Railway absente : npm install -g @railway/cli, puis railway login et railway link"; exit 1; }

vars=$(railway variables --service Postgres --kv) || { echo "Projet non lié : lance railway link à la racine du dépôt"; exit 1; }
get() { sed -n "s/^$1=//p" <<<"$vars" | head -1; }
user=$(get PGUSER); pass=$(get PGPASSWORD); db=$(get PGDATABASE)
[ -n "$user" ] && [ -n "$pass" ] && [ -n "$db" ] || { echo "Variables PGUSER / PGPASSWORD / PGDATABASE introuvables sur le service Postgres"; exit 1; }

railway connect Postgres --tunnel-only -P "$PORT" >/dev/null 2>&1 &
tunnel=$!
trap 'kill "$tunnel" 2>/dev/null || true; rm -f "$out.part"' EXIT

for _ in $(seq 1 30); do
  (exec 3<>"/dev/tcp/127.0.0.1/$PORT") 2>/dev/null && break
  kill -0 "$tunnel" 2>/dev/null || { echo "Le tunnel Railway ne s'est pas ouvert (essaie : railway connect Postgres --tunnel-only)"; exit 1; }
  sleep 1
done

mkdir -p backups
docker run --rm --network host -e PGPASSWORD="$pass" "$IMAGE" \
  pg_dump --host=127.0.0.1 --port="$PORT" --username="$user" --dbname="$db" \
  --format=custom --no-owner --no-acl > "$out.part"
mv "$out.part" "$out"

echo "Sauvegarde : $out ($(du -h "$out" | cut -f1))"
ls -1t backups/*.dump | tail -n +$((KEEP + 1)) | xargs -r rm --
echo "Les $KEEP dernières sont gardées. Vérifier qu'elle se restaure : make restore-check"

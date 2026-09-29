#!/usr/bin/env bash
# Restaure la dernière sauvegarde dans un Postgres 18 jetable (la base de dev n'est pas touchée)
# et compte ce qui compte. Une sauvegarde qu'on n'a jamais restaurée n'est pas une sauvegarde.
set -euo pipefail

IMAGE=postgres:18-alpine
NAME=capmonta-restore-check
f=$(ls -1t backups/*.dump 2>/dev/null | head -1 || true)
[ -n "$f" ] || { echo "Aucune sauvegarde : make backup-prod"; exit 1; }

docker rm -f "$NAME" >/dev/null 2>&1 || true
trap 'docker rm -f "$NAME" >/dev/null 2>&1 || true' EXIT
docker run -d --name "$NAME" -e POSTGRES_PASSWORD=restore -e POSTGRES_DB=restore "$IMAGE" >/dev/null
for _ in $(seq 1 30); do docker exec "$NAME" pg_isready -U postgres -d restore >/dev/null 2>&1 && break; sleep 1; done

docker exec -i "$NAME" pg_restore -U postgres -d restore --no-owner --no-acl --exit-on-error < "$f"
docker exec "$NAME" psql -U postgres -d restore -c \
  'SELECT (SELECT count(*) FROM "user") AS comptes, (SELECT count(*) FROM accommodation) AS logements, (SELECT count(*) FROM booking_request) AS demandes, (SELECT count(*) FROM unavailability) AS dates_prises'
echo "OK : $f se restaure."

#!/usr/bin/env bash
# Sauvegarde la base de prod sans l'exposer sur Internet : pg_dump tourne DANS le conteneur
# Postgres de Railway (railway ssh), la sauvegarde revient en base64 par la liaison SSH.
# Même version de pg_dump que le serveur, aucun mot de passe manipulé ni affiché.
# Prérequis une seule fois : CLI Railway, `railway login`, `railway link`, clé SSH enregistrée.
#
# Pourquoi pas `railway connect --tunnel-only` : Railway refuse la redirection de port
# des sessions SSH sans commande (« port forwarding rides your dev.new session… not -N »).
set -euo pipefail

IMAGE=postgres:18-alpine       # pour contrôler la sauvegarde en local (même version que la prod)
KEEP=12
out="backups/cap-monta-$(date +%F).dump"

command -v railway >/dev/null || { echo "CLI Railway absente : voir docs/deploiement.md"; exit 1; }

raw=$(mktemp)
trap 'rm -f "$raw" "$out.part"' EXIT

# Le script distant arrive par l'entrée standard : pas de souci de guillemets, pas de terminal
# alloué (sortie propre). Connexion par le socket local, sans mot de passe.
railway ssh --service Postgres -- sh >"$raw" 2>&1 <<'REMOTE' || true
f=/tmp/cap-monta-backup.dump
if pg_dump -h /var/run/postgresql -U "${PGUSER:-postgres}" -d "${PGDATABASE:-railway}" \
     --format=custom --no-owner --no-acl >"$f" 2>"$f.err"; then
  echo @@DEBUT@@; base64 "$f"; echo @@FIN@@
else
  echo "pg_dump a échoué :"; cat "$f.err"
fi
rm -f "$f" "$f.err"
REMOTE

if ! grep -q '^@@FIN@@' "$raw"; then
  echo "La sauvegarde n'a pas abouti. Réponse de Railway :"
  sed -E 's/(PASSWORD[=:] *|postgresql:\/\/[^:]*:)[^@ ]*/\1***/Ig; s/^/  | /' "$raw"
  exit 1
fi

mkdir -p backups
sed -n '/^@@DEBUT@@/,/^@@FIN@@/p' "$raw" | sed '1d;$d' | tr -d '\r' | base64 -d > "$out.part"

# Une sauvegarde illisible ne doit pas remplacer les bonnes.
docker run --rm -i "$IMAGE" pg_restore --list < "$out.part" >/dev/null \
  || { echo "Sauvegarde reçue mais illisible par pg_restore : rien n'est gardé."; exit 1; }
mv "$out.part" "$out"

echo "Sauvegarde : $out ($(du -h "$out" | cut -f1))"
ls -1t backups/*.dump | tail -n +$((KEEP + 1)) | xargs -r rm --
echo "Les $KEEP dernières sont gardées. Vérifier qu'elle se restaure : make restore-check"

#!/usr/bin/env bash
# Activates an uploaded release on the server (ADR-0002, ADR-0003).
# Usage: activate.sh <deploy-path relative to $HOME or absolute> <release-id> <app-url>
# Touches nothing outside <deploy-path>.
set -euo pipefail
umask 022

DEPLOY_PATH="$1"
RELEASE="$2"
APP_URL="$3"

case "$DEPLOY_PATH" in
  /*) APP="$DEPLOY_PATH" ;;
  *) APP="$HOME/$DEPLOY_PATH" ;;
esac
case "$RELEASE" in
  *[!A-Za-z0-9-]* | "") echo "Invalid release id" >&2; exit 1 ;;
esac

REL="$APP/releases/$RELEASE"
SHARED="$APP/shared"
[ -d "$REL" ] || { echo "Release $RELEASE not found" >&2; exit 1; }
[ -f "$SHARED/.env" ] || { echo "Missing $SHARED/.env" >&2; exit 1; }

log() { printf '[activate] %s\n' "$*"; }

# Shared storage layout
mkdir -p "$SHARED/storage/app/public" "$SHARED/storage/app/private" \
  "$SHARED/storage/framework/cache/data" "$SHARED/storage/framework/sessions" \
  "$SHARED/storage/framework/views" "$SHARED/storage/logs"

cd "$REL"
log "Linking shared files"
ln -sfn ../../shared/.env .env
rm -rf storage && ln -sfn ../../shared/storage storage
ln -sfn ../../../shared/storage/app/public public/storage

# Staging: HTTP basic auth when a password file exists (the path is resolved on the server, never stored in git)
if [ -f "$SHARED/.htpasswd" ]; then
  log "Enabling basic auth"
  {
    echo "# BEGIN staging basic auth (added by deploy/activate.sh)"
    echo "AuthType Basic"
    echo "AuthName \"Staging\""
    echo "AuthUserFile \"$SHARED/.htpasswd\""
    echo "Require valid-user"
    echo "Header always set X-Robots-Tag \"noindex, nofollow\""
    echo "# END staging basic auth"
    echo
    cat public/.htaccess
  } > public/.htaccess.new && mv public/.htaccess.new public/.htaccess
fi

log "Migrating"
php artisan migrate --force --no-interaction

log "Caching config, routes, views and events"
php artisan optimize --no-interaction

PREVIOUS="$(readlink "$APP/current" 2>/dev/null || true)"

log "Switching current -> releases/$RELEASE"
cd "$APP"
ln -sfn "releases/$RELEASE" current.new
mv -Tf current.new current

rollback() {
  if [ -n "$PREVIOUS" ]; then
    log "Health check failed, rolling back to $PREVIOUS"
    ln -sfn "$PREVIOUS" current.new && mv -Tf current.new current
  fi
  exit 1
}

log "Health check"
# Credentials go to curl through a config on stdin, never on the command line (visible in `ps`).
curl_config() {
  if [ -f "$SHARED/.healthcheck" ]; then
    printf 'user = "%s"\n' "$(cat "$SHARED/.healthcheck")"
  fi
}
ok=0
for _ in 1 2 3 4 5; do
  if curl_config | curl -fsS --max-time 15 --config - "$APP_URL/up" | grep -q "\"release\":\"$RELEASE\""; then
    ok=1
    break
  fi
  sleep 3
done
[ "$ok" = 1 ] || rollback

php artisan queue:restart --no-interaction >/dev/null 2>&1 || true

log "Pruning old releases (keeping 3)"
cd "$APP/releases"
CURRENT_NAME="$(basename "$(readlink "$APP/current")")"
ls -1t | grep -v -x "$CURRENT_NAME" | tail -n +3 | while read -r old; do
  case "$old" in
    *[!A-Za-z0-9-]* | "") continue ;;
  esac
  rm -rf -- "$APP/releases/$old"
done

log "Release $RELEASE is live"

#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)}"
PHP_BIN="${PHP_BIN:-/usr/bin/php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
SUDO_BIN="${SUDO_BIN:-sudo}"
SYSTEMCTL_BIN="${SYSTEMCTL_BIN:-systemctl}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.4-fpm.service}"

for arg in "$@"; do
  case "$arg" in
    --restart-workers) : ;; # Compatibility: service restarts are now mandatory.
    -h|--help)
      cat <<'EOF'
Usage: ./deploy.sh [--restart-workers]

Maintenance deployment for the active application directory and sample systemd units.
FPM and workers are stopped before changes and restarted after a successful build.
--restart-workers is accepted for compatibility; restarts cannot be skipped.

Environment:
  APP_DIR       application root (default: directory containing this script)
  PHP_BIN       PHP executable (default: /usr/bin/php)
  COMPOSER_BIN  Composer executable (default: composer)
  SUDO_BIN      Noninteractive privilege tool (default: sudo)
  SYSTEMCTL_BIN Service manager executable (default: systemctl)
  PHP_FPM_SERVICE FPM unit (default: php8.4-fpm.service)
EOF
      exit 0
      ;;
    *) echo "Unknown option: $arg" >&2; exit 2 ;;
  esac
done

cd "$APP_DIR"
export APP_ENV="${APP_ENV:-prod}"
export APP_DEBUG="${APP_DEBUG:-0}"

if [[ "$APP_ENV" != prod || "$APP_DEBUG" != 0 ]]; then
  echo "Deployment requires APP_ENV=prod and APP_DEBUG=0." >&2
  exit 2
fi
if [[ ! "$PHP_FPM_SERVICE" =~ ^[a-zA-Z0-9_][a-zA-Z0-9_.@-]*\.service$ ]]; then
  echo "PHP_FPM_SERVICE must be a systemd .service unit name (not an option)." >&2
  exit 2
fi

for executable in "$PHP_BIN" "$COMPOSER_BIN" "$SUDO_BIN" "$SYSTEMCTL_BIN"; do
  command -v "$executable" >/dev/null || { echo "Missing executable: $executable" >&2; exit 2; }
done

run() {
  echo "==> $*"
  "$@"
}

# Retain active scaled instances across the stop/build/start sequence.
worker_list="$("$SYSTEMCTL_BIN" list-units --type=service --state=active --no-legend --plain 'fieldnotes-worker@*.service')"
worker_units=(fieldnotes-worker@async.service fieldnotes-scheduler.service)
while read -r unit _; do
  if [[ "$unit" == fieldnotes-worker@*.service && "$unit" != fieldnotes-worker@async.service ]]; then
    worker_units+=("$unit")
  fi
done <<< "$worker_list"

trap 'echo "Deployment failed; services may remain stopped. Inspect the failure before manually restoring service." >&2' ERR

# Fail before changing dependencies/schema if service-control permission is missing.
run "$SUDO_BIN" -n "$SYSTEMCTL_BIN" stop "${worker_units[@]}"
run "$SUDO_BIN" -n "$SYSTEMCTL_BIN" stop "$PHP_FPM_SERVICE"

run "$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction
run "$PHP_BIN" bin/console doctrine:migrations:migrate --no-interaction
run "$PHP_BIN" bin/console app:security:sync-permissions
run "$PHP_BIN" bin/console tailwind:build --minify
run "$PHP_BIN" bin/console asset-map:compile
run "$PHP_BIN" bin/console cache:clear
run "$PHP_BIN" bin/console cache:warmup

# CLI cache clearing cannot invalidate timestamp-disabled FPM OPcache.
run "$SUDO_BIN" -n "$SYSTEMCTL_BIN" restart "$PHP_FPM_SERVICE"
run "$SUDO_BIN" -n "$SYSTEMCTL_BIN" restart "${worker_units[@]}"
# A multi-unit is-active call succeeds when any unit is active, not all.
for unit in "$PHP_FPM_SERVICE" "${worker_units[@]}"; do
  run "$SYSTEMCTL_BIN" is-active --quiet "$unit"
done

echo "Deployment completed for APP_ENV=$APP_ENV at $APP_DIR"

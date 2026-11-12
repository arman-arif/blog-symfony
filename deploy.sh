#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)}"
PHP_BIN="${PHP_BIN:-/usr/bin/php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
RESTART_WORKERS=0

for arg in "$@"; do
  case "$arg" in
    --restart-workers) RESTART_WORKERS=1 ;;
    -h|--help)
      cat <<'EOF'
Usage: ./deploy.sh [--restart-workers]

Environment:
  APP_DIR       application root (default: directory containing this script)
  PHP_BIN       PHP executable (default: /usr/bin/php)
  COMPOSER_BIN  Composer executable (default: composer)
EOF
      exit 0
      ;;
    *) echo "Unknown option: $arg" >&2; exit 2 ;;
  esac
done

cd "$APP_DIR"
export APP_ENV="${APP_ENV:-prod}"
export APP_DEBUG="${APP_DEBUG:-0}"

run() {
  echo "==> $*"
  "$@"
}

run "$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction
run "$PHP_BIN" bin/console doctrine:migrations:migrate --no-interaction
run "$PHP_BIN" bin/console app:security:sync-permissions
run "$PHP_BIN" bin/console tailwind:build --minify
run "$PHP_BIN" bin/console asset-map:compile
run "$PHP_BIN" bin/console cache:clear
run "$PHP_BIN" bin/console cache:warmup

if [ "$RESTART_WORKERS" = "1" ]; then
  run "$PHP_BIN" bin/console messenger:stop-workers || true
  run sudo systemctl restart 'fieldnotes-worker@*' fieldnotes-scheduler
fi

echo "Deployment completed for APP_ENV=$APP_ENV at $APP_DIR"

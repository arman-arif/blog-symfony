# Operations runbook

## Services

| Service | Purpose |
|---|---|
| `nginx` / `apache2` | TLS termination, static files, PHP-FPM proxy |
| `php8.4-fpm` | Application |
| `mysql` | Primary database |
| `fieldnotes-worker@async` | Email/newsletter async queue |
| `fieldnotes-scheduler` | Publishing, campaign and analytics schedule |

Check:

```bash
sudo systemctl status nginx php8.4-fpm mysql fieldnotes-worker@async fieldnotes-scheduler
sudo journalctl -u fieldnotes-worker@async -n 100 --no-pager
sudo -u fieldnotes php bin/console messenger:failed:show
```

Retry failed messages:

```bash
sudo -u fieldnotes php bin/console messenger:failed:retry
```

Newsletter batch retries consult the recipient ledger and never resend a terminal/started attempt. The scheduler recovers pending dispatches and marks interrupted attempts older than 15 minutes as `uncertain`. Investigate these against provider logs; do not reset ledger/counters to force resends. Transactional email jobs are outside this ledger and retain Messenger retries. See [`newsletter.md`](newsletter.md) for SMTP settings, shared-cache requirements and migration reconciliation.

## Logs

- Application: `var/log/prod.log` (and `dev.log` locally)
- Security/mail/audit channels: configured under `config/packages/monolog.yaml`
- Nginx: `/var/log/nginx/fieldnotes.*.log`
- Systemd workers: `journalctl -u fieldnotes-worker@async`
- Audit trail: **Admin → Activity**

Rotate logs with logrotate; keep enough history for incident review without unbounded growth.

## Backups

Back up **both** the database and uploads. Example nightly job:

```bash
#!/usr/bin/env bash
set -euo pipefail
STAMP=$(date -u +%Y%m%dT%H%M%SZ)
DEST=/var/backups/fieldnotes
mkdir -p "$DEST"
mysqldump --defaults-extra-file=/etc/fieldnotes/backup.cnf \
  --single-transaction --routines --triggers fieldnotes \
  | gzip > "$DEST/fieldnotes-db-$STAMP.sql.gz"
tar -C /var/www/fieldnotes/public -czf "$DEST/fieldnotes-uploads-$STAMP.tar.gz" uploads
find "$DEST" -type f -mtime +14 -delete
```

`/etc/fieldnotes/backup.cnf` should contain `[client]` credentials with mode `600` owned by the backup user. Store copies off-host and test restores regularly.

### Restore

```bash
sudo systemctl stop fieldnotes-worker@async fieldnotes-scheduler
sudo -u fieldnotes php bin/console doctrine:database:drop --force --if-exists
sudo -u fieldnotes php bin/console doctrine:database:create
gunzip -c /var/backups/fieldnotes/fieldnotes-db-<stamp>.sql.gz | mysql fieldnotes
sudo -u fieldnotes tar -C /var/www/fieldnotes/public -xzf /var/backups/fieldnotes/fieldnotes-uploads-<stamp>.tar.gz
sudo -u fieldnotes php bin/console cache:clear
sudo systemctl start fieldnotes-worker@async fieldnotes-scheduler
```

Restore `SETTINGS_ENCRYPTION_KEY` with the backup; without it, encrypted SMTP/TOTP values cannot be read.

## Files and permissions

- Media deletion first commits a soft-deleted tombstone, then removes the original and every configured Liip derivative. Storage failures leave a durable pending cleanup, not a visible library record pointing to a missing file. Run `APP_ENV=prod php bin/console app:media:cleanup` after a failure (and periodically, e.g. hourly cron); a nonzero exit means cleanup still needs retrying. Deletion is blocked for inline content, revisions/restorable trash, SEO images, and website settings including logo/favicon.
- Image uploads are checked before GD decoding: maximum side 8192 pixels, maximum 16 megapixels, and a conservative estimated decode/encode budget capped at 256 MiB and the available PHP `memory_limit`. Raise PHP's memory budget only after measuring; the fixed safety ceilings still apply. Re-encoded byte length is stored as the media size.
- Code owned by `fieldnotes:fieldnotes`; files `644`, directories `755`.
- `var/`, `public/uploads/` writable by `fieldnotes` and `www-data` (directories `775`, files `664`).
- `public/uploads/` is inside the document root, so PHP execution is denied by the web-server config. Never relax that rule.
- `var/cache`/`var/log` must not be world-readable if logs contain sensitive data.
- `.env.local` mode `600` owned by `fieldnotes`.

## Cache and assets

```bash
sudo -u fieldnotes APP_ENV=prod php bin/console cache:clear
sudo -u fieldnotes APP_ENV=prod php bin/console cache:warmup
sudo -u fieldnotes APP_ENV=prod php bin/console tailwind:build --minify
sudo -u fieldnotes APP_ENV=prod php bin/console asset-map:compile
```

Application caches are tag-invalidated on writes. If a CDN/reverse proxy is added, purge it after content changes; the app sends `s-maxage=300` for anonymous public GETs and private/no-store for authenticated/admin responses.

## Scheduled jobs

Symfony Scheduler publishes due articles, sends scheduled newsletter campaigns and runs analytics rollups. The scheduler worker must be running. Cron fallback is documented in [`deployment.md`](deployment.md).

Manual equivalents:

```bash
php bin/console messenger:consume scheduler_default --time-limit=55 --limit=20
php bin/console app:analytics:rollup --days=2 --retention=30
```

## Database migrations

```bash
php bin/console doctrine:migrations:status
php bin/console doctrine:migrations:migrate --no-interaction
```

Always back up before migrating. Do not use `doctrine:schema:update` in production. The migration set has been verified on a fresh MySQL 8.4 database and MariaDB 12.3.

## SMTP

Configure at **Admin → Settings → Email/SMTP**. Leave transport on `env` to use `MAILER_DSN`, or save SMTP credentials (encrypted at rest). Use **Send test email** after changes. If delivery fails, inspect `var/log/prod.log` and the worker journal for retries. Do not commit `.env.local`.

## Maintenance mode

Enable at **Admin → Settings → General**. Visitors receive a branded 503 with `Retry-After`; signed-in administrators with `admin.access` can still browse, and `/login` remains available. Disable before re-running public monitoring/SEO checks.

## Upgrades

1. Back up DB/uploads; note current release commit.
2. Deploy new release to a new directory; run `./deploy.sh --restart-workers`.
3. Verify health: `/`, `/blog`, `/admin`, `/robots.txt`, a test email, analytics rollup.
4. Keep the previous release + backup for rollback.
5. Never change `SETTINGS_ENCRYPTION_KEY` during an upgrade unless re-encrypting sensitive values is planned.

## Incident checks

- 500s: `var/log/prod.log`, web-server error log, `bin/console about`.
- Login issues: `security` log channel, rate-limit cache, account active/verified state, 2FA enrollment.
- Missing media: `public/uploads/`, Flysystem path, Liip cache permissions, CDN headers.
- Stale analytics: ensure scheduler worker is consuming; run `app:analytics:rollup --days=2` manually.
- Stuck email: `messenger:failed:show`, SMTP settings, worker logs.
- CSP issues: browser console, nonce propagation, allowed origins in `SecurityHeadersListener`.

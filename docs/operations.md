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

Use Bash during a maintenance window. Record exact worker instances **before stopping them**: systemd may unload inactive units, so a later wildcard restart can miss them. Review the backup and stop public traffic before the destructive database restore below.

```bash
set -euo pipefail
cd /var/www/fieldnotes
worker_list="$(systemctl list-units --type=service --state=active --no-legend --plain 'fieldnotes-worker@*.service')"
worker_units=(fieldnotes-worker@async.service fieldnotes-scheduler.service)
while read -r unit _; do
  if [[ "$unit" == fieldnotes-worker@*.service && "$unit" != fieldnotes-worker@async.service ]]; then
    worker_units+=("$unit")
  fi
done <<< "$worker_list"
sudo systemctl stop php8.4-fpm.service "${worker_units[@]}"
sudo -u fieldnotes php bin/console doctrine:database:drop --force --if-exists
sudo -u fieldnotes php bin/console doctrine:database:create
gunzip -c /var/backups/fieldnotes/fieldnotes-db-<stamp>.sql.gz | mysql fieldnotes
sudo -u fieldnotes tar -C /var/www/fieldnotes/public -xzf /var/backups/fieldnotes/fieldnotes-uploads-<stamp>.tar.gz
sudo -u fieldnotes php bin/console cache:clear
sudo systemctl restart php8.4-fpm.service
sudo systemctl restart "${worker_units[@]}"
for unit in php8.4-fpm.service "${worker_units[@]}"; do
  systemctl is-active --quiet "$unit"
done
```

Replace `<stamp>` with the reviewed backup timestamp before executing. Restore `SETTINGS_ENCRYPTION_KEY` with the backup; without it, encrypted SMTP/TOTP values cannot be read. If any command fails, leave traffic stopped and inspect the restore state before resuming services; this example does not perform automatic recovery.

## Files and permissions

- Media deletion first commits a soft-deleted tombstone, then removes the original and every configured Liip derivative. Storage failures leave a durable pending cleanup, not a visible library record pointing to a missing file. Run `APP_ENV=prod php bin/console app:media:cleanup` after a failure (and periodically, e.g. hourly cron); a nonzero exit means cleanup still needs retrying. Deletion is blocked for inline content, revisions/restorable trash, SEO images, and website settings including logo/favicon.
- Image uploads are checked before GD decoding: maximum side 8192 pixels, maximum 16 megapixels, and a conservative estimated decode/encode budget capped at 256 MiB and the available PHP `memory_limit`. Raise PHP's memory budget only after measuring; the fixed safety ceilings still apply. Re-encoded byte length is stored as the media size.
- Code owned by `fieldnotes:fieldnotes`; files `644`, directories `755`.
- Private `var/` writable by `fieldnotes` and `www-data` (setgid directories `2770`, files `660`); public uploads/assets use directories `2775`, files `664`. Configure shared-write defaults/ACLs for newly created files as described in `installation.md`.
- `public/uploads/` is inside the document root, so PHP execution is denied by the web-server config. Never relax that rule.
- `var/cache`/`var/log` must not be world-readable if logs contain sensitive data.
- `.env.local` (and `.env.local.php` if generated) mode `640` owned by `fieldnotes:www-data`: readable by the CLI/worker owner and FPM group, not other users. Sample workers and FPM use the same Symfony environment file. An owner-only `600` file would be unreadable to FPM. Verify with `sudo -u www-data test -r /var/www/fieldnotes/.env.local` without exposing values.

## Cache and assets

```bash
sudo -u fieldnotes APP_ENV=prod php bin/console cache:clear
sudo -u fieldnotes APP_ENV=prod php bin/console cache:warmup
sudo -u fieldnotes APP_ENV=prod php bin/console tailwind:build --minify
sudo -u fieldnotes APP_ENV=prod php bin/console asset-map:compile
```

Application caches are tag-invalidated on writes. If a CDN/reverse proxy is added, purge it after content changes; the app sends `s-maxage=300` for anonymous public GETs and private/no-store for authenticated/admin responses.

The FPM sample disables OPcache timestamp checks. **CLI cache clearing does not reset FPM OPcache**: restart the correct FPM service after every code/container change or release-symlink switch. `deploy.sh` enforces this and fails if service control fails; restarting Nginx alone is insufficient for a separate FPM process.

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

Always back up before migrating. Do not use `doctrine:schema:update` in production. Historical MySQL 8.4 verification predates remediation; current full fresh-host verification remains open. Consult [`database.md`](database.md) and [`newsletter.md`](newsletter.md) before upgrading legacy newsletter schemas. Retain missing historical migration metadata; never mark an unavailable migration executed to bypass a defect.

## SMTP

Configure at **Admin → Settings → Email/SMTP**. Leave transport on `env` to use `MAILER_DSN`, or save SMTP credentials (encrypted at rest). Use **Send test email** after changes. If delivery fails, inspect `var/log/prod.log` and the worker journal for retries. Do not commit `.env.local`.

## Maintenance mode

Enable at **Admin → Settings → General**. Visitors receive a branded 503 with `Retry-After`; signed-in administrators with `admin.access` can still browse, and `/login` remains available. Disable before re-running public monitoring/SEO checks.

## Upgrades

1. Back up DB/uploads; note current release commit.
2. Follow `deployment.md`'s maintenance sequence for the active directory; run `./deploy.sh` as `fieldnotes` with noninteractive, narrowly scoped sudo service permissions. The compatibility flag `--restart-workers` is optional; stopping/restarting FPM and workers is mandatory. All active scaled async instances are retained.
3. Verify health: `/`, `/blog`, `/admin`, `/robots.txt`, a test email, analytics rollup.
4. Keep the previous release + backup for rollback.
5. Never change `SETTINGS_ENCRYPTION_KEY` during an upgrade unless re-encrypting sensitive values is planned.

If a build/migration/restart fails, the script exits nonzero and may leave services stopped. Inspect logs/schema state before manual recovery; it does not automatically roll back a migration or resume possibly incompatible code. The sync command only creates missing catalog entries and never overwrites customized roles, grants or permission metadata.

## Incident checks

- 500s: `var/log/prod.log`, web-server error log, `bin/console about`.
- Login issues: `security` log channel, rate-limit cache, account active/verified state, 2FA enrollment.
- Missing media: `public/uploads/`, Flysystem path, Liip cache permissions, CDN headers.
- Stale analytics: ensure scheduler worker is consuming; run `app:analytics:rollup --days=2` manually.
- Stuck email: `messenger:failed:show`, SMTP settings, worker logs.
- CSP issues: browser console, nonce propagation, allowed origins in `SecurityHeadersListener`.

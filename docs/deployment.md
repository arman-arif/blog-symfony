# Deployment

Assumes installation per [`installation.md`](installation.md), application at `/var/www/fieldnotes`, domain `journal.example.com`, PHP 8.4 FPM.

## Nginx

Install the sample:

```bash
sudo cp deploy/nginx.conf /etc/nginx/sites-available/fieldnotes
sudo ln -s /etc/nginx/sites-available/fieldnotes /etc/nginx/sites-enabled/fieldnotes
sudo nginx -t && sudo systemctl reload nginx
```

The sample:

- points the root at `public/`;
- sends only `index.php` to PHP-FPM (all other `.php` paths return 404);
- denies PHP/PHTML/PHAR execution under `/uploads/`;
- denies dotfiles except ACME challenges;
- caches immutable compiled assets and media for a week;
- gives `/media/cache/resolve/` a `^~` prefix so image-suffixed Liip resolver URLs reach Symfony instead of the static-file regex; generated files under `/uploads/media/cache/` are then served normally;
- accepts uploads up to 12 MB (keep PHP `upload_max_filesize`/`post_max_size` aligned with Settings → Media limits).

Obtain TLS with your organisation's certificate process or Certbot. Keep HSTS enabled only once HTTPS is verified; the application emits it on secure requests.

## Apache 2.4

```bash
sudo a2enmod proxy_fcgi setenvif rewrite headers
sudo cp deploy/apache-vhost.conf /etc/apache2/sites-available/fieldnotes.conf
sudo a2ensite fieldnotes
sudo apachectl configtest && sudo systemctl reload apache2
```

The sample uses `FallbackResource /index.php`, denies PHP execution in uploads and disables directory indexes.

## PHP-FPM pool

Install `deploy/php-fpm/fieldnotes.conf` in `/etc/php/8.4/fpm/pool.d/` and adjust:

```ini
user = www-data
group = www-data
listen = /run/php/fieldnotes.sock
pm = dynamic
pm.max_children = 20
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 6
pm.max_requests = 500
```

Tune `pm.max_children` from available RAM and observed PHP process size.

The dedicated `fieldnotes.sock` address avoids colliding with the distribution's existing `www` pool. Nginx and Apache samples use that same socket. Keep each pool on its own address; do not disable another application's pool blindly.

Enable OPcache in `/etc/php/8.4/fpm/conf.d/10-opcache.ini`:

```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.save_comments=1
```

Restart the **PHP-FPM service after every deployment**, container rebuild or release-symlink switch. CLI and FPM do not share OPcache: `cache:clear`, CLI `opcache_reset()` or a Nginx reload cannot invalidate FPM bytecode. `deploy.sh` enforces FPM stop/restart and does not report success on restart failure. If a different orchestrator is used, it must enforce the equivalent FPM lifecycle. Do not expose a public cache-reset endpoint. Timestamp validation may instead be enabled as an explicit operational tradeoff; it is disabled in this sample.

Use the group-readable `fieldnotes:www-data`/`0640` `.env.local` from `installation.md` for FPM and workers. Check GD in both SAPIs and check FPM secret-file readability before serving requests. Do not replace the GD dependency with Imagick.

## systemd workers

```bash
sudo cp deploy/systemd/fieldnotes-worker@.service /etc/systemd/system/
sudo cp deploy/systemd/fieldnotes-scheduler.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now fieldnotes-worker@async
sudo systemctl enable --now fieldnotes-scheduler
```

The sample units read the application's shared `.env.local` through Symfony. They do not maintain a second secret-file copy. When using a secrets manager instead, deliver identical runtime values to FPM and workers; systemd `EnvironmentFile` is not a general-purpose Symfony Dotenv parser.

`fieldnotes-worker@async` consumes queued email/newsletter/view jobs; `fieldnotes-scheduler` alone consumes Symfony Scheduler's `scheduler_default` transport. The async template always consumes the existing **`async`** transport; `%i` identifies the process, not the transport. Scale with `sudo systemctl enable --now fieldnotes-worker@2` (then `@3`, etc.) after checking queue contention. Do not run another scheduler via the async template.

For Redis, the template supplies a distinct `MESSENGER_CONSUMER_NAME=fieldnotes-%H-%i` per host/instance. Refer to it in the Redis DSN instead of hardcoding one consumer for every process. For example, in `.env.local` (keep the Doctrine DSN for non-Redis deployments):

```dotenv
MESSENGER_CONSUMER_NAME=fieldnotes-cli
MESSENGER_TRANSPORT_DSN="redis://127.0.0.1:6379/messages/symfony/${MESSENGER_CONSUMER_NAME}"
```

Symfony preserves the existing systemd environment variable over the Dotenv CLI fallback. Do not run simultaneous manual consumers with the same fallback name. Current real multi-process/Redis behavior remains an acceptance gate.

Each service runs with `--time-limit=3600 --memory-limit=256M` and restarts automatically. A manual post-deploy restart must include FPM as well (the helper does this automatically):

```bash
sudo -u fieldnotes php bin/console messenger:stop-workers
sudo systemctl restart php8.4-fpm 'fieldnotes-worker@*' fieldnotes-scheduler
```

This wildcard addresses currently loaded worker instances only. If workers were stopped separately, retain their exact names beforehand and restart those names explicitly, as in `operations.md`'s restore sequence; otherwise systemd may have unloaded them. The deployment helper already retains its instance list.

### Cron fallback

If systemd is unavailable, run scheduler every minute and a worker continuously:

```cron
* * * * * cd /var/www/fieldnotes && APP_ENV=prod php bin/console messenger:consume scheduler_default --time-limit=55 --limit=20 >/dev/null 2>&1
* * * * * cd /var/www/fieldnotes && APP_ENV=prod php bin/console messenger:consume async --time-limit=55 --limit=50 >/dev/null 2>&1
```

Cron is a fallback, not a replacement for a supervised worker at scale.

## Deploy script

`deploy.sh` performs this maintenance sequence:

1. Require `APP_ENV=prod`/`APP_DEBUG=0`, find active scaled async instances, and stop workers/scheduler and FPM **before modifying dependencies/schema**.
2. `composer install --no-dev --optimize-autoloader`
3. `doctrine:migrations:migrate`
4. `app:security:sync-permissions` (create missing entries; preserve existing policy/metadata)
5. `tailwind:build --minify`, `asset-map:compile`, `cache:clear` + `cache:warmup`
6. Mandatory FPM restart, then scheduler/async-instance restart and an individual active-state check for every unit. Any failure exits nonzero; no success message is emitted. A single multi-unit `is-active` call is insufficient because it succeeds if any unit is active.

Run as the application user from the **active application directory**, during an announced maintenance window:

```bash
sudo -u fieldnotes APP_ENV=prod ./deploy.sh --restart-workers
```

The script expects `.env.local`/environment variables, the sample services and backups to be prepared. Configure narrowly scoped noninteractive sudo rules for the exact `systemctl stop`/`restart` operations on the FPM unit, scheduler and configured async instances; test authorization as `fieldnotes` before deployment. It uses `sudo -n`, never a password prompt or ignored failure. `PHP_FPM_SERVICE` selects a different FPM unit when needed. `--restart-workers` remains accepted but is no longer necessary, and there is no skip-restart option.

This script is a downtime/in-place deployment helper, **not an atomic-release orchestrator**: it does not copy code, switch symlinks, take backups or roll back failed migrations. Stop traffic/workers before replacing the active checkout. If preparing a separate release, use external orchestration to drain traffic/stop workers, prepare shared uploads/runtime/secrets, build/migrate, atomically switch the active path, restart FPM/workers, and check health; never run the helper against an inactive checkout and assume it activates it. Keep the previous code/database backup for rollback. On any failure, inspect schema and logs before manually resuming services.

## Release/rollback checklist

- Back up the database and `public/uploads/` before schema changes.
- Stop traffic/workers before changing the active code; use the maintenance helper or the explicitly orchestrated release sequence above.
- Verify `/`, `/blog`, `/admin`, `/robots.txt`, `/sitemap.xml` and a test email.
- If the schema migration is not backward compatible, roll back code and restore the pre-deploy database backup; do not run down-migrations on production unless explicitly tested.
- Keep `SETTINGS_ENCRYPTION_KEY` unchanged across releases.

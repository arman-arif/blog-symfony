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
listen = /run/php/php8.4-fpm.sock
pm = dynamic
pm.max_children = 20
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 6
pm.max_requests = 500
```

Tune `pm.max_children` from available RAM and observed PHP process size. Enable OPcache in `/etc/php/8.4/fpm/conf.d/10-opcache.ini`:

```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.save_comments=1
```

Restart PHP-FPM after changes. Do not use `validate_timestamps=1` in production unless you accept per-request stat checks.

## systemd workers

```bash
sudo cp deploy/systemd/fieldnotes-worker@.service /etc/systemd/system/
sudo cp deploy/systemd/fieldnotes-scheduler.service /etc/systemd/system/
Put runtime environment values in `/etc/fieldnotes/fieldnotes.env` (mode `600`, same variables as `.env.local`) so workers receive the database URL and encryption key. The units also accept the application's `.env.local` through the working directory if the file is readable by the service user.

```bash
sudo install -d -m 0755 /etc/fieldnotes
sudo install -m 0600 /var/www/fieldnotes/.env.local /etc/fieldnotes/fieldnotes.env
sudo systemctl daemon-reload
sudo systemctl enable --now fieldnotes-worker@async
sudo systemctl enable --now fieldnotes-scheduler
```

`fieldnotes-worker@async` consumes queued email/newsletter jobs; `fieldnotes-scheduler` consumes Symfony Scheduler's `scheduler_default` transport (article publishing, newsletter campaigns, analytics rollups). Scale async workers with additional `fieldnotes-worker@async-2` instances only after checking database/queue contention.

Each service runs with `--time-limit=3600 --memory-limit=256M` and restarts automatically. After a deploy:

```bash
sudo -u fieldnotes php bin/console messenger:stop-workers
sudo systemctl restart 'fieldnotes-worker@*' fieldnotes-scheduler
```

### Cron fallback

If systemd is unavailable, run scheduler every minute and a worker continuously:

```cron
* * * * * cd /var/www/fieldnotes && APP_ENV=prod php bin/console messenger:consume scheduler_default --time-limit=55 --limit=20 >/dev/null 2>&1
* * * * * cd /var/www/fieldnotes && APP_ENV=prod php bin/console messenger:consume async --time-limit=55 --limit=50 >/dev/null 2>&1
```

Cron is a fallback, not a replacement for a supervised worker at scale.

## Deploy script

`deploy.sh` performs the safe sequence:

1. `composer install --no-dev --optimize-autoloader`
2. `doctrine:migrations:migrate`
3. `tailwind:build --minify`
4. `asset-map:compile`
5. `cache:clear` + `cache:warmup`
6. optional worker restart (`--restart-workers`)

Run as the application user from a release checkout:

```bash
sudo -u fieldnotes APP_ENV=prod ./deploy.sh --restart-workers
```

The script expects `.env.local`/environment variables to be prepared. It never runs fixtures and never edits the database outside migrations. Keep a release symlink/previous release for rollback.

## Release/rollback checklist

- Back up the database and `public/uploads/` before schema changes.
- Deploy code to a new release directory; run `deploy.sh`.
- Verify `/`, `/blog`, `/admin`, `/robots.txt`, `/sitemap.xml` and a test email.
- If the schema migration is not backward compatible, roll back code and restore the pre-deploy database backup; do not run down-migrations on production unless explicitly tested.
- Keep `SETTINGS_ENCRYPTION_KEY` unchanged across releases.

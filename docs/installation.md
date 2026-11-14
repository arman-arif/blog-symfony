# Installation (Ubuntu 24.04 / Debian 12)

This procedure installs Fieldnotes on a clean host using PHP-FPM, MySQL 8.4, Nginx or Apache and systemd. No Node.js is required.

## 1. Packages

```bash
sudo apt update
sudo apt install -y \
  nginx mysql-server composer unzip curl ca-certificates acl \
  php8.4-cli php8.4-fpm php8.4-mysql php8.4-intl php8.4-mbstring \
  php8.4-xml php8.4-curl php8.4-zip php8.4-gd php8.4-opcache
```

If the distribution does not ship PHP 8.4, add the Ondrej PPA/repository first. Verify:

```bash
php -v
php -m | grep -E 'ctype|curl|dom|fileinfo|gd|intl|mbstring|openssl|pdo_mysql|sodium|xml|zip'
```

**GD is mandatory in CLI and FPM**, including JPEG/PNG/WebP support: the upload decoder/re-encoder calls GD directly and Composer requires `ext-gd`. Imagick may be installed additionally for LiipImagine, but switching Liip's driver does not replace this dependency. Verify both SAPIs after installation, not just CLI `php -m`.

## 2. Database

```bash
sudo mysql
```

```sql
CREATE DATABASE fieldnotes CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'fieldnotes'@'127.0.0.1' IDENTIFIED BY 'replace-with-a-long-password';
GRANT ALL PRIVILEGES ON fieldnotes.* TO 'fieldnotes'@'127.0.0.1';
FLUSH PRIVILEGES;
```

MySQL 8.4 is the primary target. MariaDB 12.x is also supported.

## 3. Application user and files

```bash
sudo adduser --system --group --home /var/www/fieldnotes fieldnotes
sudo mkdir -p /var/www/fieldnotes
sudo chown -R fieldnotes:fieldnotes /var/www/fieldnotes
sudo -u fieldnotes git clone <repository-url> /var/www/fieldnotes
cd /var/www/fieldnotes
sudo -u fieldnotes composer install --no-dev --optimize-autoloader --no-interaction --no-scripts
```

Create writable runtime directories:

```bash
sudo -u fieldnotes mkdir -p var/cache var/log var/share public/uploads/media/cache public/assets
sudo chown -R fieldnotes:www-data var public/uploads public/assets
sudo find var -type d -exec chmod 2770 {} \;
sudo find var -type f -exec chmod 660 {} \;
sudo find public/uploads public/assets -type d -exec chmod 2775 {} \;
sudo find public/uploads public/assets -type f -exec chmod 664 {} \;
```

PHP-FPM runs as `www-data`; it needs read/write on `var/` and `public/uploads/`, and read access to the code. Keep `.env`, `var/`, `vendor/` and `src/` outside the document root (the document root is `public/`).

Set default ACLs for new runtime files created by both CLI/FPM; initial chmod alone does not guarantee future writability:

```bash
sudo setfacl -R -m u:fieldnotes:rwX,u:www-data:rwX var public/uploads public/assets
sudo find var -type d -exec setfacl -m d:u:fieldnotes:rwx,d:u:www-data:rwx,d:o::--- {} \;
sudo find public/uploads public/assets -type d -exec setfacl -m d:u:fieldnotes:rwx,d:u:www-data:rwx,d:o::rx {} \;
```

Keep private runtime/log files inaccessible to other users. Code directories remain `755`, files `644`; do not make the whole checkout web-writable. The default ACLs apply only to runtime/uploads/compiled assets, never to the secret file or application source.

## 4. Environment

Copy the reference values into `.env.local` (never commit secrets):

```dotenv
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=replace-with-64-random-hex
APP_SHARE_DIR=var/share
DEFAULT_URI=https://journal.example.com

DATABASE_URL="mysql://fieldnotes:replace-with-a-long-password@127.0.0.1:3306/fieldnotes?serverVersion=8.4&charset=utf8mb4"
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
LOCK_DSN=flock
MAILER_DSN=smtp://127.0.0.1:25
REDIS_URL=

SETTINGS_ENCRYPTION_KEY=replace-with-base64-encoded-32-random-bytes
```

Generate secrets:

```bash
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'
```

`SETTINGS_ENCRYPTION_KEY` protects SMTP passwords and TOTP secrets. Set it once and keep it stable; rotating it requires re-entering sensitive settings and re-enrolling 2FA. Use Redis for `REDIS_URL`/`MESSENGER_TRANSPORT_DSN` if you want shared cache/queues; otherwise Doctrine Messenger is sufficient.

Protect the file **after writing it** while retaining FPM/worker read access:

```bash
sudo chown fieldnotes:www-data /var/www/fieldnotes/.env.local
sudo chmod 0640 /var/www/fieldnotes/.env.local
sudo -u www-data test -r /var/www/fieldnotes/.env.local
sudo -u fieldnotes test -r /var/www/fieldnotes/.env.local
```

Do not use owner-only `0600` for this shared file: FPM runs as `www-data`, not `fieldnotes`. Restrict membership of `www-data` to trusted application processes; other PHP applications sharing that user can read it. Use a dedicated pool/user/secret integration for stronger isolation. The sample workers read this same file through Symfony; do not create an independently copied environment file that can drift. Any generated `.env.local.php` must have the same restricted ownership/mode. Never print these files for verification.

## 5. Schema and assets

```bash
cd /var/www/fieldnotes
sudo -u fieldnotes APP_ENV=prod php bin/console doctrine:migrations:migrate --no-interaction
sudo -u fieldnotes APP_ENV=prod php bin/console app:security:sync-permissions
sudo -u fieldnotes APP_ENV=prod php bin/console assets:install public
sudo -u fieldnotes APP_ENV=prod php bin/console importmap:install
sudo -u fieldnotes APP_ENV=prod php bin/console tailwind:build --minify
sudo -u fieldnotes APP_ENV=prod php bin/console asset-map:compile
sudo -u fieldnotes APP_ENV=prod php bin/console cache:clear
sudo -u fieldnotes APP_ENV=prod php bin/console cache:warmup
```

The initial Composer command intentionally deferred auto-scripts until the production environment/permissions were prepared; the commands above install bundle assets/importmap packages and rebuild the cache. TailwindBundle downloads the pinned standalone CLI into `var/tailwind`; no Node is installed. AssetMapper compiles the ES-module import map into `public/assets/`.

`app:security:sync-permissions` creates missing catalog permissions/default roles only. Existing labels/descriptions and grants (including deliberate revocations) are preserved. Apply reviewed permission-policy changes explicitly via the admin matrix or a dedicated migration; deployments never reset it.

Create the first administrator without loading demo fixtures:

```bash
sudo -u fieldnotes APP_ENV=prod php bin/console app:user:create-admin
```

Provide the email, display name and a high-entropy 12+ character password at the prompts. The real breach lookup requires outbound HTTPS to `api.pwnedpasswords.com`; outages fail validation rather than accepting an unverified password. The account is verified and assigned `ROLE_SUPER_ADMIN`. The demo fixtures remain **development only**; never load them on a public production database.

## 6. Web server and workers

Follow [`deployment.md`](deployment.md) to install the Nginx/Apache vhost, PHP-FPM pool and systemd units. Enable them:

```bash
sudo systemctl enable --now php8.4-fpm nginx
sudo systemctl enable --now fieldnotes-worker@async fieldnotes-scheduler
```

## 7. Smoke test

```bash
curl -fsS https://journal.example.com/robots.txt
curl -fsS -o /dev/null -w '%{http_code}\n' https://journal.example.com/
sudo systemctl status fieldnotes-worker@async fieldnotes-scheduler --no-pager
```

Check `/admin` login, send a test email from **Settings → Email/SMTP**, save an article, and verify the public page.

## 8. Verification status

- Historical 2026-11-12 evidence: fresh MySQL 8.4 migrations and 221 tests / 20,420 assertions, before review remediation.
- Current local remediation QA, regression coverage and remaining fresh-deployment/runtime checks are in [`qa.md`](qa.md), [`security.md`](security.md) and `../PROJECT_PROGRESS.md`. This installation runbook has not been independently followed on a clean Ubuntu VM since remediation; it is not proof of production readiness.

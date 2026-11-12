# Installation (Ubuntu 24.04 / Debian 12)

This procedure installs Fieldnotes on a clean host using PHP-FPM, MySQL 8.4, Nginx or Apache and systemd. No Node.js is required.

## 1. Packages

```bash
sudo apt update
sudo apt install -y \
  nginx mysql-server composer unzip curl ca-certificates \
  php8.4-cli php8.4-fpm php8.4-mysql php8.4-intl php8.4-mbstring \
  php8.4-xml php8.4-curl php8.4-zip php8.4-gd php8.4-opcache
```

If the distribution does not ship PHP 8.4, add the Ondrej PPA/repository first. Verify:

```bash
php -v
php -m | grep -E 'ctype|curl|dom|fileinfo|gd|intl|mbstring|openssl|pdo_mysql|sodium|xml|zip'
```

Install `imagick` instead of `gd` if your image policy requires it; LiipImagine supports both.

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
sudo -u fieldnotes composer install --no-dev --optimize-autoloader --no-interaction
```

Create writable runtime directories:

```bash
sudo -u fieldnotes mkdir -p var/cache var/log var/share public/uploads/media/cache public/assets
sudo chown -R fieldnotes:www-data var public/uploads public/assets
sudo find var public/uploads -type d -exec chmod 775 {} \;
sudo find var public/uploads -type f -exec chmod 664 {} \;
```

PHP-FPM runs as `www-data`; it needs read/write on `var/` and `public/uploads/`, and read access to the code. Keep `.env`, `var/`, `vendor/` and `src/` outside the document root (the document root is `public/`).

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

## 5. Schema and assets

```bash
cd /var/www/fieldnotes
sudo -u fieldnotes APP_ENV=prod php bin/console doctrine:migrations:migrate --no-interaction
sudo -u fieldnotes APP_ENV=prod php bin/console app:security:sync-permissions
sudo -u fieldnotes APP_ENV=prod php bin/console tailwind:build --minify
sudo -u fieldnotes APP_ENV=prod php bin/console asset-map:compile
sudo -u fieldnotes APP_ENV=prod php bin/console cache:clear
sudo -u fieldnotes APP_ENV=prod php bin/console cache:warmup
```

TailwindBundle downloads the pinned standalone CLI into `var/tailwind`; no Node is installed. AssetMapper compiles the ES-module import map into `public/assets/`.

Create the first administrator without loading demo fixtures:

```bash
sudo -u fieldnotes APP_ENV=prod php bin/console app:user:create-admin
```

Provide the email, display name and a strong 12+ character password at the prompts. The account is verified and assigned `ROLE_SUPER_ADMIN`. The demo fixtures remain **development only**; never load them on a public production database.

## 6. Web server and workers

Follow [`deployment.md`](deployment.md) to install the Nginx/Apache vhost, PHP-FPM pool and systemd units. Enable them:

```bash
sudo systemctl enable --now php8.4-fpm nginx
sudo systemctl enable --now fieldnotes-worker@async fieldnotes-worker@scheduler_default
```

## 7. Smoke test

```bash
curl -fsS https://journal.example.com/robots.txt
curl -fsS -o /dev/null -w '%{http_code}\n' https://journal.example.com/
sudo -u fieldnotes php bin/console messenger:consume async --limit=1
```

Check `/admin` login, send a test email from **Settings → Email/SMTP**, save an article, and verify the public page.

## 8. Verification performed on this project

- Migrations `up` from a fresh MySQL 8.4 database.
- Full PHPUnit suite on MySQL 8.4 (221 tests / 20,420 assertions) and MariaDB 12.3.
- `composer qa`, CSP/header checks, route crawler and query budgets.
- See [`qa.md`](qa.md) and [`security.md`](security.md).

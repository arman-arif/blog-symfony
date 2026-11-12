# Fieldnotes

A production-oriented editorial blogging platform and CMS built with **Symfony 8.1**, **Twig**, **Doctrine ORM**, **Tailwind CSS 3.4** and vanilla ES modules. Fieldnotes covers publishing workflows, media, comments, engagement, SEO, newsletters, analytics, advertising, configurable theming and a permission-controlled admin workspace.

No Node.js or JavaScript package manager is used. Tailwind is compiled by the pinned standalone CLI through TailwindBundle, and AssetMapper serves native ES modules.

## Features

- **Publishing:** sanitized rich content, revisions/autosave, workflow transitions, scheduling, categories, tags, duplicate/restore and revision comparison.
- **Media:** validated uploads (extension + detected MIME), random storage names, LiipImagine variants and a reusable picker.
- **Public site:** homepage, blog archive/search/filters, article pages, author/category/tag archives, RSS/Atom feeds, sitemap, SEO metadata/JSON-LD, redirects and cookie consent.
- **Engagement:** threaded comments with moderation/spam guards, likes, bookmarks and de-duplicated view tracking.
- **Newsletter:** double opt-in, unsubscribe, campaigns, scheduled delivery, test sends and subscriber export.
- **Analytics:** hourly daily-view rollups, cached dashboards, date-range reports, CSV export and query budgets.
- **Monetization:** scheduled/prioritised ad placements, sponsored content, affiliate-link handling and documented premium/payment extension points.
- **Customization:** persisted settings, SMTP configuration, colours/theme/logo/footer, configurable homepage and sidebar, maintenance mode, custom CSS/JS and a nested menu builder.
- **Security:** permission voters, TOTP 2FA with encrypted secrets, strong-password policy, login throttling, CSRF, CSP nonces, security headers and passive HTTP caching.
- **Operations:** real audit log, user/role/permission administration, deploy script, Nginx/Apache samples, systemd workers and backup/restore docs.

## Requirements

- PHP **8.4+** with `ctype`, `curl`, `dom`, `fileinfo`, `gd` (or Imagick), `intl`, `mbstring`, `openssl`, `pdo_mysql`, `sodium`, `xml`, `zip`
- MySQL **8.4** (primary target) or MariaDB 12.x
- Composer 2
- Nginx or Apache + PHP-FPM
- Optional: Redis (cache/messenger), SMTP/Mailpit (email)

No Node.js, npm, Yarn or build pipeline is required.

## Quick start (development)

```bash
composer install
php tools/setup-development.php   # local MySQL/Mailpit/Redis credentials, .env.local
docker compose --env-file var/development.env up -d database
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:fixtures:load --no-interaction # demo content
composer css
composer dev
```

Open `http://127.0.0.1:8000`. Mailpit, when enabled, is at `http://127.0.0.1:8025`.

Useful commands:

```bash
composer css          # compile Tailwind
composer css-watch    # watch styles
composer qa           # lint, static analysis, tests, audit, tooling guard
composer test
bin/console app:analytics:rollup --days=2
bin/console messenger:consume async scheduler_default
```

Demo credentials (development fixtures only): `writer1@example.test` / `fieldnotes-demo`. Change them immediately outside local development.

## Production

The full Ubuntu/Debian procedure, environment reference, Nginx/Apache samples, workers, backups and upgrades are in:

- [`docs/installation.md`](docs/installation.md)
- [`docs/deployment.md`](docs/deployment.md)
- [`docs/operations.md`](docs/operations.md)

Deploy with:

```bash
sudo -u fieldnotes ./deploy.sh --restart-workers
```

Create the first administrator once with `bin/console app:user:create-admin` (no demo fixtures required).

## Architecture

- **Domain services** own business rules; repositories own queries; voters enforce permission codes; controllers stay thin.
- **Settings** are database-backed, cached and encrypted for sensitive values. SMTP can use a saved transport or `MAILER_DSN`.
- **Content** is sanitized server-side; public queries eager-load their presentation graph; public reads are cached by the HTTP layer/CDN.
- **Async work** uses Symfony Messenger + Scheduler: publishing/newsletter/analytics jobs, with Doctrine as the default transport and Redis optional.
- **Security** uses `PermissionVoter`, resource voters, CSRF, TOTP, rate limits, CSP nonces and audit logging.

Detailed module, data, design, analytics, monetization, settings, security, QA and deployment documentation lives in [`docs/`](docs/).

## Roles

| Role | Scope |
|---|---|
| Super Administrator | Everything, including roles, permissions, SMTP and custom code |
| Administrator | Content, users, audience, pages, menus, ads and non-sensitive settings |
| Editor | Editorial pipeline, taxonomy, media and comments |
| Author | Own articles, media and comments on own work |
| Contributor | Draft/submit own articles and media |

The permission matrix is data-driven and editable at `/admin/permissions`.

## Verification

- `composer qa` green from the verified workspace.
- 221 tests / 20,420 assertions; Service line coverage 90.6%, Security 81.3%.
- PHPStan max level for `src/Service` and `src/Security`; level 6 for the full tree.
- Database migrations and the full test suite verified on **MySQL 8.4** and MariaDB 12.3.

## Documentation map

| Document | Contents |
|---|---|
| `docs/installation.md` | Clean Ubuntu/Debian install |
| `docs/deployment.md` | Web server, PHP-FPM, workers, cron, deploy script |
| `docs/operations.md` | Backups, permissions, cache, upgrades, runbook |
| `docs/architecture.md` | Layering and milestone architecture |
| `docs/database.md` | Schema, ERD, migration groups |
| `docs/security.md` | CSP, auth, password/2FA policy |
| `docs/qa.md` | Tests, coverage, query budgets, browser checks |
| `docs/analytics.md`, `docs/monetization.md`, `docs/settings-and-navigation.md` | Feature deep dives |

## License

Proprietary. See the project owner before redistribution.

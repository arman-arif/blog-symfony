# Architecture

## Inspection and stack lock

The initial repository contained only `PROMPT.md` and `IMPLEMENTATION_PLAN.md`; no existing application or user code was replaced. PHP CLI is 8.5.12, Composer 2.10.3. Required extensions (`intl`, `pdo_mysql`, `gd`, `sodium`, `zip`, `fileinfo`, DOM, mbstring) are present. The installed database client is MariaDB 12.3.3, **not** the required MySQL 8 server. MySQL-backed verification is reserved for M2/M3.

Symfony **8.1** is the latest compatible stable branch verified through Composer. Production requires PHP 8.4+, while PHP 8.3 deployments must use the separately planned Symfony 7.4 LTS branch; this project does not silently claim PHP 8.3 compatibility. PHPUnit 12 supports the chosen PHP minimum.

## UI-first foundation

Packages are installed individually with Flex instead of the broad `webapp` pack, to avoid unused infrastructure and frontend scaffolding. Doctrine, Security, Forms, Messenger and persistence arrive only in their planned milestones. AssetMapper serves local ES modules; TailwindBundle downloads and runs the standalone **v3.4.17** executable. No JavaScript package manager or runtime is used.

The editor choice is **Quill 2**, imported and vendored by PHP's `importmap:require` in M1.6. Its native editing/undo behavior is progressively enhanced with accessible vanilla controls for tables and embeds. Chart.js will likewise be vendored via importmap. Fonts and artwork are local, with source licenses recorded.

## Directory boundaries

- `src/Controller/Front`, `Admin`, `Api`: thin HTTP adapters.
- `src/Mock`: immutable, realistic UI fixtures and preview querying during M1.
- `src/Service/<Module>`, `Repository`, `Entity`, `Form`, `Security/Voter`: added after UI review.
- `templates/front`, `admin`: separate layouts using `templates/components`.
- `assets/js/core`, `components`, `admin`: data-attribute progressive enhancement.
- `assets/styles`, `fonts`, `images`, `icons`: locally served static resources.
- `tests/Unit`, `Integration`, `Functional`: automated coverage alongside milestones.
- `tools`: PHP-only local development and checks; `docs`: technical handoff.

Future persistence uses normalized MySQL, attribute-mapped Doctrine entities and migrations. Business rules live in services, database querying in repositories, granular authorization in voters. Twig autoescaping stays enabled.

## M2 backend foundation

The application boots in `dev`, `test` and `prod` with the same code path. Doctrine ORM 3.7 and Doctrine Migrations 4 are wired against `DATABASE_URL` (utf8mb4, underscore naming, attribute mapping from `src/Entity`). Test runs use DAMA's static connection and the `_test` database suffix.

- **Configuration:** `.env` holds safe defaults only; `DATABASE_URL` has no credentials and is overridden by git-ignored local files. `.env.local.dist` documents the expected overrides. `tools/setup-development.php` can generate protected local credentials for the optional Docker MySQL 8.4/Mailpit/Redis stack; this workspace runs the user-provided local MariaDB 12.3 and Mailpit services instead. MySQL 8 remains the production target and must be verified in M16.
- **Security:** entity-backed user provider on a single lazy `main` firewall; `/admin` is protected by the `admin.access` permission and every module action has a granular `#[IsGranted]` check. Authentication and authorization are detailed below.
- **HTTP errors:** `ApiExceptionSubscriber` converts failures below `/api` and `/admin/api` into RFC 9457 `application/problem+json`; HTML errors continue through Twig error templates.
- **Logging:** Monolog channels `app`, `security`, `mail`, `audit`; JSON output and stderr handlers in production.
- **Cache:** `cache.app` is filesystem-backed; `cache.content` is tag-aware with a 600 s default lifetime. `cache.redis` selects Redis only when `REDIS_URL` is non-empty, otherwise a filesystem adapter, so the application runs with or without Redis.
- **Async:** Doctrine transport for `async` (optional Redis DSN override), Doctrine `failed` transport, email messages routed async; tests use in-memory transports.
- **Settings:** `SettingsServiceInterface` with typed getters and a default implementation. M13 replaces it with the cached, database-backed implementation without changing consumers.
- **Quality:** PHPUnit 12 with DAMA rollback, PHPStan level 6, PHP CS Fixer, and a PHP-only tooling guard.


## M4 authentication and authorization

- **Identity:** `App\Entity\User` implements Symfony's user, password and TOTP interfaces. The entity provider loads by email; inactive or soft-deleted users are rejected by `App\Security\UserChecker`.
- **Firewall:** one lazy `main` firewall with form login (`/login`, CSRF, remember-me, 5 attempts/minute throttling), CSRF-protected logout (`POST /logout`), and TOTP two-factor authentication. `access_control` limits `/admin` to `admin.access`; authentication pages are public and `/profile` requires a fully authenticated session.
- **Account flows:** registration is settings-gated (`registration.enabled`, default off), sends a signed verification link, and marks the address confirmed. Password reset requests use expiring selector/token pairs stored in `reset_password_request`; reset links expire after an hour and are throttled to one per minute. Profile updates, password changes and TOTP enrollment/disable actions are CSRF protected.
- **Authorization:** permission codes are data (`Permission` ↔ `Role` ↔ `User`). `PermissionVoter` grants dotted codes such as `article.publish`; `ArticleVoter`, `CommentVoter` and `UserVoter` add own-vs-any and higher-role protections. Super Administrators bypass granular checks. Admin navigation hides modules the current user cannot open.
- **Seeding:** `app:security:sync-permissions` idempotently creates or updates the canonical 31 permissions and five default roles from `PermissionCatalog`. Run it after deployment and after permission changes.
- **Audit:** successful login updates `lastLoginAt` and writes a `user.login` activity entry; logout, registration, verification, password reset and TOTP changes are also recorded. Monolog channel `audit` receives the same events.
- **Test strategy:** real entity users exercise login/logout, inactive accounts, registration + verification, reset, required 2FA and the full enrollment challenge. A role × route matrix asserts 200/403 access for all five default roles.

## M5 core CMS

The article, category and tag administration screens are now server-backed; remaining mock modules are media, comments, pages, menus, advertisements, users/roles and system settings, which are connected in their own milestones.

- **Forms:** Symfony Form types with a shared Tailwind form theme render every CMS field. Validation is server-side; preview-only forms are explicitly marked and never submit.
- **Services:** `ArticleService` owns publishing transitions, revisions, autosave, duplication, soft deletion, restoring and scheduled publication. `TaxonomyService` owns category/tag hierarchy and unique slugs. `ContentProcessor` owns sanitization, heading anchors, reading time and excerpts.
- **Workflow:** `config/packages/workflow.yaml` defines the article state machine. Invalid transitions surface as form errors rather than silent status changes.
- **Async:** scheduled publishing uses Symfony Scheduler + Messenger; the `scheduler_default` transport must be consumed in production.
- **Search:** admin filtering uses indexed columns and `LIKE`; public FULLTEXT search is added in M7.

## M6 media management

- **Storage abstraction:** `MediaStorageInterface` + `FlysystemMediaStorage`; local adapter today, S3-compatible adapter by configuration later. Uploaded files live under `public/uploads/media`, derivatives under its `cache` subdirectory.
- **Upload pipeline:** `MediaUploader` validates size, executable/double extensions, detected MIME and extension/MIME agreement; re-encodes images with GD to strip metadata and records dimensions; random 32-hex filenames and date folders prevent guessing and collisions.
- **Derivatives:** LiipImagine filter sets `thumbnail`, `medium`, `large` and `webp`; URLs resolve through `/media/cache/resolve/{filter}/{key}`.
- **Library:** media index, upload form, metadata editing, filters/pagination, JSON picker API and usage-protected deletion. The old mock media screen is gone.
- **URLs:** the `media_url()` Twig function resolves bundled fixture illustrations through AssetMapper and uploaded files through the storage abstraction.

## M7 public website integration

- **Catalog:** `PublicCatalog` replaces `MockCatalog` for every public route and enforces published/visible/not-deleted rules in repository queries. `MockDataProvider` remains only for unfinished admin preview modules, the styleguide and fixtures.
- **Queries:** article listings, archives and details eager-load author, categories, tags and featured image; pagination uses Doctrine Paginator. FULLTEXT search (with a LIKE fallback) is indexed by migration `Version20261112130000`.
- **Caching:** homepage section IDs are stored in the tag-aware `cache.content` pool with `articles`/`homepage` tags; `ArticleService` invalidates them after editorial changes.
- **Feeds:** `/feed.xml` and category RSS feeds expose only published content.

## M8 comments and engagement

- **Comments:** real threaded comments with pending/approved/rejected/spam states, guest or registered authorship, depth limits, auto-approval for trusted users, author notifications and a real admin moderation queue with voter-based own/any permissions.
- **Spam controls:** honeypot, submit timing, per-IP rate limiting, link cap, blocked words and a pluggable `SpamCheckerInterface`.
- **Engagement:** authenticated JSON like/bookmark toggles backed by unique constraints, a saved-stories page, real share/copy actions and asynchronous de-duplicated view tracking with bot filtering.
- **Front behavior:** the reader JavaScript now calls real endpoints and changes ARIA state/counts; the comment form submits a real Symfony form with reply context.

## M9 SEO

- **Metadata:** `SeoHeadFactory` resolves title/description/canonical/robots from entity SEO, content fallbacks and settings; `partials/seo_head.html.twig` renders OG, Twitter and JSON-LD for every page.
- **Structured data:** Organization, WebSite/SearchAction, BlogPosting, Person and BreadcrumbList graphs.
- **Discovery:** sitemap index plus article/category/tag/author/page sitemaps, all tag-cached and filtered to public/indexable content; dynamic robots.txt.
- **Redirects:** cached public request listener with hit counting, plus automatic 301 creation when an article slug changes.
- **Consent:** GA4 is injected only after explicit optional consent; GSC verification is metadata only.

## M10 newsletter and email

- **Subscriptions:** CSRF/rate-limited opt-in with opaque confirmation/unsubscribe tokens, double opt-in setting, confirmation/welcome emails and List-Unsubscribe headers.
- **Admin:** real subscriber management with status actions and CSV export, plus campaign editor, test sends, history and delivery stats.
- **Delivery:** campaigns are batched through Messenger (50 recipients per message), rendered with inline CSS, and scheduled sends run every minute through the Symfony Scheduler. Failures are recorded per recipient without aborting the batch.

## M11 analytics and reporting

- **Rollups:** raw `ArticleView` rows are aggregated into idempotent `article_daily_stat` values hourly by Symfony Scheduler; raw views are pruned after 30 days. `app:analytics:rollup` provides manual control.
- **Reads:** dashboard and analytics endpoints query daily stats only, using indexed date/group queries; report results are tag-cached for 10 minutes with invalidation from content, comment, engagement, subscription and rollup changes.
- **Dashboard:** real stat cards, 7-day views, category share, recent articles/comments/activity and workflow counts.
- **Reporting:** date-range charts and top-article/author/category/tag tables plus CSV export, gated by `analytics.view`.
- **Measured:** 100k raw views → 39 ms production response / 92 ms load on the local MariaDB benchmark.

## M12 monetization

- **Ads:** real advertisement CRUD, placement/schedule/priority selection, placement-ID caching with invalidation, `render_ad()` and in-article insertion after a configured paragraph.
- **Sponsored/affiliate:** article sponsorship disclosure and automatic `rel="sponsored nofollow noopener"` for affiliate-enabled content.
- **Extensions:** premium article flag plus `AccessPolicyInterface`; payment checkout/webhook contract with a disabled implementation. No payment credentials or external provider are bundled.

## M13 settings, customization and admin completion

- **Settings:** database-backed, cached and encrypted service replaces the preview settings; group-specific permissions and activity logging.
- **SMTP:** `SettingsMailer` selects a saved SMTP/sendmail transport or the environment DSN, with test-email support.
- **Appearance:** CSS variables, theme, branding, footer/social links, configurable homepage section order and article sidebar widgets.
- **Maintenance/custom code:** request listener with admin bypass, restricted custom CSS/JS.
- **Menus:** database-backed nested header/footer menus with drag reorder and public fallbacks.
- **Admin completion:** users, roles, permission matrix, pages, activity and profile are real; no `ContentPreviewController` screens remain in the admin area.

## M14 security and performance

- **Headers/CSP:** per-request nonce, strict script/frame/object policy, HSTS on HTTPS, referrer/permissions/frame headers.
- **Passwords/2FA:** 12-character common-password rejection and encrypted TOTP secrets at rest.
- **Caching:** public GET caching headers with cookie variation; private responses for authenticated/session requests; tag-based invalidation.
- **Query budgets:** `PerformanceTest` enforces bounded eager-loaded public and admin queries.

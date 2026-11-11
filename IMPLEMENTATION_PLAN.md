# Implementation Plan — Professional Blogging CMS
**Stack:** PHP 8.3+ · Symfony · Doctrine · Twig · Tailwind CSS 3.4 (standalone CLI) · Vanilla JS · MySQL 8 · **No Node.js**

---

## 0. How to Use This Plan

1. Put the original requirements in `docs/SPEC.md` and this file in `docs/IMPLEMENTATION_PLAN.md`.
2. Copy **Section 1 (Agent Rules)** into `CLAUDE.md` (Claude Code) and/or `AGENTS.md` (Codex) at the repo root.
3. Run **one milestone per agent session**. Don't ask for "build the whole app" in one go.
4. After each milestone: review the diff, run the verify commands (Section 8), commit, and tag (e.g. `m1.3-done`).
5. Use this prompt for each session:

```
Read CLAUDE.md, docs/SPEC.md, docs/IMPLEMENTATION_PLAN.md and PROJECT_PROGRESS.md.
Implement milestone <ID> only. Follow its Tasks and meet every "Done when" item.
Don't start the next milestone. When finished: run the verify commands,
update PROJECT_PROGRESS.md (status, what was built, known issues), and
summarize the changed files.
```

---

## 1. Agent Rules (copy into CLAUDE.md / AGENTS.md)

```markdown
# Project Rules

## Hard constraints
- NO Node.js tooling of any kind: no node, npm, npx, yarn, pnpm, bun, vite, webpack, Encore.
  If a package's install docs say `npm install`, use AssetMapper's importmap instead
  (`php bin/console importmap:require <pkg>`). It downloads files with PHP; no Node is involved.
- Tailwind is compiled ONLY through `symfonycasts/tailwind-bundle` (standalone CLI binary), pinned to v3.4.x.
  `tailwind.config.js` is a config file read by the standalone binary. It is NOT Node usage. Keep it.
- Frontend JS = vanilla ES modules served through Symfony AssetMapper. No jQuery, no frameworks.
- Follow milestones in order. Never implement backend business logic before M1 is signed off.

## Architecture
- Controllers: thin (request → service → response). No business logic, no queries.
- Services (src/Service/<Module>/): business logic.
- Repositories: all DB queries. QueryBuilder/DQL only, always parameterized.
- Entities: domain models with attributes mapping. Use Doctrine migrations for every schema change.
- Form Types + Validator constraints: all input validation server-side.
- Voters: every protected action is checked with `#[IsGranted('permission.code')]` or `denyAccessUnlessGranted`.
- Twig: presentation only. No logic beyond loops/conditionals/formatting.

## Conventions
- PHP: strict_types, PSR-12, typed properties, readonly where possible, constructor injection.
- Naming: `App\Controller\Admin\ArticleController`, `App\Controller\Front\BlogController`.
- Routes: admin under `/admin`, route names `admin_<module>_<action>`, public `front_<page>`.
- Templates: `templates/front/...`, `templates/admin/...`, components in `templates/components/`.
- Settings never hardcoded: read through `SettingsService`.
- Secrets only in env vars / Symfony secrets vault. Never commit credentials.

## Workflow
- Before editing an existing file, read it. Don't overwrite what you don't understand.
- Write/update tests with each backend module (from M2 onward). Fix failures before finishing.
- Run before finishing any task:
  `composer validate && php bin/console lint:twig templates && php bin/console lint:container
   && vendor/bin/phpstan analyse && vendor/bin/php-cs-fixer fix --dry-run && php bin/phpunit`
- Update PROJECT_PROGRESS.md. Never mark something Completed unless frontend, backend,
  DB, validation and authorization are all done and tested.
```

---

## 2. Stack Lock (decide once, don't re-decide)

**Symfony version rule:** PHP 8.3 → **Symfony 7.4 LTS**. Only use Symfony 8.x if the server runs PHP 8.4+. Agent must check `php -v` in M0 and record the choice.

| Concern | Package / Approach |
|---|---|
| Assets (no Node) | `symfony/asset-mapper` + `symfony/asset` |
| Tailwind | `symfonycasts/tailwind-bundle` with `binary_version: 'v3.4.17'` (or latest 3.4.x) |
| Tailwind plugins | `@tailwindcss/typography`, `@tailwindcss/forms` (bundled in the standalone CLI binary) |
| Twig components | `symfony/ux-twig-component` (PHP-only) + anonymous components |
| Rich-text editor | Vendored via importmap: **TipTap** (ESM) or **Quill 2**. Pick one in M0. Output = HTML |
| HTML sanitizing | `symfony/html-sanitizer` (allow-list config for article content) |
| Charts (admin) | `chart.js` via importmap |
| Icons | Inline SVG sprite (e.g. Heroicons/Lucide SVGs copied into `assets/icons/`) via a Twig `icon` component |
| ORM | `doctrine/orm`, `doctrine/doctrine-migrations-bundle` |
| Timestamps / soft delete | `stof/doctrine-extensions-bundle` (Timestampable, SoftDeleteable, Sluggable, Tree for categories) |
| Publishing workflow | `symfony/workflow` (article state machine) |
| Pagination | `babdev/pagerfanta-bundle` (Doctrine ORM adapter) |
| Auth | `symfony/security-bundle`, `symfonycasts/reset-password-bundle`, `symfonycasts/verify-email-bundle` |
| 2FA | `scheb/2fa-bundle` + `scheb/2fa-totp` |
| Rate limiting | `symfony/rate-limiter` (login throttling, comment/subscribe limits) |
| Files / storage | `league/flysystem-bundle` (local adapter now, S3 adapter later) |
| Images | `liip/imagine-bundle` (thumbnails, resize, WebP) |
| Async / email | `symfony/messenger` (Doctrine transport default, Redis optional), `symfony/mailer` |
| Scheduling | `symfony/scheduler` (scheduled publishing, campaigns, stats rollups) + one cron/systemd worker |
| Cache | `symfony/cache` (filesystem default; Redis via env when available) |
| Testing | `phpunit`, `symfony/browser-kit`, `zenstruck/foundry`, `dama/doctrine-test-bundle` |
| Quality | `phpstan/phpstan` (level 6+), `friendsofphp/php-cs-fixer` |
| Fixtures | `doctrine/doctrine-fixtures-bundle` + Foundry factories |

Allowed with justification only. Everything else needs a note in PROJECT_PROGRESS.md.

---

## 3. Directory Structure

```
assets/
  app.js                  # front entry: imports modules by data attribute
  admin.js                # admin entry
  js/
    core/                 # dom helpers, event bus, fetch wrapper (CSRF header)
    components/           # dropdown.js, modal.js, tabs.js, toast.js, drawer.js,
                          # dropdown.js, confirm.js, toc.js, reading-progress.js,
                          # table-sort.js, form-validate.js, theme-toggle.js
    admin/                # editor.js, media-picker.js, charts.js, slug.js, menu-builder.js
  styles/
    app.css               # @tailwind base/components/utilities + @layer components
    admin.css
  icons/
config/
src/
  Controller/{Front,Admin,Api}/
  Entity/  Repository/  Form/  Security/Voter/
  Service/{Article,Media,Seo,Newsletter,Analytics,Settings,Menu,Comment}/
  MessageHandler/  Message/  Scheduler/
  EventSubscriber/  Twig/{Components,Extension}/
  Mock/                   # M1 only: MockDataProvider (removed/unused after M7)
  Enum/                   # ArticleStatus, CommentStatus, MediaType, ...
  DataFixtures/  Factory/
templates/
  base.html.twig
  front/{layout,home,blog,article,author,category,tag,page,search}/
  admin/{layout,dashboard,article,category,tag,media,comment,user,role,
         newsletter,analytics,ad,page,menu,appearance,seo,settings,activity,profile}/
  components/             # Button, Input, Select, Card, Badge, Alert, Modal, Table,
                          # Pagination, EmptyState, Avatar, ArticleCard, StatCard, Icon...
  partials/
  bundles/TwigBundle/Exception/   # error404.html.twig, error500.html.twig, error.html.twig
  emails/
tests/{Unit,Integration,Functional}/
docs/
PROJECT_PROGRESS.md
```

---

## 4. Key Conventions (important for a smooth UI → backend handoff)

**4.1 Mock-data contract (the most important rule in M1).**
All M1 screens get data from `App\Mock\MockDataProvider`, which returns **plain PHP objects/arrays shaped exactly like the future entities** (same property names: `article.title`, `article.slug`, `article.author.displayName`, `article.publishedAt`, `article.featuredImage.url`, `article.readingTime`, etc.). Write the field list in `docs/data-contract.md` in M1.1. In M7, controllers replace the provider with repositories and **templates stay unchanged**.

**4.2 Routes are real from M1.**
Create the final route paths and names in M1 (controllers render mock data). Every nav link must resolve. Admin routes are open in M1 (behind a `APP_UI_PREVIEW=1` env flag check). Security locks them in M4.

**4.3 Vanilla JS pattern.**
Progressive enhancement with data attributes: `<div data-component="dropdown">`. `app.js` scans the DOM and lazy-imports matching modules. No inline `onclick`. All `fetch` calls go through `core/http.js`, which adds the CSRF token header.

**4.4 Permissions are strings, not roles.**
Codes look like `article.create`, `article.edit.own`, `article.edit.any`, `article.publish`, `comment.moderate`, `settings.smtp.manage`. Roles group permissions in the DB. Voters check permission codes. Templates use `is_granted('article.publish')`.

**4.5 Article state machine (symfony/workflow).**
`draft → pending_review → scheduled | published → unpublished`, plus `draft → published` for users with `article.publish`. Transitions are guarded by permissions.

---

## 5. Milestones

Each milestone = one agent session (split further if the agent struggles). Status values: Pending / In Progress / Completed / Blocked.

---

### M0 — Inspection, Setup & Planning
**Tasks**
- Inspect the directory. Detect an existing Symfony app (`composer.json`, `symfony.lock`). If one exists, document it and don't overwrite anything.
- Check `php -v`, extensions (`intl`, `pdo_mysql`, `gd` or `imagick`, `sodium`, `zip`), and the MySQL version. Pick the Symfony version (Section 2 rule).
- If new: `composer create-project symfony/skeleton:"7.4.*"` then `composer require webapp` **and remove Encore if added**. Install AssetMapper and the Tailwind bundle. `php bin/console tailwind:init`. Pin 3.4.x in config.
- Install dev quality tools (PHPStan, CS Fixer, PHPUnit). Add a `Makefile` or `composer` scripts: `dev`, `css`, `css-watch`, `test`, `lint`, `qa`.
- Choose the editor library (TipTap vs Quill). Record the choice.
- Create `PROJECT_PROGRESS.md` (table from the spec), `docs/architecture.md`, `docs/modules.md`, `docs/roadmap.md`.

**Done when**
- `symfony serve` (or `php -S`) shows the welcome page; `php bin/console tailwind:build` works; **`which node` is not needed anywhere**.
- `grep -ri "npm\|webpack\|vite\|encore" --exclude-dir=vendor .` returns nothing relevant.
- Progress doc and roadmap committed.

---

### M1.1 — Design System & Component Library
**Tasks**
- Design tokens in `tailwind.config.js`: brand primary/secondary, neutral scale, surface/background, semantic colors (success/warning/danger/info), font families (one serif or display for headings plus one sans for UI; self-hosted in `assets/fonts/`), type scale, spacing, radius, shadows, breakpoints. Dark mode `class` strategy.
- Base styles and `@layer components` for: buttons (primary/secondary/ghost/danger, sizes, loading, disabled), inputs, textarea, select, checkbox/radio/toggle, field with label/help/error, cards, tables, badges, alerts, toasts, modals, drawers, dropdowns, tabs, breadcrumbs, pagination, avatars, skeleton loaders, empty states, tooltips.
- Twig components for each in `templates/components/`.
- JS modules: dropdown, modal (focus trap, ESC, aria), drawer, tabs, toast, confirm dialog, theme toggle.
- **`/_styleguide` route** (dev only) that renders every component in every state.
- `docs/data-contract.md` (see 4.1) and `MockDataProvider` with realistic data: ~30 articles, 8 categories (some nested), 25 tags, 6 authors, comments with replies, media items, users, roles.

**Done when** the styleguide shows all components in light and dark mode, all states (default/hover/focus/disabled/loading/error), works with the keyboard, and has no console errors.

---

### M1.2 — Public Layout & Homepage
**Tasks**
- `front/layout`: header (logo, nav with mega/dropdown, search trigger, theme toggle, subscribe CTA), mobile drawer nav, footer (columns, social, newsletter mini-form, legal links).
- Homepage sections: hero/featured, latest, popular, trending, category sections, recommended, author highlights, newsletter section.
- Reusable `ArticleCard` variants: large, horizontal, compact, minimal.

**Done when** the homepage is responsive at 360 / 768 / 1024 / 1440 px, all links resolve to routes, and images use `loading="lazy"` with width/height set.

---

### M1.3 — Blog Listing & Article Details
**Tasks**
- Listing: grid/list toggle, category and tag filters, search box, sort (latest/popular/oldest), pagination, empty state, "no results" state. Filters work through query string with mock data.
- Article details: title/subtitle, featured image with caption, author meta, date, reading time, `prose` content (all elements styled: headings, lists, quotes, code, tables, images, embeds), sticky TOC (generated by JS from headings), reading progress bar, share buttons (copy link with toast), tags, author bio, related articles, prev/next, like/bookmark buttons (visual), comment section (threaded, reply form, validation states, "comments closed" state).

**Done when** both pages work with mock data on all breakpoints, and TOC, progress bar and filters function.

---

### M1.4 — Remaining Public Pages & Errors
About, Contact (form with client validation states), Privacy, Terms, Cookie Policy, cookie consent banner, Authors list, Author profile, Categories, Category details, Tags, Tag details, Search results, 404, 500 (Twig error templates, previewable at `/_error/404` in dev), maintenance page.

**Done when** every public page from the spec exists and is linked from nav/footer. No lorem-ipsum headings. Realistic copy.

---

### M1.5 — Admin Shell & Dashboard
**Tasks**
- Separate `admin/layout`: collapsible sidebar (grouped nav, active state, icons), mobile off-canvas sidebar, top bar (search, notifications dropdown, quick-create, profile menu), breadcrumbs, page header with actions, flash/toast area.
- Dashboard: stat cards, charts (Chart.js: views over time, top categories), recent articles, recent comments, activity feed, quick actions.
- Shared admin patterns: data table (sortable headers, row selection, bulk actions bar, filters, per-page, pagination), filter bar, form layout (main column plus settings sidebar), delete confirmation modal, "no permission" page.

**Done when** the shell works on mobile/tablet/desktop and the data table pattern is reusable through a Twig component.

---

### M1.6 — Article Management & Content Editor UI
**Tasks**
- Article list (tabs: All / Drafts / Pending / Scheduled / Published / Trash; filters; bulk actions).
- Create/edit screen: title, slug (auto-generated, editable), subtitle, excerpt, **rich-text editor** (toolbar: headings, bold/italic, lists, quote, link, image, table, code block, embed video, separator, alignment, undo/redo), right sidebar panels (status/visibility/schedule date picker, author, categories tree checklist, tags input with autocomplete, featured image picker, comments toggle), SEO panel (title/description with char counters, focus keyword, canonical, OG image, noindex, Google snippet preview), autosave indicator (visual).
- Preview page, revision history list plus diff/compare view (mock).
- Media picker modal (shared with M1.7 media library).

**Done when** the editor works fully client-side (content is editable, all toolbar actions work), and it is usable on tablet and degrades gracefully on mobile.

---

### M1.7a — Admin Pages: Content Group
Categories (tree list plus form with image and SEO), Tags, Media library (grid/list, upload dropzone with progress UI, filters, detail drawer with alt/caption), Comment moderation (tabs by status, inline approve/reject/spam, reply), Static pages (list plus editor reuse), Navigation menus (drag-and-drop nested builder in vanilla JS, menu locations), Advertisements (list, placement, schedule).

### M1.7b — Admin Pages: System Group
Users (list, create/edit, activate/deactivate, role assignment), Roles, Permission matrix (role × permission checkbox grid), Newsletter (subscribers table, export button, campaigns list, campaign editor, schedule, history), Analytics (date-range filter, charts, top tables), Appearance (colors, header/footer, homepage sections ordering, light/dark default, social links), SEO settings (defaults, robots.txt editor, sitemap toggles, GA/GSC IDs, redirects table), General settings, SMTP settings (with "send test email" button), Activity logs, Admin profile (info, password, 2FA setup screen with QR placeholder).

**Done when (both)** every admin page in the spec exists with mock data, forms show validation states, and every page has loading/empty/error states.

---

### M1.8 — UI States, Responsive & Accessibility Audit (UI sign-off gate)
**Tasks**
- Go through the spec's §1.4 and §1.7 checklists page by page. Create `docs/ui-checklist.md` with one row per page × state.
- Check: loading skeletons, empty, error, success toasts, validation errors, disabled controls, confirmations, no search results, no permission, responsive navigation.
- Accessibility: semantic landmarks, labels, focus visible, color contrast AA, `aria-*` on interactive components, keyboard navigation for menus/modals/tabs.
- Remove duplicated markup into components.
- Build a "UI route crawler" functional test that requests every route and asserts HTTP 200 and no Twig errors.

**Done when** the checklist is all green, the crawler test passes, and PROJECT_PROGRESS marks Phase 1 Completed. **Stop here for human review before M2.**

---

### M2 — Backend Foundation
**Tasks**
- Env config (`.env`, `.env.local.dist`, `.env.test`), MySQL `DATABASE_URL`, Doctrine config (utf8mb4, `server_version`), migrations config.
- Security skeleton (user provider placeholder, firewalls `main` and `admin`), password hasher `auto`.
- Exception handling: custom error pages, JSON errors for `/api`, logging channels (`app`, `security`, `mail`, `audit`) via Monolog.
- Cache pools (`cache.app` filesystem; `cache.redis` conditional on `REDIS_URL`).
- Messenger transports (`async` = Doctrine by default; Redis if env set; `failed` transport).
- Base service skeletons per module plus `SettingsService` interface (DB-backed comes in M13; uses defaults until then).
- PHPUnit and test DB setup (dama/doctrine-test-bundle), CI script (`composer qa`).

**Done when** the app boots in dev/test/prod modes, `composer qa` passes, and the app works with **and** without Redis.

---

### M3 — Database Design & Implementation
**Tasks**
- Entities (attributes mapping), relations, indexes, unique constraints, cascade/`onDelete` policies, Timestampable on all, SoftDeleteable on Article, Comment, Page, Media, User.
- ER diagram in `docs/database.md` (Mermaid).
- One migration per logical group. Must run cleanly on an empty MySQL 8 DB and be reversible.
- Foundry factories plus fixtures matching the M1 mock data (so the UI looks identical after integration).

**Entity notes** (keep it normalized, avoid extra complexity)
- `User` (email unique, password, displayName, slug, bio, avatar→Media, isActive, isVerified, lastLoginAt, totpSecret nullable) ↔ `Role` (M:N) ↔ `Permission` (M:N, `code` unique).
- `Article` (title, subtitle, slug unique, excerpt, content HTML (sanitized), status enum, publishedAt, scheduledAt, author→User, featuredImage→Media, allowComments, readingTime, viewCount (denormalized), likeCount, seo fields embedded as `SeoMetadata` Embeddable). Indexes: (status, publishedAt), slug, author.
- Article↔Category and Article↔Tag: plain Doctrine M:N join tables (`article_category`, `article_tag`). No separate entity classes unless extra columns are needed.
- `ArticleRevision` (article, content snapshot, title, createdBy, createdAt, isAutosave).
- `Category` (Tree: parent, lft/rgt or materialized path, slug unique, description, image, SeoMetadata). `Tag` (slug unique, description, SeoMetadata).
- `Comment` (article, parent self-ref, user nullable, guestName/guestEmail, content, status enum, ip hash, userAgent, reportCount).
- `ArticleLike`, `ArticleBookmark` (unique(article,user)). `ArticleView` (article, viewedAt, visitorHash, referrer). Plus `ArticleDailyStat` (article, date, views) rollup table for fast analytics.
- `Media` (filename random, originalName, mimeType, size, width, height, alt, caption, storage key, uploadedBy, variants JSON).
- `Page`, `Menu` (location unique), `MenuItem` (tree, type enum: custom/page/category/article, target, position).
- `NewsletterSubscriber` (email unique, status, token, confirmedAt, unsubscribedAt), `NewsletterCampaign` (subject, html, status, scheduledAt, sentAt, stats).
- `Advertisement` (name, placement enum, type: html/image/adsense, content, startsAt, endsAt, isActive, priority).
- `Setting` (key unique, value JSON, group, isSensitive). `Redirect` (sourcePath unique, targetUrl, statusCode, hits). `ActivityLog` (user, action, subjectType, subjectId, context JSON, ip, createdAt; index on createdAt).

**Done when** `doctrine:migrations:migrate` plus `doctrine:fixtures:load` succeed on a fresh DB, `doctrine:schema:validate` is green, and repository tests pass.

---

### M4 — Authentication & Authorization
**Tasks**
- Login form (CSRF, remember-me, login throttling 5/min), logout, configurable registration (setting), forgot/reset password (reset-password-bundle, expiring hashed tokens), email verification, profile, password change, activate/deactivate (inactive users blocked through a `UserChecker`), lastLoginAt subscriber.
- Seed permissions and the 5 default roles (Section 6) through a migration or a `app:security:sync-permissions` command (idempotent).
- `PermissionVoter` (generic code check) plus resource voters: `ArticleVoter` (own vs any), `CommentVoter`, `UserVoter` (can't edit higher roles; can't demote the last Super Admin).
- Lock `/admin` (`access_control` requires `admin.access`). Remove the `APP_UI_PREVIEW` bypass.
- Admin sidebar/actions hidden according to permissions (UI) **and** enforced in controllers (backend).
- 2FA (TOTP) optional per user, enforceable for admins via setting.
- Activity logging for auth events.

**Done when** functional tests cover login/logout/reset/verify, and a permission matrix test checks that each role can and can't reach each admin route (expects 200/403).

---

### M5 — Core CMS
**Tasks**
- `ArticleService`: create, update, delete (soft), restore, duplicate, workflow transitions (draft/submit/publish/schedule/unpublish), slug uniqueness (Sluggable plus a manual edit check), excerpt auto-generation, reading time, author assignment (permission-gated), categories/tags assignment.
- Content pipeline: editor HTML → `HtmlSanitizer` (allow-list: headings, p, lists, a[href,rel,target], img[src,alt,width,height], figure/figcaption, table elements, pre/code, blockquote, hr, iframe only for YouTube/Vimeo allow-listed hosts) → stored. Heading IDs are added server-side for the TOC.
- Autosave endpoint (`POST /admin/api/articles/{id}/autosave`, JSON, CSRF, throttled) → autosave revision (keep the latest N).
- Revisions: created on each manual save; compare; restore.
- Scheduled publishing through `symfony/scheduler` (runs every minute: publish due articles).
- Categories (hierarchical, image, SEO, prevent deleting a category that has children or reassign them), Tags (CRUD, SEO, tag autocomplete endpoint, create-on-the-fly with permission).
- Wire the M1.6/M1.7a screens to real forms (Symfony Form types rendered with the M1 components through a custom form theme `form/tailwind_theme.html.twig`).

**Done when** tests cover CRUD, workflow guards, slug uniqueness, sanitizer (XSS payloads stripped), scheduling, and own-vs-any authorization.

---

### M6 — Media Management
**Tasks**
- `MediaUploader` service: validate extension **and** detected MIME (finfo) against an allow-list (jpg, png, webp, gif, svg excluded unless sanitized, pdf, docx…), size limit from settings, random filename (`bin2hex(random_bytes(16))`), date-based subfolder, store through Flysystem `media.storage`. Block executables/double extensions. Uploads dir has no PHP execution (web-server config in docs).
- Image processing with LiipImagine: thumbnail/medium/large/WebP variants, strip EXIF, record dimensions.
- Library endpoints: list/search/filter (type, date, uploader), paginate, update alt/caption, delete (blocked if in use, or with a warning), media picker JSON API for the editor and featured image.
- Storage abstraction documented for switching to S3 (`league/flysystem-aws-s3-v3`) via config only.

**Done when** upload tests cover valid image/document, wrong MIME disguised with a valid extension, oversize, and `.php`/`.phtml` rejected; variants are generated.

---

### M7 — Public Website Integration
**Tasks**
- Swap `MockDataProvider` for repositories/query services in front controllers. Templates should need minimal or no changes (thanks to the data contract).
- `PublishedArticleQuery` (always filters `status=published AND publishedAt <= now`, not soft-deleted) is used everywhere public.
- Homepage sections (latest, popular = views in the last 30 days, trending = view velocity over the last 7 days, category blocks, recommended = shared tags/categories), cached with tag-aware invalidation on article save.
- Listing with filters/search/sort/pagination (MySQL FULLTEXT index on title/excerpt/content for search), category/tag/author pages, related articles, prev/next, RSS feed (`/feed.xml`, plus per category), reading progress/TOC from real content.
- Eager-load relations (fetch joins / `addSelect`) to avoid N+1.
- Delete `src/Mock/` (or keep it dev-only for the styleguide).

**Done when** functional tests confirm draft/scheduled/deleted articles return 404 publicly, the UI matches M1 visually, and the Symfony profiler shows no N+1 on listing/details.

---

### M8 — Comments & Engagement
**Tasks**
- Comment submit (auth or anonymous per setting), replies (configurable max depth), moderation queue (auto-approve rules: trusted users), approve/reject/spam/edit/delete, report comment, per-article disable.
- Spam protection with no external dependency: honeypot field, minimum time-to-submit, rate limiter per IP, link-count limit, blocked words setting. Optional Akismet/Turnstile adapter through an interface.
- Likes/bookmarks (unique constraint plus idempotent endpoints, JSON, CSRF), "Saved for later" page for logged-in readers, view counting (de-duplicated by visitor hash per 24h, bot UA filter, async through Messenger), share buttons (real share URLs).
- Notifications: email author/moderators on a new pending comment (async).

**Done when** tests cover duplicate like prevention, honeypot/rate-limit rejection, disabled comments, and moderation permissions.

---

### M9 — SEO
**Tasks**
- `SeoMetadataResolver`: per-page title/description/canonical/robots with fallbacks (entity SEO → auto-generated from excerpt → site defaults). Rendered by one `partials/seo_head.html.twig`.
- Open Graph plus Twitter Card tags, JSON-LD: Article/BlogPosting, Organization, Person (author), BreadcrumbList, WebSite with SearchAction.
- `/sitemap.xml` (index plus sub-sitemaps: articles, categories, tags, authors, pages; only public and indexable content; cached), dynamic `/robots.txt` from settings.
- Redirect manager: kernel request listener (cached lookup) plus auto-create a 301 when a published article's slug changes.
- GA4 / GSC verification injected from settings (consent-aware with the cookie banner).

**Done when** tests confirm the sitemap excludes drafts/noindex/private items, JSON-LD validates (structure test), and the redirect works and counts hits.

---

### M10 — Newsletter & Email
**Tasks**
- Subscribe form (rate-limited), double opt-in toggle, confirmation and unsubscribe tokens (signed URLs), `List-Unsubscribe` header.
- Admin: subscriber list/search/activate/deactivate, CSV export (streamed), campaigns (Twig HTML templates with inky/inline-CSS through `twig/cssinliner-extra`), test send, schedule, send in batches through Messenger, history plus basic stats (sent/failed).
- Transactional emails (verify, reset, comment notifications) share one base email layout.

**Done when** tests use `MailerAssertionsTrait` for opt-in/unsubscribe flows, and campaign batching works with the async transport in test.

---

### M11 — Analytics & Reporting
**Tasks**
- Dashboard stat cards (counts per spec) and content analytics (most viewed, popular categories/tags, author performance, engagement = likes+comments+bookmarks per view, daily/monthly views).
- Reads from the `ArticleDailyStat` rollup (scheduler job aggregates `ArticleView` hourly/nightly and prunes raw views after N days), not raw tables.
- Date-range filter, cached results (TTL ~10 min plus invalidation), CSV export.
- External analytics: GA4 snippet only (from M9). Note in docs.

**Done when** the dashboard loads in under ~200 ms with 100k fixture views (measure it), and analytics permissions are enforced.

---

### M12 — Monetization (extensible, core only)
**Tasks**
- **Implement:** ad management (placements: header, in-article after paragraph N, sidebar, footer; types: image banner, HTML, AdSense snippet; scheduling; priority; enable toggle), Twig function `render_ad('placement')`, sponsored-article flag plus disclosure label, affiliate link `rel="sponsored nofollow"` option in the editor.
- **Architecture only (interfaces plus disabled module stubs, documented):** premium articles (`isPremium` plus an `AccessPolicy` interface), membership plans, subscriptions, donations (`PaymentGatewayInterface`, configure through env/settings, no hardcoded keys).

**Done when** ads render by placement/schedule and are cached, and the extension points are documented in `docs/monetization.md`.

---

### M13 — Settings & Customization
**Tasks**
- `SettingsService`: typed getters, defaults, cached, grouped (general, appearance, seo, comments, registration, newsletter, media, mail, maintenance). Sensitive values (SMTP password) encrypted with sodium using a key from env, never shown after saving.
- SMTP: build the mailer transport from settings if present, otherwise fall back to `MAILER_DSN` env. "Send test email" action.
- Appearance: theme colors through CSS variables (Tailwind colors reference `rgb(var(--color-primary) / <alpha-value>)` so it can change at runtime without a rebuild), logo/favicon (Media), header/footer options, homepage section order/visibility, sidebar widgets, default light/dark, social links.
- Menu builder: CRUD, nested items, reorder (persist positions through JSON endpoint), locations (header, footer, …), link types.
- Maintenance mode (request listener; admins bypass), custom CSS/JS (**restricted to `settings.code.manage` = Super Admin**, warning in UI).
- All settings changes recorded in ActivityLog.

**Done when** changing settings in admin reflects on the site without a deploy, and sensitive groups are inaccessible to Editor/Author (403 tests).

---

### M14 — Security & Performance Hardening
**Security checklist:** CSRF on every state-changing form/endpoint · Twig autoescape on (no `|raw` except sanitized content) · CSP and security headers (`nelmio/security-bundle` allowed, or a custom subscriber) · secure/HttpOnly/SameSite cookies · session fixation protection · login throttling · password policy (`PasswordStrength`, `NotCompromisedPassword`) · authorization review (grep every controller action for an access check) · upload hardening re-check · token expiry · activity log coverage · 2FA flow re-test · `composer audit` clean.

**Performance checklist:** index review with `EXPLAIN` on top queries · N+1 sweep with the profiler · Doctrine result/query cache for hot reads · HTTP caching (Cache-Control/ETag for public pages, ESI or fragment caching for dynamic blocks like comments count) · responsive images (`srcset` from LiipImagine variants) · lazy loading · minified CSS (`tailwind:build --minify`) · `asset-map:compile` with versioned filenames · gzip/brotli and long-cache headers in server configs · Redis optional (verify both modes) · OPcache/preload settings documented.

**Done when** the security checklist is ticked in `docs/security.md`, and Lighthouse (run manually in a browser) is ≥ 90 for performance, accessibility, best practices and SEO on home/listing/article.

---

### M15 — Testing & QA Consolidation
(Tests are written in every milestone from M2. This milestone fills gaps.)
- Coverage report. Target ≥ 70% on `src/Service` and `src/Security`.
- Functional suites from the spec: publishing workflow, scheduled publishing, search/filter/pagination, comments, newsletter, menus, permissions matrix.
- UI checks without Node: route crawler (all routes 200/302/403 as expected), HTML structure assertions (`DomCrawler`) for empty/error states, and optionally `symfony/panther` (uses ChromeDriver, no Node) for modal/dropdown/mobile-nav smoke tests.
- Fix all failures. Run PHPStan at the max level the codebase allows.

**Done when** `composer qa` is green from a fresh clone plus a fresh DB.

---

### M16 — Deployment & Documentation
**Tasks**
- `README.md`: features, requirements, quick start, architecture overview, modules, roles/permissions.
- `docs/`: installation (Ubuntu/Debian), env config, DB setup and migrations, Tailwind standalone CLI (`tailwind:build --minify`), `asset-map:compile`, Nginx and Apache vhost samples (deny PHP execution in `/uploads`), PHP-FPM tuning, systemd units for `messenger:consume async scheduler_default`, cron fallback, file permissions, backup/restore (`mysqldump` plus uploads), cache management, upgrade procedure, database schema (Mermaid ERD).
- `deploy.sh` (or Deployer recipe in PHP): `composer install --no-dev -o` → migrate → `tailwind:build --minify` → `asset-map:compile` → `cache:clear` → restart workers.
- Optional: `compose.yaml` for local MySQL/Mailpit/Redis (Docker, no Node).

**Done when** a clean Ubuntu VM can be deployed by following the docs only, with no Node installed.

---

## 6. Default Role → Permission Matrix

| Permission | Super Admin | Admin | Editor | Author | Contributor |
|---|:-:|:-:|:-:|:-:|:-:|
| admin.access | ✓ | ✓ | ✓ | ✓ | ✓ |
| article.create | ✓ | ✓ | ✓ | ✓ | ✓ |
| article.edit.own | ✓ | ✓ | ✓ | ✓ | drafts only |
| article.edit.any | ✓ | ✓ | ✓ | | |
| article.delete.own | ✓ | ✓ | ✓ | ✓ | drafts only |
| article.delete.any | ✓ | ✓ | ✓ | | |
| article.submit | ✓ | ✓ | ✓ | ✓ | ✓ |
| article.publish / schedule | ✓ | ✓ | ✓ | | |
| article.review | ✓ | ✓ | ✓ | | |
| category.manage / tag.manage | ✓ | ✓ | ✓ | | |
| media.upload | ✓ | ✓ | ✓ | ✓ | ✓ |
| media.manage.any | ✓ | ✓ | ✓ | | |
| comment.moderate | ✓ | ✓ | ✓ | own articles | |
| user.manage | ✓ | ✓ (not admins) | | | |
| role.manage / permission.manage | ✓ | | | | |
| analytics.view | ✓ | ✓ | ✓ | own stats | |
| newsletter.manage | ✓ | ✓ | | | |
| page.manage / menu.manage | ✓ | ✓ | | | |
| ad.manage | ✓ | ✓ | | | |
| settings.general / appearance / seo | ✓ | ✓ | | | |
| settings.smtp / settings.system | ✓ | | | | |
| settings.code (custom CSS/JS) | ✓ | | | | |
| activity_log.view | ✓ | ✓ | | | |
| profile.manage.own | ✓ | ✓ | ✓ | ✓ | ✓ |

---

## 7. Updated PROJECT_PROGRESS.md Template

```markdown
| Phase | Milestone | Module | Status | Notes |
|---|---|---|---|---|
| 0 | M0 | Project Inspection & Setup | Pending | |
| 1 | M1.1 | Design System & Components | Pending | |
| 1 | M1.2 | Public Layout & Homepage | Pending | |
| 1 | M1.3 | Blog Listing & Details | Pending | |
| 1 | M1.4 | Other Public Pages & Errors | Pending | |
| 1 | M1.5 | Admin Shell & Dashboard | Pending | |
| 1 | M1.6 | Article Mgmt & Editor UI | Pending | |
| 1 | M1.7a | Admin Content Pages | Pending | |
| 1 | M1.7b | Admin System Pages | Pending | |
| 1 | M1.8 | UI Audit & Sign-off | Pending | |
| 2 | M2 | Backend Architecture | Pending | |
| 3 | M3 | Database | Pending | |
| 4 | M4 | Authentication & Authorization | Pending | |
| 5 | M5 | Core CMS | Pending | |
| 6 | M6 | Media Management | Pending | |
| 7 | M7 | Public Website Integration | Pending | |
| 8 | M8 | Comments & Engagement | Pending | |
| 9 | M9 | SEO | Pending | |
| 10 | M10 | Newsletter | Pending | |
| 11 | M11 | Analytics | Pending | |
| 12 | M12 | Monetization | Pending | |
| 13 | M13 | Website Settings | Pending | |
| 14 | M14 | Security & Performance | Pending | |
| 15 | M15 | Testing | Pending | |
| 16 | M16 | Deployment & Documentation | Pending | |

## Log
### <date> — <milestone>
- Implemented:
- Tests:
- Known issues:
- Decisions:
```

---

## 8. Verify Commands (run at the end of every milestone)

```bash
composer validate --strict
php bin/console lint:container
php bin/console lint:twig templates
php bin/console lint:yaml config
php bin/console doctrine:schema:validate        # from M3
php bin/console tailwind:build --minify
php bin/console debug:router                    # all expected routes exist
vendor/bin/phpstan analyse
vendor/bin/php-cs-fixer fix --dry-run --diff
php bin/phpunit
composer audit
# Node check: must print nothing project-related
grep -rinE "npm|npx|yarn|pnpm|webpack|vite|encore" --exclude-dir={vendor,var,.git} .
```

---

## 9. Known Pitfalls to Watch For

1. **Agents defaulting to npm.** Tailwind plugins, editor libraries and Chart.js all have npm-first docs. Force `importmap:require` and the standalone CLI.
2. **Tailwind classes built dynamically in Twig** (e.g. `'bg-' ~ color`) get purged. Use full class names or a safelist.
3. **M1 drift from future entities.** Enforce `docs/data-contract.md`, or M7 becomes a rewrite.
4. **Fake "Completed" status.** Require test evidence in the progress log for each milestone.
5. **`|raw` in Twig.** Only allowed on sanitized article/page content. Grep for it in M14.
6. **Runtime theme colors.** Use CSS-variable-based Tailwind colors (M13) so admins can change colors without recompiling CSS.
7. **Scheduled tasks need a worker.** Scheduled publishing/campaigns do nothing unless `messenger:consume scheduler_default` runs. Document it and check it in deployment.
8. **Huge milestones.** If an agent session runs out of context, split along the task bullets and keep each "Done when" intact.

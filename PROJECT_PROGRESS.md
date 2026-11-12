# Project progress

Completed UI milestones mean **mock-data interfaces only**, not completed backend features. Phase 1 must be reviewed before persistence or business logic starts.

| Phase | Milestone | Module | Status | Notes |
|---|---|---|---|---|
| 0 | M0 | Project Inspection & Setup | Completed | Symfony 8.1; standalone CSS build and full QA verified |
| 1 | M1.1 | Design System & Components | Completed | Mock UI only; local fonts/artwork, keyboard and responsive checks |
| 1 | M1.2 | Public Layout & Homepage | Completed | Mock UI; responsive homepage, working navigation and all card variants |
| 1 | M1.3 | Blog Listing & Details | Completed | Mock-only discovery and article reading interactions verified |
| 1 | M1.4 | Other Public Pages & Errors | Completed | Mock public archives, search, policies, contact, consent and branded errors verified |
| 1 | M1.5 | Admin Shell & Dashboard | Completed | Mock admin shell, accessible charts and reusable interactive table verified |
| 1 | M1.6 | Article Management & Editor UI | Pending | |
| 1 | M1.7a | Admin Content Pages | Pending | |
| 1 | M1.7b | Admin System Pages | Pending | |
| 1 | M1.8 | UI Audit & Sign-off | Pending | Human review gate before M2 |
| 2 | M2 | Backend Architecture | Pending | Requires MySQL 8 verification |
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

### 2026-11-12 — M0 inspection
- Preserved both original planning files; inspected the clean initial repository.
- Chose Symfony 8.1 on PHP 8.5.12 (minimum PHP 8.4), Tailwind 3.4.17, AssetMapper and Quill 2.
- Installed explicit UI packages rather than `webapp` to avoid unrelated scaffolding; backend packages remain deferred.
- Known prerequisite: installed MariaDB client does not prove a MySQL 8 server is available. No database work has started.
- Verification: `composer qa` passed (container/Twig/YAML lint, Tailwind build, PHPStan level 6, CS Fixer, PHPUnit: 1 test / 3 assertions, Composer audit, tooling check). HTTP welcome page returned 200 through PHP's server.
- Tailwind init requires interactive input; supplied its explicit no-managed-binary / version-3 answers via stdin, then pinned 3.4.17. The bundled Browserslist age warning is harmless; its suggested package-manager command was not run.
- Port 8000 was already occupied; the verification server uses loopback port 8088 without touching the existing process. Importmap CDN polyfills are disabled; the supported browser baseline has native import maps.

### 2026-11-12 — M1.1 design system
- Implemented runtime color tokens, serif/sans variable fonts with bundled licenses, light/dark palettes and reusable Twig primitives (controls, fields, cards, tables, badges, alerts, overlays, navigation, skeletons and empty states).
- Added lazy-loaded vanilla enhancement, native modal focus containment/Escape, keyboard dropdowns/tabs, persistent theme preference, dismissible live notifications and preview validation/confirmations.
- Documented the data contract; fixtures provide 36 articles (30 public), 8 categories, 25 tags, 6 authors/users, threaded comments, 6 original local illustrations and 5 roles. No business persistence has been introduced.
- Tests: QA passed, fixture contract assertions and HTTP component-state checks; production styleguide access is explicitly tested as 404.
- Browser checks: light/dark mode; modal opening/Escape; keyboard arrow-tab selection; no page overflow at 360/768/1024/1440; zero console errors/warnings. Reviewed the full styleguide screenshot.
- Known issues: broader page-level accessibility and all state matrices remain the M1.8 audit, not a completed claim for the whole UI. Composer audit briefly used its local advisory cache after a network timeout; rerun before deployment.

### 2026-11-12 — M1.2 public shell and homepage
- Built sticky public navigation, category dropdown, mobile drawer, search dialog, footer and validated preview-only newsletter forms.
- Homepage includes featured/latest/popular/trending, taxonomy sections, recommended reads, author highlights and newsletter. Added four reusable article-card variants and locally served, dimensioned lazy images.
- Established read-only preview routes for linked journal/article/legal destinations so there are no broken homepage links; advanced listing/detail and static-page work remain M1.3/M1.4.
- Verification: `composer qa` green (5 tests / 304 assertions), including an HTTP crawl of every internal homepage link, image attributes and mock fixture contracts. Composer audit fetched successfully on the final run.
- Browser: inspected the complete homepage; fixed an initial 360px header overflow with 44px icon buttons. Retested 360/768/1024/1440 with no overflow or broken images, mobile drawer/Escape and zero console messages.
- No email delivery, database content, tracking, reader-account persistence or CMS business logic is claimed.

### 2026-11-12 — M1.3 discovery and reading
- Listing supports combined search/category/tag/author query filters, newest/popular/oldest sorting, bounded pagination preserving filters, grid/list enhancement and distinct no-match/empty-collection states.
- Article view includes semantic rich content samples (headings, lists, quote, code, table, illustration and optional embed), generated sticky TOC, scroll progress, copy/share actions, local preview like/bookmark toggles, tags, biography, related and previous/next stories.
- Approved threaded mock comments support reply context, client validation and safely text-rendered local preview replies; closed-comment articles show a distinct state. Draft/scheduled/unpublished mock articles return 404 publicly.
- Browser checks: listing and article at 360/768/1024/1440 with no overflow; TOC generates five entries; progress updates while scrolling; like state and an actual local reply work; zero console messages.
- Handoff note: the structured fixture content include intentionally avoids raw HTML. In M7, replace that one include with the **M5-sanitized** entity content output. No unsafe provider HTML is rendered.
- Verification: full `composer qa` passes with 17 tests / 344 assertions, including tag/combined filters, oldest sorting, preserved pagination filters, public visibility and comment state coverage.

### 2026-11-12 — M1.4 public pages and errors
- Added contributor, category and topic indexes and linked detail archives, including an empty child-category state. Search has its own route, preserves filters/pagination and safely escapes query text.
- Expanded About, Contact, Privacy, Terms and Cookie Policy with realistic preview-specific copy. Contact supports required/email/minimum-length validation, inline errors, first-invalid focus and an explicit local-only success notification.
- Added local consent choices, persistent dismissal and preferences controls on the policy page/footer. Optional analytics and advertising stay disabled regardless of the selected choice.
- Added branded 404, 500, generic-error and maintenance templates. Error previews are dev/test only; maintenance returns 503 with `Retry-After: 3600` and is unavailable in production.
- Introduced the shared `PreviewForm` safeguard for contact, newsletter and comments: controls stay disabled without JavaScript and are enabled only after submission prevention is registered. Retested local comment creation and newsletter validation after the refactor; no contact-submission network requests occurred.
- Verification: `composer qa` passed with 47 tests / 578 assertions, PHPStan level 6, all lint/style checks, standalone Tailwind compilation, clean dependency audit and PHP-only tooling check. Tests cover every new page/status, archive counts, empty collections, linked destinations, escaped search, preview-form fallback and production preview restrictions.
- Browser: 68 page/viewport checks across 360/768/1024/1440 passed with no overflow or runtime errors. Inspected contact desktop, contributor dark mode, mobile categories and mobile 404; confirmed all eight category illustrations load on scroll. Consent accepts both choices, persists after reload and reopens from both controls; contact validation and JavaScript-disabled public-form fallback passed. Browser resource errors for deliberately requested 404/500/503 responses are expected, not script failures.
- Handoff: static-page sections are escaped structured preview content; switch the content loop to M5-sanitized `page.content` during integration. Policies still require production/legal review. Full UI accessibility/state audit remains M1.8; administration screens and all backend functionality remain pending.

### 2026-11-12 — M1.5 administration shell and dashboard
- Added separate editorial workspace layout with grouped/active navigation, collapsible desktop sidebar, native-modal mobile drawer, search, notifications, quick-create outline preview, profile summary, breadcrumbs and shared feedback. Future modules are explicitly unavailable until implemented; there are no links to placeholder screens.
- Added dashboard aggregates derived from article/team/comment fixtures, locally served Chart.js 4.5.1 line/doughnut charts with HTML data alternatives, recent stories/comments, activity records and linked quick actions. Chart snapshots are illustrative, not live analytics.
- Built reusable `DataTable`, `StatCard` and main/sidebar `AdminFormLayout` (demonstrated in the styleguide). The article table supports query-preserving combined filters, ascending/descending sorting, per-page/bounded pagination, visible-row selection/indeterminate state, bulk and row confirmation, transient removal, empty/no-results/loading/error/retry states. No write endpoints exist.
- Admin routes require `APP_UI_PREVIEW=1` in dev/test. Default is off and production denies access even with the flag on. `/admin/no-permission` is an explicit 403 UI example, not authentication. Security remains M4.
- Verification: `composer qa` passed (62 tests / 712 assertions, all lint/style checks, PHPStan level 6, standalone CSS, clean Composer audit and PHP-only tooling check). Added 15 admin tests for routes/states, all enabled links, table contracts, chart alternatives, computed counts, GET-only endpoints and the preview/production gate. Initially stale test/prod route caches were refreshed with Symfony cache-clear commands; tests now exercise the new controllers.
- Browser: 32 route/state/viewport checks at 360/768/1024/1440 passed without page overflow or runtime errors. Confirmed combined filters (2 drafts → 1 notebook), pagination (10 rows/page), numeric sorting, indeterminate selection, cancellation, local deletion/reload restoration, retry, hostile sort/per-page values, desktop collapse, mobile drawer/Escape, dropdown arrow-key/focus return, dark mode, invalid-field focus and local-only success toast. JavaScript-disabled controls remain disabled while all 36 table records stay readable.
- Known scope: outline capture is only a form-pattern preview; the full editor/create/edit/revision screens follow in M1.6. Remaining module screens are M1.7a/b. No publishing, persistence, email, uploads, authentication or live analytics is claimed; the full accessibility/state audit remains M1.8.

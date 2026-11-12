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
| 1 | M1.6 | Article Management & Editor UI | Completed | Mock-only editor, settings, media picker, preview and revisions verified |
| 1 | M1.7a | Admin Content Pages | Completed | Mock content modules, upload simulation, moderation, editor reuse and nested menus verified |
| 1 | M1.7b | Admin System Pages | Completed | Mock audience, access, analytics, settings and profile screens verified |
| 1 | M1.8 | UI Audit & Sign-off | Completed | Mock UI engineering audit complete; human approval still required before M2 |
| 2 | M2 | Backend Architecture | Completed | Dev/test/prod boot, Doctrine, cache pools, messenger, logging, typed settings defaults verified |
| 3 | M3 | Database | Completed | 22 entities + embeddable, 3 reversible migrations, fixtures and repository tests verified |
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

## Phase 1 review gate

- **Phase 1 engineering status: Completed** — mock-data interfaces and their audit only.
- **Human UI approval: Waived by owner instruction** to continue implementation through the remaining phases without stopping.
- **M2 started after that instruction.** Persistence, authentication, authorization and business functionality are now being implemented in milestone order. Production parity on MySQL 8 still needs a dedicated verification run in M16; this workspace currently uses the owner-provided local MariaDB 12.3 service for development.

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

### 2026-11-12 — M1.6 article workspace and editor
- Added GET-only create/edit/preview/revision-history/compare routes; list has All/Drafts/Pending/Scheduled/Published/Trash views, filters, local bulk status changes, trash and restoration. Admin has 38 rows (36 active plus two trash fixtures); public fixtures are unchanged.
- Implemented locally served Quill 2.0.3 with working headings, bold/italic/underline, lists, quote, code, links, table insertion/row/column/removal, local images/captions, approved video placeholders, separators, alignment and undo/redo. Semantic HTML is captured locally; server sanitizing remains M5.
- Added editable automatic slug, subtitle/excerpt, status/visibility/schedule, author IDs, category tree, tag suggestions, featured/shared media selection, comments toggle, SEO counters/canonical/noindex/snippet and explicit nonpersisting autosave indicator. Local preview safely copies presentational DOM; revisions use escaped text and ins/del comparison, not executable HTML.
- Verification: final `composer qa` passed (82 tests / 895 assertions, all lint/style/static analysis, standalone CSS, clean fetched dependency audit and PHP-only tooling check). An earlier audit network timeout was transient and resolved on rerun.
- Browser: all toolbar actions, tags, slug editing/regeneration, scheduling, SEO counters, empty-title/content validation/focus, local success and preview, table bulk/trash/restoration were exercised. Recorded zero mutation or external requests during editing/image/video interactions. All 24 route/viewport checks at 360/768/1024/1440 passed without overflow or runtime errors; inspected tablet/mobile editor screenshots. Hidden unselected image slots intentionally have no src, not broken assets. JavaScript-disabled content is readable, controls disabled, with no overflow. Retested semantic bulleted-list preview after correcting its DOM copy.
- No data saves, publishing workers, actual autosaves, revision restoration, upload endpoints, backend authorization or email delivery exist yet. All remaining module screens are M1.7a/b; human review still required after M1.8.

### 2026-11-12 — M1.7a content administration
- Added real preview-gated GET routes for categories/tags, media, moderation, static pages, menus and advertisements, including create/edit forms where applicable. Activated content navigation and media quick-create. Shared PreviewStates/SeoFields/MediaField and presentation-only controller context avoid duplicating infrastructure.
- Taxonomies have tree/list organization, descriptions, parent/image/SEO forms and slug enhancement. Media supports grid/list, search/type/date filters, detail alt/caption, local deletion and drop/file progress simulation with MIME/size preview checks; SVG uploads are disallowed. No file is transferred or persisted.
- Moderation supports all/pending/approved/rejected/spam, local inline decisions and safely text-rendered replies. Pages reuse the full editor/preview/SEO/status UI. Menus support drag/drop, branch-aware up/down/nesting/unnesting, add/edit/delete and location choices with transient structured output. Advertisements support format/placement/priority/enable, UTC date validation and inert code preview.
- QA: `composer qa` passed with 162 tests / 1,473 assertions, lint/style/PHPStan, standalone CSS, clean dependency audit and tooling check. New tests cover 72 route/state combinations, field contracts, local-only controls, all content links, missing IDs, denied POSTs and production gating. Resolved stale no-debug test cache separately; corrected datetime-local literal T formatting.
- Browser: 27 interaction assertions passed, including actual nested drag/drop, dynamic deletion, unsafe URL/code rejection, media document/date filters, metadata edits, SVG rejection, completed upload simulation, moderation re-filtering and page editor preview. Zero mutation requests, external requests or runtime errors. 156 page/state/viewport checks passed at 360/768/1024/1440; inspected desktop media and mobile nested-menu screenshots. All 18 no-JavaScript screens have safe disabled forms and readable content; initial first-page CSS timing was rechecked after load (no actual overflow).
- No backend business logic, database mutations, real upload validation, public-navigation changes, ad execution or reader notifications are implemented. M1.7b remains next; full accessibility/state audit and human review remain M1.8.

### 2026-11-12 — M1.7b system and audience administration
- Added users/roles/permission matrix, subscribers/CSV dialog/campaigns/editor/schedule/history, filtered analytics, appearance, SEO/redirects, general/system/SMTP settings, activity context and profile/password/2FA-preview screens. Activated all administration navigation and profile/security links. Every screen has loading/empty/error examples and safe disabled form fallback.
- Shared editor supports campaign `html` output; media fields support distinct association names. Analytics updates fixed daily/monthly chart data and accessible alternatives, with range validation and empty feedback. Appearance ordering and isolated light/dark/color sample never change the public site; contrast feedback identifies unsuitable choices.
- Verification: `composer qa` passed with 251 tests / 2,248 assertions, lint/style/PHPStan, standalone CSS, clean fetched dependency audit and PHP-only tooling check. Added 89 system tests including route/state combinations, unique IDs, field contracts, read-only methods, links, preview/production gates and absent credential fixtures.
- Browser: 37 interaction assertions and 320 route/state/viewport checks at 360/768/1024/1440 passed; all 20 no-JavaScript screens remained safe/readable without overflow. Zero mutation requests, external requests or runtime errors. Fixed modern-browser pattern syntax for SMTP/GSC tokens; inspected dark analytics desktop and enabled mobile profile screenshots.
- No invitations, persisted permissions, consent changes, exported files, delivery, tracking, installed redirects, executed custom code, password changes or 2FA enrollment are implemented. Dummy password/code values clear after local validation. The QR is explicitly non-scannable. Full UI audit and human review remain M1.8; backend implementation is still deferred.

### 2026-11-12 — M1.8 UI engineering audit
- Completed `docs/ui-checklist.md`: 816 page × state rows for 68 screen variants, with explicit Pass/N/A applicability and shared-component evidence. All applicable rows pass; human UI approval remains pending. Retained machine-readable final results and twelve reviewed screenshots in `docs/ui-audit/`.
- Added the HTTP UI route crawler covering every UI route, loading/empty/error admin states, all public fixture detail pages, internal links, expected error statuses, landmarks, unique IDs, labels/ARIA references and safe disabled preview forms. Added article editor/preview/revision/compare state wrappers and consolidated analytics table markup into the shared component.
- Fixed missing help/error targets and semantic roles, live-notification semantics, keyboard-reachable scrolling tables, native-dialog Tab/Shift+Tab boundary wrapping, dark prose/error/report/control contrast and chart palettes/borders that update with the theme. Styleguide forms now use the same safe PreviewForm contract as production-shaped screens. Videos use explicit external links; no provider is contacted automatically.
- Final QA: `composer qa` passed with **252 tests / 46,694 assertions**, container/Twig/YAML lint, PHPStan level 6, CS Fixer, standalone Tailwind 3.4.17 build, clean dependency audit and PHP-only tooling check. The crawler alone contributes 44,443 assertions. Expected denial/error logs in tests are deliberate requests, not unresolved failures.
- Browser evidence: **2,128** page/state/viewport checks across light/dark and 360/768/1024/1440px; **137** JavaScript-disabled pages; **524** final settled default/state axe scans; **330** keyboard assertions including **19** overlay/feedback scans; **64** changed-page/theme regressions and **24** focused-validation regressions/axe scans. All final checks pass with zero observed mutation/external requests or runtime errors.
- Investigated rather than ignored inconsistent theme-scan failures: post-navigation theme changes could leave inherited colors briefly inconsistent. Final scans use fresh contexts, set the preference before application scripts and assert the rendered palette. Fixed a real initialization gap on error pages without theme buttons; breadcrumb link affordance now includes underlining. Visual review found first-invalid fields obscured by sticky headers; shared form/editor/upload validation now centers focus, reserves header scroll space and connects file errors to their control.
- Reviewed all **344** default contrast-incomplete nodes using computed/composited colors and unobscured hit tests, plus representative screenshots. Minimum reviewed text contrast: 5.48:1 light / 7.74:1 dark; select arrows: 4.83:1 / 3.26:1. The 130 incomplete state findings reuse the same reviewed route/selector components. Keyboard samples also verify enabled control text ≥4.5:1 and unchecked boundaries ≥3:1. Automated/manual browser review is not screen-reader-device or multi-engine certification.
- Phase 1 mock UI engineering is complete. **Stopped for human review before M2.** No persistence, publishing, real uploads, exported subscriber files, email delivery, tracking, credential changes or backend authorization is claimed. MySQL 8 verification and production/legal review remain prerequisites in their later phases.

### 2026-11-12 — M2 backend foundation
- Implemented: Doctrine ORM/migrations config, security firewall skeleton, RFC 9457 JSON API errors, Monolog channels, filesystem/tag-aware/optional-Redis cache pools, Doctrine Messenger transports with in-memory tests, typed settings defaults, DAMA test isolation, local MySQL/Mailpit configuration and the optional Docker MySQL 8.4 stack.
- Fixed during verification: the optional cache adapter now exposes replaceable namespace/lifetime arguments required by Symfony's cache pool pass; the Mailpit published port was out of range; the security logout route import was deferred to M4 because logout is not configured yet.
- Tests: full PHPUnit suite passes with 259 tests / 46,713 assertions, including new integration coverage for settings, cache without Redis, Doctrine connectivity, in-memory async transport and problem+json errors. Container/Twig/YAML lint, PHPStan level 6, PHP CS Fixer and Tailwind build pass; dev, test and prod containers boot.
- Known issues: this workspace verifies against the owner-provided MariaDB 12.3 service; MySQL 8.4 production parity is deferred to M16. Redis-backed mode is configuration-selected but not exercised until a Redis instance is enabled.
- Decisions: application defaults stay credential-free in `.env`; local overrides are git-ignored. `SettingsServiceInterface` stays the stable consumer contract for the M13 persisted implementation.

### 2026-11-12 — M3 database design and implementation
- Implemented 22 attribute-mapped entities plus the `SeoMetadata` embeddable and 10 backed enums: users/roles/permissions, articles/revisions, categories (Gedmo nested tree), tags, comments, likes/bookmarks/views/daily stats, media, pages, menus/items, newsletter subscribers/campaigns, advertisements, settings, redirects and activity logs. Timestampable on all entities; soft delete on users, articles, comments, pages and media; unique slugs, engagement constraints and query indexes as documented.
- Generated three migrations by logical group and verified a full up → down → up cycle on a fresh empty database. Fixed a real down-order defect where `media` was dropped before the `app_user.avatar_id` foreign key was removed.
- Added `DemoFixtures` reproducing the M1 contract: 6 users, 5 roles, 31 permissions, 8 categories (2 nested), 25 tags, 36 articles (30 published, 6 non-public), 6 media records, 5 moderated comments, 5 pages, 2 menus with 6 items, 3 advertisements, 18 subscribers, 3 campaigns, 5 activity records and 1 redirect. Added seven Foundry factories under `tests/Factory`.
- Tests: full PHPUnit suite passes with 266 tests / 46,729 assertions, including repository coverage for published filtering, soft delete, duplicate-like rejection, approved-comment filtering, nested category bounds, role/permission exposure and subscriber tokens. Mapping validation, schema sync, container/Twig lint and PHPStan level 6 pass.
- Known issues: this workspace verifies against MariaDB 12.3; MySQL 8.4 parity remains an M16 deployment check. Full-text search indexes are added in M7 with the search implementation.
- Decisions: roles and permissions are data seeded idempotently, never hardcoded in authorization logic; JSON columns are limited to genuinely flexible payloads; category hierarchy uses Gedmo nested set rather than a custom parent walk.

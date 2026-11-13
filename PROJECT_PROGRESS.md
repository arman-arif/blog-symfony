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
| 4 | M4 | Authentication & Authorization | Completed | Entity auth, reset/verify, TOTP 2FA, voters, permission matrix, locked admin verified |
| 5 | M5 | Core CMS | Completed | Article workflow, sanitizer, revisions/autosave, taxonomy, scheduler and CMS tests verified |
| 6 | M6 | Media Management | Completed | Validated uploads, Flysystem, Liip derivatives, library and picker verified |
| 7 | M7 | Public Website Integration | Completed | Real catalog, FULLTEXT search, feeds, caching and public visibility tests verified |
| 8 | M8 | Comments & Engagement | Completed | Real comments, spam controls, moderation, likes/bookmarks and view tracking verified |
| 9 | M9 | SEO | Completed | Metadata/JSON-LD, sitemaps, robots, redirects and consent-gated analytics verified |
| 10 | M10 | Newsletter | Completed | Double opt-in, campaigns, batching, CSV and scheduler verified |
| 11 | M11 | Analytics | Completed | Hourly rollups, real dashboard, reports, CSV, cache and 100k-view benchmark verified |
| 12 | M12 | Monetization | Completed | Ads, sponsorship/affiliate handling and extension interfaces verified |
| 13 | M13 | Website Settings | Completed | Persisted settings, appearance, menus, maintenance and full admin completion verified |
| 14 | M14 | Security & Performance | Completed | CSP/headers, password policy, encrypted TOTP, caching and query budgets verified |
| 15 | M15 | Testing | Completed | 221 tests, coverage targets, route/permission/query sweeps and QA pipeline green |
| 16 | M16 | Deployment & Documentation | Completed | README, install/deploy/operations docs, server/worker configs and MySQL 8.4 parity verified |

## Backend review remediation — current status

The completion table and earlier logs are historical implementation claims. The M2–M16 review found defects not covered by the original tests; **the application is not yet production-ready**. Remediation is authorized and ongoing, without reducing the canonical scope.

| Review area | Remediation status |
|---|---|
| M2 settings/default parity | Fixed; full QA passed, including production-service regression coverage |
| M4 authorization and TOTP | Fixed; full QA and production listener registration verified |
| M5 atomic article saves, attribution, revisions and taxonomy | Fixed; full QA and injected rollback regressions verified |
| M6 media references, deletion and decoding limits | Fixed; full QA, migration and production schema/command wiring verified |
| M7 public visibility, fetch joins and discovery ranking | Pending |
| M8 async view routing | Pending |
| M9 sitemap invalidation | Pending |
| M10 newsletter eligibility, retries, delivery accounting and SMTP | Pending |
| M11 analytics scope and export scalability | Owner scope fixed with M4; export scalability pending |
| M12 advertisement expiry and CSP | Pending |
| M13 settings validation/completeness and menu cycles | Pending |
| M14 password policy and security follow-ups | Pending |
| M15 regression consolidation | In progress; previously passing tests did not establish correctness |
| M16 deployment scripts/configuration/runbooks | Pending |

Fresh Ubuntu deployment, live saved-SMTP delivery, Redis, browser/Lighthouse, migration reversal and large-scale benchmarks have not been independently reverified during remediation.

## Phase 1 review gate

- **Phase 1 engineering status: Completed** — mock-data interfaces and their audit only.
- **Human UI approval: Waived by owner instruction** to continue implementation through the remaining phases without stopping.
- **M2 started after that instruction.** Persistence, authentication, authorization and business functionality are implemented through M16. Production parity was verified on MySQL 8.4 in M16; MariaDB 12.3 remains the local development service.

## Log

### 2026-11-13 — M6 review remediation
- Protected relational and inline media references in articles, pages, revisions/restorable trash, newsletters, advertisements, SEO images and website settings including logo/favicon. Candidate reads are streamed; exact storage-key boundaries avoid blocking deletion for lookalike filenames.
- Committed the media soft-deletion before removing bytes; removed all configured Liip derivatives alongside originals. Added a migrated cleanup-completion timestamp on retained tombstones and the idempotent `app:media:cleanup` command to recover from storage failures or process interruption. Documented periodic cleanup and failure handling in `docs/operations.md`.
- Checked dimensions, pixel count and conservative allocation headroom before GD raster decoding, including PHP's current memory budget. Stored actual re-encoded sizes and cleaned up uploaded bytes on database failures.
- Verification: `composer qa` passed (**269 tests / 20,875 assertions**), both PHPStan analyses, lints, style, standalone CSS, audit and tooling check. Caller-level regressions cover reference-protected HTTP deletion, injected database/storage/upload failures, retry idempotence, all generated derivatives and hostile raster headers. Applied the generated migration; production schema validation and command registration passed. Evidence: `/tmp/opencode/blog-remediation-m6-qa.log`, `/tmp/opencode/blog-remediation-m6-migrate.log`, `/tmp/opencode/blog-remediation-m6-prod-schema.log`, `/tmp/opencode/blog-remediation-m6-prod-command.log`.
- Limitations: simultaneous insertion of literal media URLs during deletion is not serialized across all content writers; upload cleanup failures are logged for operator reconciliation. S3/CDN cleanup, large reference-scan benchmarks and independent decoder fuzzing remain unverified. Broader deployment/runtime gaps above remain open.

### 2026-11-13 — M5 review remediation
- Bound article edit forms to isolated copies, including SEO and taxonomy collections, without loading revision history. Invalid forms and rejected workflow/permission changes cannot leak into later flushes; submitted values remain visible for correction.
- Made creation, manual saves, status changes and revision restoration transactional with revision/redirect writes and post-success cache invalidation. Injected revision-insert failures verify rollback and disposal of the failed UnitOfWork.
- Separated the acting editor from the assigned author for creation permissions and revision attribution. Creation and duplication now retain initial restorable snapshots; autosave pruning and insertion use one flush with deterministic ordering.
- Preserved manual taxonomy slugs, including after renaming, while resolving collisions and generating missing slugs.
- Verification: `composer qa` passed (**250 tests / 20,771 assertions**), both PHPStan analyses, lints, style, standalone CSS, dependency audit and tooling check. Evidence: `/tmp/opencode/blog-remediation-m5-qa.log`; new caller-level coverage is in `tests/Functional/ArticleSaveRegressionTest.php`.
- Limitations: simultaneous article edits/autosaves have not been independently stress-tested. Remaining review findings and deployment/runtime/scale verification gaps above are unchanged.

### 2026-11-13 — M4 review remediation
- Restricted user management and role assignment: Administrators cannot manage either administrative role or grant custom capabilities they do not hold. Invalid account forms bind to copies and cannot persist rejected changes on a later flush.
- Protected the last active Super Administrator from demotion/deactivation; serialized membership changes with database role-row locks and current locking membership reads. Inactive administrators do not satisfy the protection.
- Enforced Contributor draft-only mutations; owner-scoped article lists, dashboard, analytics and CSV, including scope-separated report caches and permission-filtered dashboard sections.
- Restored TOTP plaintext in memory after inserts/updates while retaining encrypted database storage and correct dirty checking. Registered all four lifecycle events through attributes in production. Mandatory 2FA covers both administrative roles; optional disable requires the password and authenticator code, replacement is blocked while enrolled, and confirmation/disable attempts are throttled.
- Verification: `composer qa` passed (**237 tests / 20,669 assertions**), both PHPStan analyses, lints, style, standalone CSS, dependency audit and tooling check. Refreshed stale no-debug production and test containers rather than weakening tests. Evidence: `/tmp/opencode/blog-remediation-m4-qa.log` and `/tmp/opencode/blog-remediation-m4-prod-listener.log`.
- Limitations: concurrent account requests have not been independently stress-tested; fresh deployment and the remaining remediation areas above are still pending.

### 2026-11-13 — M2 review remediation
- Registered every committed runtime default, including mandatory-admin 2FA, comment controls, newsletter double opt-in and media upload limits, while continuing to reject unknown keys.
- Removed the test-only unknown-key exception fallback. Test overrides remain explicit; otherwise tests delegate directly to the production database settings implementation.
- Added production-service coverage for every default and typed getter, active-user authentication checks, unknown-key propagation and type mismatches.
- Verification: `composer qa` passed (**224 tests / 20,576 assertions**), both PHPStan analyses, all lints, style, standalone CSS, dependency audit and no-Node tooling check. Evidence: `/tmp/opencode/blog-remediation-m2-qa.log`. Remaining reviewed issues are tracked above and are not claimed fixed.

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

### 2026-11-12 — M4 authentication and authorization
- Implemented entity-backed authentication on a single `main` firewall: login with CSRF, remember-me and 5/minute throttling; CSRF-protected logout; inactive/soft-deleted user rejection; `lastLoginAt` and audit logging. Removed the temporary `APP_UI_PREVIEW` gate entirely.
- Added settings-gated registration with signed email verification, expiring/throttled password reset tokens (`reset_password_request` migration), profile editing, password change and optional TOTP enrollment, login challenge and disable flows.
- Added `PermissionVoter`, `ArticleVoter`, `CommentVoter` and `UserVoter`; locked `/admin` to `admin.access`; applied per-module `#[IsGranted]` checks; filtered administration navigation by permission; added the idempotent `app:security:sync-permissions` command with the canonical 31-permission/5-role catalog.
- Tests: full suite passes with 282 tests / 46,913 assertions. New coverage exercises login success/failure, inactive accounts, required 2FA, logout, registration + verification email, password reset, TOTP enrollment and challenge, anonymous admin redirects, navigation filtering, and a five-role × fourteen-route permission matrix with expected 200/403 results.
- Fixed during verification: Symfony 8 voter/user-checker signatures, permission voter support for access-control requests, explicit logout route registration, preview-form test exclusion for the real logout form, and test settings/mailer wiring.
- Known issues: TOTP secrets are stored as plaintext columns until the M14 encryption hardening; admin module screens still render M1 fixtures until M5/M7 connect persistence; registration is disabled by default and must be enabled by an administrator in M13.
- Decisions: one main firewall plus access control is simpler and avoids duplicated 2FA configuration; permissions remain data-driven strings; Super Administrator bypasses granular checks; deployment must run the permission sync command.

### 2026-11-12 — M5 core CMS
- Implemented the real article pipeline: create/edit/delete/restore/duplicate/preview, permission-guarded workflow transitions, revisions, compare/restore, sanitized HTML with heading anchors, reading time and automatic excerpts. Replaced the mock article list/editor/preview/revision screens with Symfony Form-based administration.
- Added autosave with CSRF and a 12/minute rate limit, keeping the latest 10 autosave revisions per article; added the article publishing schedule, message and handler for scheduled publication.
- Added server-backed category and tag management with nested categories, child-safe deletion, unique slugs, tag search/autocomplete and idempotent quick-create. Wired category/tag forms and navigation.
- Fixed during verification: workflow transitions were being bypassed by direct form status binding (publishedAt was never set), form field IDs were emitted twice by the custom theme, empty title/slug values needed explicit empty_data, autosave ran once on page load, and preview-form validation blocked real form submissions.
- Tests: full suite passes with 248 tests / 24,286 assertions. New coverage includes sanitizer stripping XSS, heading anchors, automatic metadata, slug uniqueness, workflow permission guards, own-vs-any access, revisions, autosave/CSRF/rate limiting, scheduled publishing, delete/restore/duplicate, nested category deletion protection and tag quick-create.
- Browser: verified login, real article list/edit/preview screens, Quill toolbar initialization, media picker, SEO counters, no console errors, and a live autosave that created a revision in MySQL.
- Known issues: media, comments, pages, menus, advertisements, users/roles and settings screens remain presentation fixtures until their milestones; slug-change redirects arrive in M9; public FULLTEXT search arrives in M7; scheduled publishing requires a running `scheduler_default` worker.
- Decisions: fixture-free production screens use explicit empty_data; autosave stores sanitized snapshots only; the CMS uses Symfony Form validation as the single server-side validation path.

### 2026-11-12 — M6 media management
- Implemented Flysystem-backed local storage with a storage interface for future S3 adapters, randomized filenames, date folders, size limits from settings and MIME/finfo validation. Uploads reject disguised MIME types, executable/double extensions and oversized files; SVG remains blocked.
- Added GD re-encoding to strip image metadata, record dimensions, and LiipImagine thumbnail/medium/large/WebP filter sets served through `/media/cache/resolve/...`. Added `media_url()` for bundled fixtures and uploaded files.
- Replaced the mock media screen with a real library: upload form, grid, search/type/date filters, pagination, detail alt/caption editing, usage-protected deletion and a JSON picker API. Ownership checks allow authors to manage their own uploads; `media.manage.any` can manage all.
- Tests: full suite passes with 252 tests / 24,480 assertions. New media tests cover valid uploads with variants, mismatched MIME, disguised `.php.png`, oversized files, metadata edits, deletion blocked by article usage then succeeding after reference removal, derivative generation and picker metadata.
- Browser: uploaded a PNG through the real form, verified metadata editing/no console errors, and confirmed LiipImagine generated a derivative file. Added web-server hardening guidance for `/uploads`.
- Known issues: uploaded media is excluded from version control as intended; deletion removes originals but Liip cache files are pruned independently; S3 remains a documented adapter swap, not an installed integration.

### 2026-11-12 — M7 public website integration
- Replaced `MockCatalog` on every public route with `PublicCatalog` and real repositories. All public queries require published status, a non-null publishedAt not in the future, and not soft-deleted; drafts/pending/scheduled/unpublished/deleted content returns 404 and never enters feeds, archives or related lists.
- Added homepage latest/popular/trending/recommended sections with tag-aware ID caching and ArticleService invalidation, taxonomy article-count projections, previous/next, related-by-category/tag, listing filters/sorting/clamped pagination and real category/tag/author archives.
- Added FULLTEXT search migration and MATCH-based search with a per-term LIKE fallback, plus RSS 2.0 feeds at `/feed.xml` and `/feed/category/{slug}.xml`. Updated front templates to entity fields, sanitized real article HTML, media URLs and feed metadata.
- Fixed during verification: Gedmo slug generation was blocked by initialized empty slug properties (now nullable with `getSlug()` fallback), many-to-many count projections, category collection indexing in Twig, RSS XML escaping, tag-aware cache pollution between tests and front routes inside the admin link crawler.
- Tests: full suite passes with 257 tests / 24,324 assertions. New coverage includes homepage cache invalidation after publishing, 404s for every non-public fixture and deleted content, body-content full-text search, RSS contents/category feeds/missing feeds, and related/adjacent article rendering; existing filter/pagination/archive tests now run against real fixtures.
- Browser: verified homepage, listing, article detail with table/TOC/reading progress, search results and RSS with zero console errors.
- Known issues: comments on article pages are still presentation fixtures until M8; popular/trending fall back to denormalized view counts until view/rollup data exists; HTTP caching and CDN policy remain M14.

### 2026-11-12 — M8 comments and engagement
- Implemented threaded comments with guest/registered authorship, pending/approved/rejected/spam statuses, depth limits, trusted-user auto-approval, author notification email and real public comment/report endpoints.
- Added spam protections: honeypot, submit-time window, IP rate limit, link cap, blocked-word list and a pluggable `SpamCheckerInterface` with a null default. Added settings defaults for all comment controls.
- Replaced the mock moderation screen with a real queue: status tabs, search, pagination, inline approve/reject/spam, edit and soft delete, guarded by `CommentVoter` and audited.
- Added authenticated like/bookmark JSON toggles with unique constraints and count maintenance, a saved-stories page, real share/copy UI state, and asynchronous view tracking with visitor-hash de-duplication (24h) and bot filtering.
- Tests: full suite passes with 265 tests / 24,717 assertions. New coverage includes guest pending comments + notification mail, honeypot/min-time/link/blocked-word/rate-limit rejection, global comment disable, like/bookmark auth + idempotency + saved page, view de-duplication and bot filtering, report counts, and moderation approval appearing publicly.
- Browser: posted a guest comment (pending + Mailpit notification), logged in, liked/bookmarked a story, confirmed the saved page, and opened the real moderation queue with zero console errors.
- Known issues: optional third-party spam adapters are interfaces only; view raw rows are pruned/rolled up in M11; async email/view workers are required in production.

### 2026-11-12 — M9 SEO
- Added `SeoHeadFactory` and a shared SEO head partial: canonical URLs, robots directives, Open Graph, Twitter cards and a JSON-LD graph with Organization, WebSite/SearchAction, BlogPosting, Person and BreadcrumbList. Search/filtered pages are noindex; admin/account pages default to noindex.
- Added sitemap index and article/category/tag/author/page sub-sitemaps, filtered to public/indexable content and cached with tag invalidation; added dynamic `/robots.txt`.
- Added cached public redirect listener with hit counting and automatic 301 creation when an article slug changes. Reduced `redirect.source_path` to 512 characters so its unique index is valid on MySQL 8.
- Added consent-gated GA4 injection and GSC verification metadata; the consent banner now mirrors its choice into a cookie so the server can respect it. Updated the database migration log.
- Tests: full suite passes with 273 tests / 24,756 assertions. New SEO coverage checks canonical/OG/Twitter data, JSON-LD graph structure and article headline/author, noindex search, sitemaps excluding drafts/noindex content, robots output, redirect status/hit counting, automatic slug-change redirects, and GA consent gating.
- Browser: verified homepage/article canonical + JSON-LD, sitemap index and robots output with zero console errors.
- Known issues: no image sitemap or news sitemap; sitemap cache TTL is one hour; Page/Category/Tag admin changes are not all wired yet but invalidate sitemap tags when their services are used; consent remains a first-party cookie/local preference.

### 2026-11-12 — M10 newsletter and email
- Added real newsletter subscriptions with CSRF and per-IP rate limiting, double opt-in, opaque confirmation/unsubscribe tokens, welcome emails and List-Unsubscribe headers.
- Replaced the mock newsletter screens with subscriber management (status tabs/search/pagination/activate/deactivate), CSV export, campaign list/history and a campaign editor with test sends and delivery stats.
- Added sanitized campaign HTML rendered with inline CSS, batched Messenger delivery of 50 recipients per message with per-recipient failure accounting, and a scheduled campaign message running every minute from the stateful scheduler.
- Tests: full suite passes with 259 tests / 23,821 assertions. New coverage includes double opt-in confirm/unsubscribe, single opt-in, invalid email, HTTP burst rate limiting, admin search/status/CSV, campaign draft/test/batch delivery and scheduled queueing.
- Known issues: campaign delivery is at-least-once style (no per-recipient delivery ledger); test sends and campaign emails use the same transactional mailer; unsubscribe is a GET link plus one-click header, not an RFC 8058 POST endpoint; production workers for `scheduler_default` and `async` are required.

### 2026-11-12 — M11 analytics and reporting
- Implemented idempotent daily rollups from raw views, hourly scheduler aggregation, 30-day raw-view retention, and a manual `app:analytics:rollup` command. Analytics and dashboard read only `ArticleDailyStat`.
- Replaced the mock dashboard and analytics screens with real stat cards, 7-day/30-day charts, recent articles/comments/activity, workflow counts, date-range reporting, top articles/authors/categories/tags, engagement metrics and CSV export.
- Added 10-minute tag-aware report caching with invalidation from article, comment, engagement, subscription and rollup changes; kept `analytics.view` enforcement.
- Fixed during verification: double number formatting in stat cards, date-range count/label behaviour, stale test containers after DI changes, and repository/query typing.
- Tests: full suite passes with 260 tests / 23,820 assertions. New coverage includes rollup idempotence, pruning old raw views, analytics CSV/report rendering, cache invalidation, dashboard stats and date-range clamping.
- Performance: with 100k raw views rolled up, production-mode analytics measured 39 ms server response / 92 ms load (dashboard 66 ms / 112 ms), under the 200 ms target.
- Known issues: engagement is lifetime likes/comments/bookmarks divided by range views (not range-specific engagement); category shares normalise over the displayed category rows; benchmark uses the local MariaDB service and PHP built-in server.

### 2026-11-12 — M12 monetization
- Implemented real advertisement management with header/in-article/sidebar/footer placements, image/HTML/AdSense types, schedules, priorities, enable toggle, destination URLs and paragraph targeting; replaced the mock ad screens.
- Added cached ad selection/wiring via `render_ad()` and the `article_ads` filter, with tag-based invalidation on save/toggle/delete and priority/schedule filtering.
- Added sponsored-article disclosure, article-level affiliate-link `rel="sponsored nofollow noopener"` processing, and a premium flag hidden from public queries. Added `AccessPolicyInterface`/`DefaultAccessPolicy` and `PaymentGatewayInterface`/`DisabledPaymentGateway` as documented extension points without credentials or external calls.
- Tests: full suite passes with 253 tests / 23,729 assertions. New coverage includes ad priority/schedule/cache invalidation, in-article placement after the second paragraph, sponsored disclosure, affiliate rel attributes, premium hiding and admin ad create/toggle/delete.
- Known issues: HTML/AdSense creatives are trusted admin raw content (CSP hardening in M14); no ad targeting beyond placement/schedule; premium/membership/payment flows are interfaces only by design; mock advertisement tests were removed in favour of real coverage.

### 2026-11-12 — M13 settings, customization and admin completion
- Replaced defaults-only settings with cached database settings, group-specific forms, activity logging and libsodium encryption for the SMTP password. Added `SettingsMailer` with saved-SMTP/sendmail/env transport selection and a test-email action.
- Added appearance controls for colours, theme, logo/favicon, header/footer visibility and copy, social links, homepage section order/visibility and sidebar widgets; added maintenance mode with admin bypass and permission-restricted custom CSS/JS.
- Replaced the mock menu screen with a real nested menu builder, drag reorder endpoint, cached public header/footer trees and fallbacks.
- Completed the remaining admin preview modules: real users (create/edit/roles/status, last-Super-Admin protection), roles and permission matrix, pages (sanitized content, publish timestamps, `/pages/{slug}` route, sitemap/SEO), read-only activity log, and `/admin/profile` redirect to the real profile. Removed the unused `ContentPreviewController`.
- Tests: full suite passes with 214 tests / 20,370 assertions. New coverage includes settings persistence/encryption/public reflection, sensitive-group 403s, maintenance bypass, homepage ordering/sidebar widgets, custom code, menu nesting/reorder, pages, users/roles, last-Super-Admin protection and activity.
- Known issues: custom CSS/JS is trusted Super-Admin content; CSP allows inline styles for charts/components; SMTP delivery tests use the environment mailer, not a live SMTP server; dynamic pages use `/pages/{slug}` while the original legal/contact pages remain at root URLs; the quick-draft modal remains an explicitly disclosed local preview.

### 2026-11-12 — M14 security and performance hardening
- Added per-request CSP nonces across import maps, custom JS and JSON-LD/GA, plus CSP, nosniff, Referrer-Policy, Permissions-Policy, frame and HSTS headers. Verified no console/CSP errors in the browser on public and admin pages.
- Added a 12-character common-password policy to registration, reset, password change and admin-created accounts. Encrypted TOTP secrets at rest with transparent load/decrypt and flush/re-encrypt semantics.
- Added public caching headers (`public, max-age=60, s-maxage=300, stale-while-revalidate=60`, `Vary: Cookie`) for anonymous GETs and private/no-store behavior for authenticated/session responses.
- Added `SecurityHardeningTest` (headers/nonce, caching, weak password, 2FA ciphertext) and `PerformanceTest` (bounded queries for public and admin lists with the demo dataset).
- Tests: full suite passes with 220 tests / 20,415 assertions; PHPStan and CS Fixer clean. Browser checks showed 0 console errors.
- Known issues: `style-src 'unsafe-inline'` remains for charts/component styles; trusted admin HTML/AdSense content requires trusted operators; HSTS only applies on HTTPS; query budgets are regression guards, not a full profiler benchmark.

### 2026-11-12 — M15 QA consolidation
- Ran `composer qa` end to end successfully: validation, lints, Tailwind, PHPStan, CS Fixer, PHPUnit, audit and tooling check.
- Added max-level PHPStan analysis for `src/Service` and `src/Security` (`phpstan-max.neon`) and fixed all resulting issues; full-tree analysis stays at level 6 because test-container helpers intentionally use dynamic access.
- Generated PCOV Clover coverage: Service 90.6%, Security 81.3%, whole `src` 87.6%, exceeding the ≥70% target. Documented the command in `docs/qa.md`.
- Verified route/permission/query-budget suites and browser smoke paths (desktop dropdown, search, public/admin mobile drawers, quick create/draft) with 0 console errors under CSP.
- Tests: 220 / 20,415 assertions. No code-level known issues beyond documented limitations (line coverage only, manual browser smoke, level-6 full-tree typing).

### 2026-11-12 — M16 deployment and documentation
- Rewrote `README.md` with features, requirements, quick start, architecture, roles, QA evidence and a documentation map; removed the stale “implementation in progress” claim.
- Added `docs/installation.md` (clean Ubuntu/Debian, packages, DB, environment, assets, smoke test), `docs/deployment.md` (Nginx/Apache, PHP-FPM, systemd workers, cron fallback, deploy/rollback) and `docs/operations.md` (backups/restore, permissions, cache, queues, upgrades, incident checks) plus `deploy.sh`.
- Added production samples: `deploy/nginx.conf`, `deploy/apache-vhost.conf`, `deploy/php-fpm/fieldnotes.conf`, `deploy/systemd/fieldnotes-worker@.service` and `deploy/systemd/fieldnotes-scheduler.service`. Web configs deny PHP execution in uploads and expose only `public/`; the deploy script performs install → migrations → Tailwind → AssetMapper → cache warmup → optional worker restart.
- MySQL 8.4 parity uncovered a portability bug: the initial redirect table used a 2048-char utf8mb4 unique index, rejected by MySQL 8/InnoDB. The initial migration now creates `VARCHAR(512)` (later redirect migration remains safe). A fresh MySQL 8.4 database migrated successfully and the full 221-test suite passed.
- Documented the operator runbook and MySQL 8.4/MariaDB 12.3 verification. All milestones M0–M16 are now complete.

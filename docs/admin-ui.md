# Administration preview patterns

The workspace routes require an authenticated session with the `admin.access` permission (M4). Each module additionally enforces a granular permission, and the sidebar only renders modules the current user may open. The screens still use presentation fixtures until the corresponding backend modules are connected; that is a data-source limitation, not an access bypass.

## Shared layout

`templates/admin/layout.html.twig` supplies grouped navigation, active links, desktop collapse, a native modal mobile drawer, workspace search, editorial notifications, quick-create outline preview, profile/security links, breadcrumbs, actions and live notifications. All specified administration destinations are implemented and linked as mock-only screens.

`StatCard`, `DataTable` and `AdminFormLayout` extend the M1.1 primitives. Forms that would mutate data must use `PreviewForm`: controls remain disabled until submission prevention is installed. The outline form validates but never creates a draft.

## Data table

`DataTable` receives `id`, `caption`, `columns` (`key`, `label`, optional `sortable`), `filters` (`key`, `label`, `options` of `value`/`label`) and a content block of entity-backed rows. Rows expose `data-row`, stable `data-id`, `data-search` and field-specific data attributes. Row checkboxes use `data-select-row`; optional removal uses `data-row-remove` plus the existing confirmation component.

The vanilla module implements filters, ascending/descending sort with `aria-sort`, per-page selection/indeterminate state, bulk confirmation, local removal, pagination, summary announcements and query-string synchronization. Optional `tabs` and `trashEnabled` add status views and transient restoration; article rows include two trashed fixtures in addition to the 36 active records. All excludes trash. Filter changes clear selection; select-all selects only the visible page. Removed records return on reload. Without JavaScript all mock rows remain readable and selection/sort controls are disabled. No mutation endpoint exists.

Preview states: `?state=loading`, `?state=empty`, `?state=error` on dashboard/table; use a nonmatching `q` for no-results. The error table has a local retry action. `/admin/no-permission` deliberately returns 403 and describes the future permission experience, not a real authorization decision.

## Charts and records

Chart.js **4.5.1** and its color dependency are downloaded with PHP's `importmap:require` and served locally by AssetMapper. Charts import registered ES modules without a JavaScript build tool. Each canvas has a labeled HTML data table, reduced-motion-safe animation settings and theme-aware labels. A page-specific admin entrypoint keeps chart execution off public pages.

Article/team/comment counts are derived from `MockDataProvider`. Time-series and category percentages are a documented illustrative analytics snapshot. There is no live tracking, newsletter-delivery claim or database analytics yet.

## Article editor (M1.6)

Create/edit/preview/revisions/compare routes are GET-only. Quill **2.0.3** and its stylesheet are PHP-importmapped and locally served. The toolbar supports headings, inline formatting, lists, quotes, code, links, tables with row/column controls, local figures/captions, video placeholders, separators, alignment and undo/redo. `RichEditor` exports semantic HTML to a read-only hidden `content` textarea; **this is not server-sanitized content**. M5 must run Symfony HtmlSanitizer before storage or rendering.

Settings include an editable generated slug, schedule, visibility, author, hierarchical categories, suggested tags, featured image and SEO/snippet fields. The autosave indicator describes only a transient browser preview. Local preview copies allow-listed DOM nodes using text-safe construction, preserving list/code presentation. Paste rejects remote images and iframes. YouTube/Vimeo insertions show a provider link, not a loaded embed; no third-party request occurs merely by inserting a video.

The shared media picker emits `media:chosen` with `target`, media `id`, local `url`, `alt`, `caption`, `width` and `height`. Search/selection, featured image removal and editor insertion never upload files. Revisions use escaped text fixtures and semantic `ins`/`del`; restoration is explicitly unavailable until M5. Reload discards every edit. Without JavaScript, original content remains readable and all form controls remain disabled.

## Content modules (M1.7a)

Categories/tags, media, comments, pages, menus and advertisements have real GET-only admin routes, populated views and loading/empty/error examples via `?state=...`. `ContentPreviewController` supplies shared **presentation context only**; each module has its own attribute-routed controller. It must not acquire business logic when backend services are introduced.

- Taxonomies use the reusable table and main/sidebar form, category parent choices, media selection and `SeoFields`. Sorting the category list intentionally flattens the hierarchy. Backend integration must enforce unique slugs, safe parent relationships and deletion/reassignment rules.
- Media has grid/list, name/type/date filters, a metadata drawer, local removal and upload/drop progress **simulation**. It checks reported browser MIME and 10 MB size only; M6 must verify actual MIME/extension/size on the server. Existing SVG artwork is trusted bundled content; SVG uploads are rejected. A bundled TXT fixture demonstrates documents. Selected files are never transferred, persisted or added to the library.
- Moderation tabs use five flattened fixtures, including replies, rejected and spam examples. Approve/reject/spam updates only row state. `table:changed` refreshes filtering after a local action. Replies are created with `textContent`, not parsed HTML. No notifications are sent.
- Pages reuse `RichEditor` with escaped page sections, SEO/status controls and safe local preview. `content` output must be sanitized in M5; no page save endpoint exists.
- Menus use native drag/drop plus keyboard-accessible Up/Down/Nest/Unnest controls. Branches move together; cycles and more than three levels are prevented in the preview. `items` contains transient JSON with stable/local IDs, label/type/target/parent/position. Additions, edits, reorder and confirmed removal never change public navigation.
- Advertisements expose placement/type/priority/enable and UTC start/end controls. HTML and network creatives are inert text; there is no script execution, provider request or live ad rendering. End-before-start validation works locally. M12 owns real placement/schedule behavior.

`PreviewStates`, `SeoFields` and `MediaField` are reusable for later module forms. Local file checks, HTML-copy allow-lists and form constraints are UI safeguards, **not substitutes for backend validation, CSRF, authorization or sanitizing**.

## System and audience modules (M1.7b)

Every screen has real preview-gated GET routes and loading/empty/error examples. No mutation routes, mail transport, account security or settings persistence have been added.

- Users expose profile fields, role IDs and local active/inactive changes. Roles use granular capability strings; the matrix supports individual checks and column selection. Assignments are illustrative, not enforced. Own-resource, contributor-draft and higher-role/last-Super-Administrator restrictions remain M4 voter responsibilities.
- Newsletter includes 18 fictional subscribers, local activation with refreshed status filters, and an **all-fixture CSV dialog** irrespective of table filters. No file is exported. Campaigns have draft/scheduled/sent snapshots, history and the shared editor with `outputName="html"`; scheduling requires a date locally but starts no worker. Email preview uses the same safe DOM copy as pages. Test-email buttons validate fictional recipients without contacting them.
- Analytics filters 30 fixed daily records (Oct 13–Nov 11, 2026), updates daily/monthly charts and their HTML alternatives, totals and illustrative top-table view projections. Lifetime engagement rates and team/subscriber counts are labeled separately. Empty ranges and reversed dates have distinct feedback. No visitor information is collected.
- Appearance has an isolated sample, color contrast feedback, header/footer/sidebar/default-mode options, section visibility and keyboard-accessible ordering, footer text and HTTP(S) social URLs. It never changes the public website or stored theme preference.
- General/system settings cover identity, logo/favicon association IDs, locale, registration/comments/opt-in, media limit, cache lifetime and maintenance. Custom CSS/JavaScript is **inert text**, never evaluated. Sensitive capability requirements are disclosed but not enforced until M4/M13.
- SEO includes defaults, canonical/OG image, plain-text robots editor, sitemap toggles, GA/GSC token syntax and a local redirects table with safe path/URL validation and confirmed deletion. No tracker is loaded, crawler file served or redirect installed.
- SMTP includes fictional host/port/encryption/sender/template fields and a test-email dialog. Passwords start blank and dummy values are cleared after successful local validation. No transport is connected.
- Activity records support filtering and a safely text-rendered context dialog, with no deletion controls. Profile reuses identity/media fields; password confirmation and six-digit 2FA format validate locally, clear dummy values and explicitly leave security unchanged. The QR is a labeled, non-scannable placeholder; recovery codes remain unavailable.

`MediaField` accepts a configurable `name` for distinct logo/favicon/avatar/OG associations. `DataTable` accepts `removable=false` for read-only audit history. `chart:changed` carries `{labels, values}` locally; late chart initialization reads updated node data too. Never send preview passwords or 2FA values through storage, logs or serialized events. Backend integration must replace these client safeguards with Symfony Form/Validator, CSRF, Mailer and permission voters.

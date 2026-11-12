# Administration preview patterns

The workspace and article routes are available only in dev/test with `APP_UI_PREVIEW=1`. The committed default is off; production returns 404 even if the flag is accidentally enabled. This is **not authentication**. M4 replaces the temporary gate with Security and granular permission voters.

## Shared layout

`templates/admin/layout.html.twig` supplies grouped navigation, active links, desktop collapse, a native modal mobile drawer, workspace search, editorial notifications, quick-create outline preview, profile summary, breadcrumbs, actions and live notifications. Later module navigation is explicitly marked unavailable, rather than linked to unfinished placeholder screens. Only implemented destinations are interactive.

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

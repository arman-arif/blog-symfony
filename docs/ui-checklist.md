# Phase 1 UI checklist — M1.8

**Engineering audit complete; human review pending.** This covers mock interfaces, not production features. M2 must not start until the reviewer approves Phase 1.

## Coverage and evidence

- One row per reviewed screen × required state, plus its default state. Dynamic article/author/category/tag pages use a representative route below; all 30 published articles, six authors, eight categories and 25 tags were also crawled and browser-checked. Four alternate administration records exercise hierarchy, inactive users, scheduled campaigns and inert advertisements.
- **Pass** means the applicable presentation is verified. **N/A** means the state is deliberately inapplicable, with a reason; it does not mean unimplemented business behavior is complete. Shared-shell interactions are identified rather than claimed as page-specific features.
- HTTP crawler: all UI routes, internal links, expected statuses, unique IDs, labels/ARIA references, state markup and disabled preview forms. Error examples deliberately return 403/404/418/500/503 rather than disguising them as 200.
- Browser: **2,128** page/state/viewport checks in light/dark at **360/768/1024/1440px**, **137** JavaScript-disabled pages at 360px, **524** settled page/state axe 4.11.0 scans, and **330** keyboard/interaction assertions including **19** overlay/feedback axe scans. Final regressions add **64** changed-page/theme checks and **24** focused-validation checks/scans across all four widths and both themes.
- Final `composer qa`: **252 tests / 46,694 assertions**, container/Twig/YAML lint, PHPStan level 6, CS Fixer, pinned standalone Tailwind, dependency audit and PHP-only tooling check.
- Machine-readable evidence: [audit results](ui-audit/m1.8-results.json). Representative visual review: [screenshots](ui-audit/README.md). Transient first attempts are not counted as passing; corrected final runs are used.

### Accessibility review limitations

Automated checks are not accessibility certification. Keyboard operation, visible focus, focus containment/return, semantic labels, table scrolling and representative visual layouts were additionally reviewed. Charts have readable HTML alternatives; updates and validation have visible/live feedback. No screen-reader-device or exhaustive browser-engine certification is claimed.

Default scans produced **344** contrast-incomplete nodes: select background icons, textarea geometry and text affected by sticky/overlapping layout sampling. Each was scrolled into view and checked using rendered foreground/composited background colors and center-point obstruction checks: all passed. Minimum sampled text ratios were **5.48:1 light / 7.74:1 dark**; select-arrow ratios were **4.83:1 / 3.26:1**. The **130** state-scan incomplete findings reuse the exact same reviewed route/selector components. Default control text requires ≥4.5:1 and unchecked boundaries/glyphs ≥3:1; genuinely disabled controls are contrast-exempt.

The initial audit changed the theme class after navigation and sampled inconsistent inherited colors. Final scans use fresh contexts with a theme preference set before application scripts and assert the actual rendered palette. A real defect was also fixed: saved/system themes now initialize even on error pages without a toggle. Administration breadcrumb links are underlined so their affordance does not rely on color.

Visual review also caught first-invalid fields hidden behind sticky navigation. Shared form/editor/upload validation now centers focused controls, and document scroll padding reserves header space. The final 24-case regression checks focused identity, invalid state, visible outline, connected descriptions and an unobscured control at every breakpoint/theme.

## Shared interaction contracts

- Public shell: keyboard category menu, search dialog and mobile drawer; newsletter validation/success are local-only. Consent choices persist only the browser preference; optional tracking remains off.
- Admin shell: keyboard navigation, mobile drawer, collapse, notifications/profile menus and quick-draft validation/success. The no-permission example is `/admin/no-permission`; it is illustrative, not an authorization implementation.
- `PreviewForm`: submission prevention precedes enabling controls; without JavaScript fields remain disabled with readable content. Every preview explicitly says it does not save/send. Search forms use read-only GET navigation.
- `DataTable`: combined mock filters, sortable headings, selection, pagination, loading/empty/error/retry/no-results, and cancellation/local removal where applicable. Horizontal scrolling is keyboard reachable. Loading/error displays do not issue data requests.
- `PreviewStates`: form/detail loading/empty/error presentations are selected with `?state=loading`, `?state=empty` or `?state=error`. Empty forms mean an illustrative empty-data panel, not a persisted record deletion.
- Rich editor/media/moderation/menu/settings actions were exercised in M1.6/M1.7a/M1.7b; M1.8 retests the shared keyboard, editor validation/success and overlays. Module evidence in `PROJECT_PROGRESS.md` is retained rather than relabelled as new exhaustive interaction testing.
- Video links are explicit external navigation; no automatic provider requests. Uploads, CSV exports, email tests, custom code, password changes, 2FA and publication remain simulations/inert/disabled as disclosed. Verification recorded no mutation requests, external requests or script errors.

## Page × state review

Evidence abbreviations: **C** HTTP crawler/functional suites; **R** responsive browser batches; **A** settled light/dark axe; **K** shared keyboard/feedback audit; **N** no-JavaScript browser checks; **M** earlier module interaction evidence. Each state's coverage uses its implementation mechanism; these letters do not imply every combination was manually operated independently.
The following 68 screen variants have 816 rows.

### This path leads somewhere else. — `/_error/404`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 404; populated/readable content (C/R/A/N). |
| Loading | N/A | Synchronous server-rendered page; no async data loading. |
| Empty | N/A | Fixed page/directory content, not an editable collection. |
| Error | Pass | Branded server-error fallback at `/_error/500` (C/R/A/N). |
| Success | N/A | Read-only error/maintenance screen; no successful write action. |
| Validation | N/A | No input form on this error/maintenance screen. |
| Disabled | N/A | Navigation links only; no unavailable input action. |
| Confirmation | N/A | No action needing a confirmation dialog. |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | N/A | Public content; no restricted operation. |
| Responsive navigation | Pass | Wrapping navigation/recovery links at all four widths (R/K where interactive). |

### A clear record of the work. — `/admin/activity`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Shared quick-draft validation/local success (C/K/M). |
| Validation | Pass | Shared quick-draft validation/local success (C/K/M). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Shared quick-draft/navigation dialogs; no persisted save confirmation (K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | Pass | Unmatched mock filter displays clear-filter state (M/R; shared DataTable). |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### Partnerships with a little purpose. — `/admin/advertisements`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Shared quick-draft validation/local success (C/K/M). |
| Validation | Pass | Shared quick-draft validation/local success (C/K/M). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Cancel/confirm local removal; dialog keyboard/focus return (M/K). |
| Delete confirmation | Pass | Explicit local-only removal confirmation; cancellation/reload restore fixtures (M/K). |
| No results | Pass | Unmatched mock filter displays clear-filter state (M/R; shared DataTable). |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### A place for the right partnership. — `/admin/advertisements/new`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Validation | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Dialog interactions; labelled controls, Escape/focus return (M/K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### A place for the right partnership. — `/admin/advertisements/1/edit`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Validation | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Dialog interactions; labelled controls, Escape/focus return (M/K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### Understand what resonates. — `/admin/analytics`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Validation | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Shared quick-draft/navigation dialogs; no persisted save confirmation (K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | Pass | Date range outside the fictional snapshot displays no-data feedback (M). |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### Stories in the making. — `/admin/articles`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Shared quick-draft validation/local success (C/K/M). |
| Validation | Pass | Shared quick-draft validation/local success (C/K/M). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Cancel/confirm local removal; dialog keyboard/focus return (M/K). |
| Delete confirmation | Pass | Explicit local-only removal confirmation; cancellation/reload restore fixtures (M/K). |
| No results | Pass | Unmatched mock filter displays clear-filter state (M/R; shared DataTable). |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### Begin with a little curiosity. — `/admin/articles/new`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Validation | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Dialog interactions; labelled controls, Escape/focus return (M/K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### Give this story room to grow. — `/admin/articles/1/edit`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Validation | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Dialog interactions; labelled controls, Escape/focus return (M/K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### The quiet power of making things with intention — `/admin/articles/1/preview`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Shared quick-draft validation/local success (C/K/M). |
| Validation | Pass | Shared quick-draft validation/local success (C/K/M). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Shared quick-draft/navigation dialogs; no persisted save confirmation (K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### Every story has a process. — `/admin/articles/1/revisions`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Shared quick-draft validation/local success (C/K/M). |
| Validation | Pass | Shared quick-draft validation/local success (C/K/M). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Shared quick-draft/navigation dialogs; no persisted save confirmation (K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### See how the story changed. — `/admin/articles/1/revisions/11/compare`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Shared quick-draft validation/local success (C/K/M). |
| Validation | Pass | Shared quick-draft validation/local success (C/K/M). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Shared quick-draft/navigation dialogs; no persisted save confirmation (K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### Make every story easy to find. — `/admin/categories`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Shared quick-draft validation/local success (C/K/M). |
| Validation | Pass | Shared quick-draft validation/local success (C/K/M). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Cancel/confirm local removal; dialog keyboard/focus return (M/K). |
| Delete confirmation | Pass | Explicit local-only removal confirmation; cancellation/reload restore fixtures (M/K). |
| No results | Pass | Unmatched mock filter displays clear-filter state (M/R; shared DataTable). |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### Give an idea a place to belong. — `/admin/categories/new`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Validation | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Dialog interactions; labelled controls, Escape/focus return (M/K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### Refine the way readers explore. — `/admin/categories/1/edit`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Validation | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Dialog interactions; labelled controls, Escape/focus return (M/K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### Make room for a good conversation. — `/admin/comments`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Validation | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Cancel/confirm local removal; dialog keyboard/focus return (M/K). |
| Delete confirmation | Pass | Explicit local-only removal confirmation; cancellation/reload restore fixtures (M/K). |
| No results | Pass | Unmatched mock filter displays clear-filter state (M/R; shared DataTable). |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### A good day to make something. — `/admin`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Shared quick-draft validation/local success (C/K/M). |
| Validation | Pass | Shared quick-draft validation/local success (C/K/M). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Dialog interactions; labelled controls, Escape/focus return (M/K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### This area needs a little more access. — `/admin/no-permission`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 403; populated/readable content (C/R/A/N). |
| Loading | N/A | Synchronous server-rendered page; no async data loading. |
| Empty | N/A | Fixed page/directory content, not an editable collection. |
| Error | Pass | Branded server-error fallback at `/_error/500` (C/R/A/N). |
| Success | Pass | Shared quick-draft validation/local success (C/K/M). |
| Validation | Pass | Shared quick-draft validation/local success (C/K/M). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Shared quick-draft/navigation dialogs; no persisted save confirmation (K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### A library of visual possibilities. — `/admin/media`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Validation | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Cancel/confirm local removal; dialog keyboard/focus return (M/K). |
| Delete confirmation | Pass | Explicit local-only removal confirmation; cancellation/reload restore fixtures (M/K). |
| No results | Pass | Unmatched filename/type/date displays media no-match state (M). |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### A considered path through your site. — `/admin/menus`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Shared quick-draft validation/local success (C/K/M). |
| Validation | Pass | Shared quick-draft validation/local success (C/K/M). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Cancel/confirm local removal; dialog keyboard/focus return (M/K). |
| Delete confirmation | Pass | Explicit local-only removal confirmation; cancellation/reload restore fixtures (M/K). |
| No results | Pass | Unmatched mock filter displays clear-filter state (M/R; shared DataTable). |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### Give readers a thoughtful next step. — `/admin/menus/new`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Validation | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Cancel/confirm local removal; dialog keyboard/focus return (M/K). |
| Delete confirmation | Pass | Explicit local-only removal confirmation; cancellation/reload restore fixtures (M/K). |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### Give readers a thoughtful next step. — `/admin/menus/1/edit`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Validation | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Cancel/confirm local removal; dialog keyboard/focus return (M/K). |
| Delete confirmation | Pass | Explicit local-only removal confirmation; cancellation/reload restore fixtures (M/K). |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### A thoughtful note, a willing audience. — `/admin/newsletter`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Shared quick-draft validation/local success (C/K/M). |
| Validation | Pass | Shared quick-draft validation/local success (C/K/M). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Cancel/confirm local removal; dialog keyboard/focus return (M/K). |
| Delete confirmation | Pass | Explicit local-only removal confirmation; cancellation/reload restore fixtures (M/K). |
| No results | Pass | Unmatched mock filter displays clear-filter state (M/R; shared DataTable). |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### Something worth opening. — `/admin/newsletter/campaigns`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Shared quick-draft validation/local success (C/K/M). |
| Validation | Pass | Shared quick-draft validation/local success (C/K/M). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Cancel/confirm local removal; dialog keyboard/focus return (M/K). |
| Delete confirmation | Pass | Explicit local-only removal confirmation; cancellation/reload restore fixtures (M/K). |
| No results | Pass | Unmatched mock filter displays clear-filter state (M/R; shared DataTable). |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### A record of notes sent with care. — `/admin/newsletter/history`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Shared quick-draft validation/local success (C/K/M). |
| Validation | Pass | Shared quick-draft validation/local success (C/K/M). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Cancel/confirm local removal; dialog keyboard/focus return (M/K). |
| Delete confirmation | Pass | Explicit local-only removal confirmation; cancellation/reload restore fixtures (M/K). |
| No results | Pass | Unmatched mock filter displays clear-filter state (M/R; shared DataTable). |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### Begin a note worth reading. — `/admin/newsletter/campaigns/new`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Validation | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Dialog interactions; labelled controls, Escape/focus return (M/K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### Begin a note worth reading. — `/admin/newsletter/campaigns/1/edit`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Validation | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Dialog interactions; labelled controls, Escape/focus return (M/K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### The pages behind your journal. — `/admin/pages`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Shared quick-draft validation/local success (C/K/M). |
| Validation | Pass | Shared quick-draft validation/local success (C/K/M). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Cancel/confirm local removal; dialog keyboard/focus return (M/K). |
| Delete confirmation | Pass | Explicit local-only removal confirmation; cancellation/reload restore fixtures (M/K). |
| No results | Pass | Unmatched mock filter displays clear-filter state (M/R; shared DataTable). |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### A clear home for useful information. — `/admin/pages/new`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Validation | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Dialog interactions; labelled controls, Escape/focus return (M/K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### A clear home for useful information. — `/admin/pages/1/edit`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Validation | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Dialog interactions; labelled controls, Escape/focus return (M/K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### The person behind the perspective. — `/admin/profile`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Validation | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Dialog interactions; labelled controls, Escape/focus return (M/K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### Clear responsibilities, thoughtful access. — `/admin/roles`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Shared quick-draft validation/local success (C/K/M). |
| Validation | Pass | Shared quick-draft validation/local success (C/K/M). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Cancel/confirm local removal; dialog keyboard/focus return (M/K). |
| Delete confirmation | Pass | Explicit local-only removal confirmation; cancellation/reload restore fixtures (M/K). |
| No results | Pass | Unmatched mock filter displays clear-filter state (M/R; shared DataTable). |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### Define a role with a clear purpose. — `/admin/roles/new`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Validation | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Shared quick-draft/navigation dialogs; no persisted save confirmation (K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### Define a role with a clear purpose. — `/admin/roles/1/edit`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Validation | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Shared quick-draft/navigation dialogs; no persisted save confirmation (K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### See every capability in context. — `/admin/permissions`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Validation | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Shared quick-draft/navigation dialogs; no persisted save confirmation (K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### Give your journal a sense of place. — `/admin/settings`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Validation | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Dialog interactions; labelled controls, Escape/focus return (M/K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### Make it unmistakably yours. — `/admin/appearance`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Validation | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Shared quick-draft/navigation dialogs; no persisted save confirmation (K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### Help the right readers find you. — `/admin/seo`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Validation | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Cancel/confirm local removal; dialog keyboard/focus return (M/K). |
| Delete confirmation | Pass | Explicit local-only removal confirmation; cancellation/reload restore fixtures (M/K). |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### A careful route to the inbox. — `/admin/settings/smtp`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Validation | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Dialog interactions; labelled controls, Escape/focus return (M/K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### Small connections, bigger discoveries. — `/admin/tags`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Shared quick-draft validation/local success (C/K/M). |
| Validation | Pass | Shared quick-draft validation/local success (C/K/M). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Cancel/confirm local removal; dialog keyboard/focus return (M/K). |
| Delete confirmation | Pass | Explicit local-only removal confirmation; cancellation/reload restore fixtures (M/K). |
| No results | Pass | Unmatched mock filter displays clear-filter state (M/R; shared DataTable). |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### Give an idea a place to belong. — `/admin/tags/new`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Validation | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Shared quick-draft/navigation dialogs; no persisted save confirmation (K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### Refine the way readers explore. — `/admin/tags/1/edit`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Validation | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Shared quick-draft/navigation dialogs; no persisted save confirmation (K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### Good work starts with good people. — `/admin/users`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Shared quick-draft validation/local success (C/K/M). |
| Validation | Pass | Shared quick-draft validation/local success (C/K/M). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Cancel/confirm local removal; dialog keyboard/focus return (M/K). |
| Delete confirmation | Pass | Explicit local-only removal confirmation; cancellation/reload restore fixtures (M/K). |
| No results | Pass | Unmatched mock filter displays clear-filter state (M/R; shared DataTable). |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### Make space for another perspective. — `/admin/users/new`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Validation | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Shared quick-draft/navigation dialogs; no persisted save confirmation (K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### Make space for another perspective. — `/admin/users/1/edit`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Validation | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Shared quick-draft/navigation dialogs; no persisted save confirmation (K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### Making room for something better. — `/_maintenance`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 503; populated/readable content (C/R/A/N). |
| Loading | N/A | Synchronous server-rendered page; no async data loading. |
| Empty | N/A | Fixed page/directory content, not an editable collection. |
| Error | Pass | Branded server-error fallback at `/_error/500` (C/R/A/N). |
| Success | N/A | Read-only error/maintenance screen; no successful write action. |
| Validation | N/A | No input form on this error/maintenance screen. |
| Disabled | N/A | Navigation links only; no unavailable input action. |
| Confirmation | N/A | No action needing a confirmation dialog. |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | N/A | Public content; no restricted operation. |
| Responsive navigation | Pass | Wrapping navigation/recovery links at all four widths (R/K where interactive). |

### A world of ideas. — `/blog`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | N/A | Synchronous server-rendered page; no async data loading. |
| Empty | Pass | Shared archive empty collection; digital-craft fixture (C/R/A). |
| Error | Pass | Branded server-error fallback at `/_error/500` (C/R/A/N). |
| Success | Pass | Shared newsletter validation/local success (C/K/M). |
| Validation | Pass | Shared newsletter validation/local success (C/K/M). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Consent-choice controls and keyboard search/navigation dialogs (M/K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | Pass | Unmatched query displays no-match state (C/R/A/M). |
| No permission | N/A | Public content; no restricted operation. |
| Responsive navigation | Pass | Public header/drawer/search at all four widths (R/K where interactive). |

### Follow a new thread. — `/search`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | N/A | Synchronous server-rendered page; no async data loading. |
| Empty | Pass | Shared archive empty collection; digital-craft fixture (C/R/A). |
| Error | Pass | Branded server-error fallback at `/_error/500` (C/R/A/N). |
| Success | Pass | Shared newsletter validation/local success (C/K/M). |
| Validation | Pass | Shared newsletter validation/local success (C/K/M). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Consent-choice controls and keyboard search/navigation dialogs (M/K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | Pass | Unmatched query displays no-match state (C/R/A/M). |
| No permission | N/A | Public content; no restricted operation. |
| Responsive navigation | Pass | Public header/drawer/search at all four widths (R/K where interactive). |

### The quiet power of making things with intention — `/blog/the-quiet-power-of-making-things-with-intention`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | N/A | Synchronous server-rendered page; no async data loading. |
| Empty | N/A | Fixed page/directory content, not an editable collection. |
| Error | Pass | Branded server-error fallback at `/_error/500` (C/R/A/N). |
| Success | Pass | Shared newsletter validation/local success (C/K/M). |
| Validation | Pass | Shared newsletter validation/local success (C/K/M). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Consent-choice controls and keyboard search/navigation dialogs (M/K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | N/A | Public content; no restricted operation. |
| Responsive navigation | Pass | Public header/drawer/search at all four widths (R/K where interactive). |

### Different voices. Shared curiosity. — `/authors`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | N/A | Synchronous server-rendered page; no async data loading. |
| Empty | N/A | Fixed page/directory content, not an editable collection. |
| Error | Pass | Branded server-error fallback at `/_error/500` (C/R/A/N). |
| Success | Pass | Shared newsletter validation/local success (C/K/M). |
| Validation | Pass | Shared newsletter validation/local success (C/K/M). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Consent-choice controls and keyboard search/navigation dialogs (M/K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | N/A | Public content; no restricted operation. |
| Responsive navigation | Pass | Public header/drawer/search at all four widths (R/K where interactive). |

### Find your corner of the world. — `/categories`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | N/A | Synchronous server-rendered page; no async data loading. |
| Empty | N/A | Fixed page/directory content, not an editable collection. |
| Error | Pass | Branded server-error fallback at `/_error/500` (C/R/A/N). |
| Success | Pass | Shared newsletter validation/local success (C/K/M). |
| Validation | Pass | Shared newsletter validation/local success (C/K/M). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Consent-choice controls and keyboard search/navigation dialogs (M/K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | N/A | Public content; no restricted operation. |
| Responsive navigation | Pass | Public header/drawer/search at all four widths (R/K where interactive). |

### Follow a new thread. — `/tags`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | N/A | Synchronous server-rendered page; no async data loading. |
| Empty | N/A | Fixed page/directory content, not an editable collection. |
| Error | Pass | Branded server-error fallback at `/_error/500` (C/R/A/N). |
| Success | Pass | Shared newsletter validation/local success (C/K/M). |
| Validation | Pass | Shared newsletter validation/local success (C/K/M). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Consent-choice controls and keyboard search/navigation dialogs (M/K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | N/A | Public content; no restricted operation. |
| Responsive navigation | Pass | Public header/drawer/search at all four widths (R/K where interactive). |

### Maya Chen — `/authors/maya-chen`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | N/A | Synchronous server-rendered page; no async data loading. |
| Empty | N/A | Fixed page/directory content, not an editable collection. |
| Error | Pass | Branded server-error fallback at `/_error/500` (C/R/A/N). |
| Success | Pass | Shared newsletter validation/local success (C/K/M). |
| Validation | Pass | Shared newsletter validation/local success (C/K/M). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Consent-choice controls and keyboard search/navigation dialogs (M/K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | N/A | Public content; no restricted operation. |
| Responsive navigation | Pass | Public header/drawer/search at all four widths (R/K where interactive). |

### Design — `/categories/design`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | N/A | Synchronous server-rendered page; no async data loading. |
| Empty | N/A | Fixed page/directory content, not an editable collection. |
| Error | Pass | Branded server-error fallback at `/_error/500` (C/R/A/N). |
| Success | Pass | Shared newsletter validation/local success (C/K/M). |
| Validation | Pass | Shared newsletter validation/local success (C/K/M). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Consent-choice controls and keyboard search/navigation dialogs (M/K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | N/A | Public content; no restricted operation. |
| Responsive navigation | Pass | Public header/drawer/search at all four widths (R/K where interactive). |

### #Creativity — `/tags/creativity`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | N/A | Synchronous server-rendered page; no async data loading. |
| Empty | N/A | Fixed page/directory content, not an editable collection. |
| Error | Pass | Branded server-error fallback at `/_error/500` (C/R/A/N). |
| Success | Pass | Shared newsletter validation/local success (C/K/M). |
| Validation | Pass | Shared newsletter validation/local success (C/K/M). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Consent-choice controls and keyboard search/navigation dialogs (M/K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | N/A | Public content; no restricted operation. |
| Responsive navigation | Pass | Public header/drawer/search at all four widths (R/K where interactive). |

### Stay curious.Live considered. — `/`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | N/A | Synchronous server-rendered page; no async data loading. |
| Empty | N/A | Fixed page/directory content, not an editable collection. |
| Error | Pass | Branded server-error fallback at `/_error/500` (C/R/A/N). |
| Success | Pass | Shared newsletter validation/local success (C/K/M). |
| Validation | Pass | Shared newsletter validation/local success (C/K/M). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Consent-choice controls and keyboard search/navigation dialogs (M/K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | N/A | Public content; no restricted operation. |
| Responsive navigation | Pass | Public header/drawer/search at all four widths (R/K where interactive). |

### A journal for the things that matter. — `/about`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | N/A | Synchronous server-rendered page; no async data loading. |
| Empty | N/A | Fixed page/directory content, not an editable collection. |
| Error | Pass | Branded server-error fallback at `/_error/500` (C/R/A/N). |
| Success | Pass | Shared newsletter validation/local success (C/K/M). |
| Validation | Pass | Shared newsletter validation/local success (C/K/M). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Consent-choice controls and keyboard search/navigation dialogs (M/K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | N/A | Public content; no restricted operation. |
| Responsive navigation | Pass | Public header/drawer/search at all four widths (R/K where interactive). |

### Thoughtful by design. — `/_styleguide`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | Skeleton and disabled busy button examples (C/R/A). |
| Empty | Pass | Shared EmptyState example (C/R/A). |
| Error | Pass | Branded server-error fallback at `/_error/500` (C/R/A/N). |
| Success | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Validation | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Cancel/confirm local removal; dialog keyboard/focus return (M/K). |
| Delete confirmation | Pass | Explicit local-only removal confirmation; cancellation/reload restore fixtures (M/K). |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | Pass | Permission warning component (C/R/A); no auth implementation. |
| Responsive navigation | Pass | Wrapping navigation/recovery links at all four widths (R/K where interactive). |

### Let’s start a conversation. — `/contact`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | N/A | Synchronous server-rendered page; no async data loading. |
| Empty | N/A | Fixed page/directory content, not an editable collection. |
| Error | Pass | Branded server-error fallback at `/_error/500` (C/R/A/N). |
| Success | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Validation | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Consent-choice controls and keyboard search/navigation dialogs (M/K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | N/A | Public content; no restricted operation. |
| Responsive navigation | Pass | Public header/drawer/search at all four widths (R/K where interactive). |

### Your privacy matters. — `/privacy`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | N/A | Synchronous server-rendered page; no async data loading. |
| Empty | N/A | Fixed page/directory content, not an editable collection. |
| Error | Pass | Branded server-error fallback at `/_error/500` (C/R/A/N). |
| Success | Pass | Shared newsletter validation/local success (C/K/M). |
| Validation | Pass | Shared newsletter validation/local success (C/K/M). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Consent-choice controls and keyboard search/navigation dialogs (M/K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | N/A | Public content; no restricted operation. |
| Responsive navigation | Pass | Public header/drawer/search at all four widths (R/K where interactive). |

### A thoughtful space, shared responsibly. — `/terms`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | N/A | Synchronous server-rendered page; no async data loading. |
| Empty | N/A | Fixed page/directory content, not an editable collection. |
| Error | Pass | Branded server-error fallback at `/_error/500` (C/R/A/N). |
| Success | Pass | Shared newsletter validation/local success (C/K/M). |
| Validation | Pass | Shared newsletter validation/local success (C/K/M). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Consent-choice controls and keyboard search/navigation dialogs (M/K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | N/A | Public content; no restricted operation. |
| Responsive navigation | Pass | Public header/drawer/search at all four widths (R/K where interactive). |

### A clear note about cookies. — `/cookies`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | N/A | Synchronous server-rendered page; no async data loading. |
| Empty | N/A | Fixed page/directory content, not an editable collection. |
| Error | Pass | Branded server-error fallback at `/_error/500` (C/R/A/N). |
| Success | Pass | Shared newsletter validation/local success (C/K/M). |
| Validation | Pass | Shared newsletter validation/local success (C/K/M). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Consent-choice controls and keyboard search/navigation dialogs (M/K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | N/A | Public content; no restricted operation. |
| Responsive navigation | Pass | Public header/drawer/search at all four widths (R/K where interactive). |

### Even good stories need a pause. — `/_error/500`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 500; populated/readable content (C/R/A/N). |
| Loading | N/A | Synchronous server-rendered page; no async data loading. |
| Empty | N/A | Fixed page/directory content, not an editable collection. |
| Error | Pass | Branded server-error fallback at `/_error/500` (C/R/A/N). |
| Success | N/A | Read-only error/maintenance screen; no successful write action. |
| Validation | N/A | No input form on this error/maintenance screen. |
| Disabled | N/A | Navigation links only; no unavailable input action. |
| Confirmation | N/A | No action needing a confirmation dialog. |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | N/A | Public content; no restricted operation. |
| Responsive navigation | Pass | Wrapping navigation/recovery links at all four widths (R/K where interactive). |

### A small detour. — `/_error/418`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 418; populated/readable content (C/R/A/N). |
| Loading | N/A | Synchronous server-rendered page; no async data loading. |
| Empty | N/A | Fixed page/directory content, not an editable collection. |
| Error | Pass | Branded server-error fallback at `/_error/500` (C/R/A/N). |
| Success | N/A | Read-only error/maintenance screen; no successful write action. |
| Validation | N/A | No input form on this error/maintenance screen. |
| Disabled | N/A | Navigation links only; no unavailable input action. |
| Confirmation | N/A | No action needing a confirmation dialog. |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | N/A | Public content; no restricted operation. |
| Responsive navigation | Pass | Wrapping navigation/recovery links at all four widths (R/K where interactive). |

### Refine the way readers explore. — `/admin/categories/7/edit`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Validation | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Dialog interactions; labelled controls, Escape/focus return (M/K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### Make space for another perspective. — `/admin/users/6/edit`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Validation | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Shared quick-draft/navigation dialogs; no persisted save confirmation (K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### Begin a note worth reading. — `/admin/newsletter/campaigns/2/edit`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Validation | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Dialog interactions; labelled controls, Escape/focus return (M/K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

### A place for the right partnership. — `/admin/advertisements/2/edit`

| State | Result | Applicability / evidence |
|---|---|---|
| Default | Pass | HTTP 200; populated/readable content (C/R/A/N). |
| Loading | Pass | `?state=loading`; announced skeleton (C/R/A). |
| Empty | Pass | `?state=empty`; safe empty-data presentation (C/R/A). |
| Error | Pass | `?state=error`; readable error and retry (C/R/A). |
| Success | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Validation | Pass | Main preview form; inline validation and local-only success (C/M/K). |
| Disabled | Pass | Preview fields/actions disabled without JS; unavailable backend actions disclosed (C/N/M). |
| Confirmation | Pass | Dialog interactions; labelled controls, Escape/focus return (M/K). |
| Delete confirmation | N/A | No delete action on this screen. |
| No results | N/A | No main-content search/filter; shared search leads to its own results screen. |
| No permission | Pass | 403 example at `/admin/no-permission`; preview gate tested; actual authorization deferred (C/R/A). |
| Responsive navigation | Pass | Admin collapse/drawer and menus at all four widths (R/K where interactive). |

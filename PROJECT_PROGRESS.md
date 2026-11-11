# Project progress

Completed UI milestones mean **mock-data interfaces only**, not completed backend features. Phase 1 must be reviewed before persistence or business logic starts.

| Phase | Milestone | Module | Status | Notes |
|---|---|---|---|---|
| 0 | M0 | Project Inspection & Setup | Completed | Symfony 8.1; standalone CSS build and full QA verified |
| 1 | M1.1 | Design System & Components | Completed | Mock UI only; local fonts/artwork, keyboard and responsive checks |
| 1 | M1.2 | Public Layout & Homepage | Pending | |
| 1 | M1.3 | Blog Listing & Details | Pending | |
| 1 | M1.4 | Other Public Pages & Errors | Pending | |
| 1 | M1.5 | Admin Shell & Dashboard | Pending | |
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

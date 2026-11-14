# QA consolidation (M15)

## Test and static-analysis status

- Latest local verification (2026-11-14, M15 consolidation): `composer qa` passed with **356 tests / 22,195 assertions** on PHP 8.5.12 and the local MariaDB test database. Assertion totals vary with generated fixture data. Evidence: `/tmp/opencode/blog-m15-qa.log` (local, not a repository artifact).
- The pipeline runs Composer validation, container/Twig/YAML lint, standalone Tailwind, PHPStan (level 6 across `src`/`tests`/`migrations`), max-level PHPStan for `src/Service`/`src/Security`, PHP-CS-Fixer dry run, PHPUnit, dependency audit and the no-Node tooling guard.
- PHPStan max level is clean on the business/security layer via `phpstan-max.neon`; the full codebase (including intentionally dynamic test container access) remains at level 6.

## Coverage

**Historical measurement only (2026-11-12, before review remediation):** a locally built PCOV extension was used without adding a project/runtime dependency. PCOV/Xdebug is not currently loaded and that extension artifact is unavailable; the percentages below have not been recalculated for the current source. Command to rerun with a compatible coverage driver:

```bash
php -d extension=/path/to/pcov.so \
  vendor/bin/phpunit --coverage-clover var/coverage.xml
```

Historical results parsed from Clover:

| Scope | Line coverage |
|---|---:|
| `src/Service` | **90.6%** (1029/1136 statements) |
| `src/Security` | **81.3%** (135/166 statements) |
| Whole `src` | **87.6%** (4261/4864 statements) |

The historical line results exceeded the ≥70% service/security target, but do not establish current coverage or correctness. Refresh coverage before release; branch/path coverage is not enforced.

## Review-regression inventory

| Review area | Caller-level regression suites |
|---|---|
| Defaults and permissions/TOTP | `SettingsDefaultsRegressionTest`, `AuthorizationRegressionTest` |
| Atomic article/taxonomy saves | `ArticleSaveRegressionTest` |
| Media references/cleanup/decoding | `MediaSafetyRegressionTest` |
| Public eligibility, taxonomy hydration and ranking | `PublicDiscoveryRegressionTest` |
| HTTP → queue → view consumer | `AsyncViewRegressionTest` |
| Cached sitemaps and page saves | `SitemapPageRegressionTest` |
| Newsletter ledger, retries, bounded reads and saved SMTP | `NewsletterDeliveryRegressionTest`, `SettingsMailDeliveryRegressionTest`, `NewsletterLedgerMigrationTest` |
| Scoped/streamed CSV and warm-cache advertisement dates/CSP | `AnalyticsTest`, `AdvertisementTest`, `SecurityHardeningTest` |
| Complete runtime controls and rejected menu graphs | `SettingsMenuValidationRegressionTest`, `SettingsAndMenusTest` |
| Entropy, breach lookup privacy/outages and all password callers | `PasswordPolicyRegressionTest`, `AuthenticationTest`, `AdminManagementTest` |

Injected failures assert persistence/rollback or later-flush isolation, not merely that an exception occurred. Newsletter migration tests execute against isolated suffixed tables on a separate connection; they do not prove a full fresh-host migration run. Mail and queue fixtures exercise the real application validators/services/consumer command while replacing external HTTP/SMTP/broker boundaries.

## Functional and UI sweeps

- `UiRouteCrawlerTest` discovers HTML GET `front_*`/`admin_*` routes (excluding machine endpoints, exports, token links and the profile redirect), generates realistic parameters, crawls internal links, and checks expected statuses, landmarks, headings, unique IDs, labels, ARIA, fragments and preview-form safeguards. Dedicated suites cover the excluded endpoint types.
- `PermissionMatrixTest` checks each default role against permitted/forbidden admin routes.
- `PerformanceTest` enforces query budgets with the full demo dataset (public listing ≤30, article ≤40, key admin lists ≤45).
- **Historical browser smoke checks (2026-11-12):** dropdown/search navigation, public/admin mobile drawers, quick-create and quick-draft; zero console errors were recorded then. These checks predate the remediated settings/forms/scripted-ad CSP and have not been rerun. No current Lighthouse ≥90 claim is made.

## Known limitations

- Coverage is statement/line based; branch/path coverage is not enforced.
- PHPStan max level covers the service/security layer rather than every test helper; full-tree analysis stays at level 6.
- Browser checks are manual agent-driven smoke checks, not a CI Playwright suite.
- The quick-draft modal remains an explicit local preview; real creation flows through the article editor.
- Fresh clone + fresh MySQL 8.4 QA and independent Ubuntu deployment remain open acceptance gates. The old 221-test MySQL run predates remediation. Missing development migration `Version20261113124023` is retained as metadata; its authentic reversal code is unavailable. See `database.md` for reconciled archives and rollback limitations.
- Live SMTP/ad networks/breach-service egress, separate Doctrine/Redis worker processes, shared cache invalidation, concurrency and 100k-view/large-audience scale benchmarks remain independently unverified. The old analytics timing is not a current performance result.
- Existing Flysystem legacy-config and nested menu-macro deprecation notices remain visible. See `../PROJECT_PROGRESS.md` for the current remediation ledger rather than treating historical milestone completion as production certification.

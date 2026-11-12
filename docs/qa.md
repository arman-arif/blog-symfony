# QA consolidation (M15)

## Test and static-analysis status

- `composer qa` passes: composer validation, container/Twig/YAML lint, Tailwind build, PHPStan (level 6 across `src`/`tests`), max-level PHPStan for `src/Service`/`src/Security`, PHP-CS-Fixer dry run, PHPUnit, `composer audit` and the tooling check.
- PHPUnit: **220 tests / 20,415 assertions**.
- PHPStan max level is clean on the business/security layer via `phpstan-max.neon`; the full codebase (including intentionally dynamic test container access) remains at level 6.

## Coverage

A locally built PCOV extension was used; no project or runtime dependency was added. Command shape:

```bash
php -d extension=/path/to/pcov.so \
  vendor/bin/phpunit --coverage-clover var/coverage.xml
```

Results parsed from Clover:

| Scope | Line coverage |
|---|---:|
| `src/Service` | **90.6%** (1029/1136 statements) |
| `src/Security` | **81.3%** (135/166 statements) |
| Whole `src` | **87.6%** (4261/4864 statements) |

Class coverage is 44.4% because many classes are final DTO/entity-like services with no individually executable methods; methods are 76.7% covered and the line targets exceed the ≥70% requirement.

## Functional and UI sweeps

- `UiRouteCrawlerTest` discovers every GET `front_*`/`admin_*` route, generates realistic parameters, crawls internal links, and checks status codes, landmarks, headings, unique IDs, label targets, ARIA references, fragments, forms and external-source policy.
- `PermissionMatrixTest` checks each default role against permitted/forbidden admin routes.
- `PerformanceTest` enforces query budgets with the full demo dataset (public listing ≤30, article ≤40, key admin lists ≤45).
- Browser smoke checks (Playwright, no Node build step): desktop header dropdown, search modal and result navigation, mobile public drawer, admin mobile drawer, admin quick-create dropdown and quick-draft modal. Public and admin pages produced **0 console errors** under the CSP.
- Modal/dropdown behavior was verified interactively; no Panther/ChromeDriver dependency was added because the project avoids Node tooling and Playwright covered the same smoke paths.

## Known limitations

- Coverage is statement/line based; branch/path coverage is not enforced.
- PHPStan max level covers the service/security layer rather than every test helper; full-tree analysis stays at level 6.
- Browser checks are manual agent-driven smoke checks, not a CI Playwright suite.
- The quick-draft modal remains an explicit local preview; real creation flows through the article editor.

# Architecture

## Inspection and stack lock

The initial repository contained only `PROMPT.md` and `IMPLEMENTATION_PLAN.md`; no existing application or user code was replaced. PHP CLI is 8.5.12, Composer 2.10.3. Required extensions (`intl`, `pdo_mysql`, `gd`, `sodium`, `zip`, `fileinfo`, DOM, mbstring) are present. The installed database client is MariaDB 12.3.3, **not** the required MySQL 8 server. MySQL-backed verification is reserved for M2/M3.

Symfony **8.1** is the latest compatible stable branch verified through Composer. Production requires PHP 8.4+, while PHP 8.3 deployments must use the separately planned Symfony 7.4 LTS branch; this project does not silently claim PHP 8.3 compatibility. PHPUnit 12 supports the chosen PHP minimum.

## UI-first foundation

Packages are installed individually with Flex instead of the broad `webapp` pack, to avoid unused infrastructure and frontend scaffolding. Doctrine, Security, Forms, Messenger and persistence arrive only in their planned milestones. AssetMapper serves local ES modules; TailwindBundle downloads and runs the standalone **v3.4.17** executable. No JavaScript package manager or runtime is used.

The editor choice is **Quill 2**, imported and vendored by PHP's `importmap:require` in M1.6. Its native editing/undo behavior is progressively enhanced with accessible vanilla controls for tables and embeds. Chart.js will likewise be vendored via importmap. Fonts and artwork are local, with source licenses recorded.

## Directory boundaries

- `src/Controller/Front`, `Admin`, `Api`: thin HTTP adapters.
- `src/Mock`: immutable, realistic UI fixtures and preview querying during M1.
- `src/Service/<Module>`, `Repository`, `Entity`, `Form`, `Security/Voter`: added after UI review.
- `templates/front`, `admin`: separate layouts using `templates/components`.
- `assets/js/core`, `components`, `admin`: data-attribute progressive enhancement.
- `assets/styles`, `fonts`, `images`, `icons`: locally served static resources.
- `tests/Unit`, `Integration`, `Functional`: automated coverage alongside milestones.
- `tools`: PHP-only local development and checks; `docs`: technical handoff.

Future persistence uses normalized MySQL, attribute-mapped Doctrine entities and migrations. Business rules live in services, database querying in repositories, granular authorization in voters. Twig autoescaping stays enabled.

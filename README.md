# Fieldnotes

A professional editorial blogging platform and CMS, developed UI-first with Symfony 8.1, Twig, Tailwind CSS 3.4 and vanilla JavaScript. **Implementation is in progress, not production-ready.** See `PROJECT_PROGRESS.md`.

## Local development

Requires PHP 8.4+, Composer, intl, mbstring, DOM, curl and zip. MySQL 8 and additional extensions will be required during backend integration.

```sh
composer install
composer css
composer dev
```

Open `http://127.0.0.1:8000`. The development server binds to loopback only. Run `composer css-watch` separately while editing styles; `composer qa` runs validation, linting, CSS compilation, PHPStan, formatting, PHPUnit, dependency audit and the asset-tooling check. `symfony serve` is an alternative.

No JavaScript runtime or package manager is needed. TailwindBundle downloads the pinned standalone CLI into ignored `var/tailwind`; compiled CSS and production assets are generated, not committed. Real secrets belong in ignored `.env.local` or the Symfony secrets vault.

Requirements: `PROMPT.md`, `IMPLEMENTATION_PLAN.md`. Architecture, module map and roadmap: `docs/`.

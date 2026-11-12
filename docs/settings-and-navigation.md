# Settings, appearance, navigation and admin operations

## Persisted settings

`DatabaseSettingsService` implements `SettingsServiceInterface` on top of `app_setting`:

- defaults come from `DefaultSettingsService`; unknown/missing keys return defaults;
- entries are cached for one hour in the tag-aware `content` pool and invalidated on save;
- sensitive values (SMTP password) are encrypted with `SettingsEncryption` (libsodium secretbox), using `SETTINGS_ENCRYPTION_KEY` when set and falling back to `APP_SECRET` for local development;
- the SMTP password is never rendered back into the form; leaving it blank preserves the saved value;
- settings changes are recorded in the activity log.

### Groups and permissions

| Group | Route | Permission |
|---|---|---|
| General | `/admin/settings` | `settings.general.manage` |
| Appearance | `/admin/appearance` | `settings.appearance.manage` |
| SEO | `/admin/seo` | `settings.seo.manage` |
| Email/SMTP | `/admin/settings/smtp` | `settings.smtp.manage` |
| Custom code | `/admin/settings/code` | `settings.code.manage` |

The SMTP and custom-code screens are restricted to Super Administrators by default; authors/editors receive 403.

### SMTP

`SettingsMailer` implements `MailerInterface`:

- `mail.transport=env` delegates to the configured `MAILER_DSN` (tests and local development use this path);
- `smtp` builds a Symfony Mailer transport from host, port, username, decrypted password and encryption;
- `sendmail` uses `sendmail://default`;
- the SMTP settings screen can send a test email to a validated recipient.

## Appearance

Appearance settings drive the public site without a deploy:

- primary/accent colours are converted to CSS variables via `appearance_css()`; `tailwind.config.js` already consumes those variables;
- default light/dark/system theme;
- logo, favicon, header announcement, footer introduction and social links;
- header/footer visibility;
- homepage section order and visibility (`hero`, `latest`, `favorites`, `categories`, `stories`, `recommended`, `authors`) via `front/home/_<section>.html.twig` partials;
- article sidebar widget selection (table of contents, about, advertisement, newsletter).

Custom CSS/JS is only available through `settings.code.manage` and renders in the public layout. CSP hardening for this capability is part of M14.

## Maintenance mode

`MaintenanceListener` returns a 503 page with `Retry-After` and `no-store` while `maintenance.enabled` is true. `/login`, `/admin`, assets, uploads and the profiler remain reachable; authenticated users with `admin.access` bypass maintenance.

## Menus

The menu builder at `/admin/menus` supports header/footer locations, nested items via a parent selector, item editing/removal and drag reorder (`assets/js/menu.js` posts the new order to `admin_menu_reorder` with CSRF). `MenuService` resolves custom paths/URLs, page/category/article slugs and caches trees with the `menus` tag; the public header/footer fall back to static links when no menu exists.

## Admin operations

- **Users:** search/filter/pagination, create with temporary password, role assignment, activation/verification toggles, edit, last-Super-Administrator protection and `UserVoter` enforcement.
- **Roles:** CRUD, permission assignment, system role handling; Super Administrator is kept complete.
- **Permissions:** role × permission matrix with CSRF; Super Administrator cells are disabled and always granted.
- **Pages:** real CRUD with sanitized HTML, publish timestamps, public `/pages/{slug}` route, sitemap/SEO integration and activity logging.
- **Activity:** read-only paginated audit log with filters and JSON context.
- **Profile:** `/admin/profile` redirects to the real `/profile` account screen from M4.

## Verification

`SettingsAndMenusTest` and `AdminManagementTest` cover persistence, encryption, public-site changes, maintenance bypass, SMTP password masking, sensitive permissions, homepage order/sidebar widgets, custom code, menu nesting/reorder, page CRUD/route, user role assignment/deactivation, last-Super-Admin protection, role permissions, and activity rendering.

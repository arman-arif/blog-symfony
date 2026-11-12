# Security and performance hardening (M14)

## Response headers and CSP

`SecurityHeadersListener` adds to every response:

- `Content-Security-Policy` with a per-request nonce: `default-src 'self'`, `script-src 'self' 'nonce-…' https://www.googletagmanager.com`, `style-src 'self' 'unsafe-inline'`, `img-src 'self' data: https:`, `connect-src` limited to self and Google Analytics, frames limited to YouTube/Vimeo, `object-src 'none'`, `frame-ancestors 'self'`, `base-uri 'self'`, `form-action 'self'`.
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()`
- `X-Frame-Options: SAMEORIGIN`
- `Cross-Origin-Opener-Policy: same-origin`
- `Strict-Transport-Security` on HTTPS requests

Import maps, custom JavaScript and JSON-LD receive the same nonce. GA4 is only emitted after the `fieldnotes_consent=all` cookie and its inline bootstrap is nonced. Tailwind-style inline styles remain allowed through `style-src 'unsafe-inline'`; scripts never use `unsafe-inline`.

## Authentication and passwords

- Login throttling: 5 attempts per minute (`security.firewall.login_throttling`).
- Password policy: minimum 12 characters and rejection of common passwords (`StrongPassword` constraint) across registration, reset, password change and admin-created users.
- Password hashing remains Symfony `auto` (argon2id/bcrypt as available); tests use low-cost hashing.
- CSRF protection is enabled for the login form, logout, forms, API mutations and the permission matrix.
- Inactive accounts are rejected by `UserChecker`; full authentication is required for profile and saved stories.
- TOTP secrets are encrypted at rest through `UserTotpSecretListener`/`TotpSecretCipher` using the settings encryption key. Secrets are decrypted after Doctrine load for the two-factor provider and re-encrypted on flush. Legacy plaintext secrets are read transparently and encrypted on the next persisted change.
- Existing rate limits cover autosave (12/min), comment submission (5/10 min), comment reports (3/hour) and newsletter subscriptions (5/hour).

## Caching and data access

- Anonymous public GET responses receive `public, max-age=60, s-maxage=300, stale-while-revalidate=60` and `Vary: Cookie`; session-backed or authenticated responses are private/no-store by default. Admin responses are never shared.
- Content/report caches are tag-aware and invalidated on writes (`articles`, `homepage`, `menus`, `ads`, `analytics`, `settings`).
- Public catalog dictionaries/queries eager-load author, image, categories and tags. `PerformanceTest` asserts bounded query counts: blog listing ≤30, article page ≤40, and key admin lists ≤45 queries with the full demo dataset.

## Verification

`SecurityHardeningTest` checks CSP nonce consistency, security headers, public vs admin cache policy, weak-password rejection and 2FA ciphertext at rest. `PerformanceTest` enforces query budgets. The full suite also covers throttled/CSRF forms and the role/permission matrix.

## Residual risks / follow-ups

- `style-src 'unsafe-inline'` is retained for inline component styles and charts; tighten with hashes/nonces in a future pass.
- Administrators can store trusted HTML/AdSense creatives by design; only grant `ad.manage` to trusted staff.
- Custom CSS/JS is Super-Admin-only but executes on public pages; review changes and keep backups.
- Production should terminate TLS at the proxy, keep HSTS enabled, and set `SETTINGS_ENCRYPTION_KEY` to a stable secret independent of `APP_SECRET`.

# Security and performance hardening (M14)

## Response headers and CSP

`SecurityHeadersListener` adds to every response:

- `Content-Security-Policy` with a per-request nonce and `script-src 'strict-dynamic'`. Ordinary pages limit connections to self/Google Analytics and frames to self/YouTube/Vimeo. Pages rendering trusted scripted ads allow HTTPS connections/frames for those creatives; eval and inline handlers remain blocked. Styles retain `'unsafe-inline'`; images allow self/data/HTTPS; objects are disabled and base URI/forms/frame ancestors are same-origin.
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()`
- `X-Frame-Options: SAMEORIGIN`
- `Cross-Origin-Opener-Policy: same-origin`
- `Strict-Transport-Security` on HTTPS requests

Import maps, custom JavaScript, trusted ad scripts and JSON-LD receive the same fresh response nonce. GA4 is only emitted after the `fieldnotes_consent=all` cookie; both its bootstrap and external loader are nonced. Live ad-network/browser compatibility has not been independently reverified after remediation.

## Authentication and passwords

- Login throttling: 5 attempts per minute (`security.firewall.login_throttling`).
- Password policy: 12–4096 characters, Symfony `PasswordStrength` score at least medium and real `NotCompromisedPassword` validation across registration, reset, profile changes, admin forms/direct service calls and bootstrap administration. Type/length/entropy checks run sequentially before network work. Blank optional admin edits preserve the password; creation requires one.
- Breach lookup sends only five uppercase SHA-1 characters to the HTTPS Pwned Passwords range API with `Add-Padding: true`, not the password or full hash. The dedicated HTTP client has 3-second inactivity/5-second total limits and no redirects. Lookups fail closed: outages return a correctable form/CLI error, not acceptance or an HTTP 500. Operators must permit outbound HTTPS to `api.pwnedpasswords.com`; existing login passwords are not checked or changed by this policy. Tests replace HTTP responses, not the real validator, and never send fixture passwords to the network.
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

`SecurityHardeningTest` checks CSP nonce consistency, security headers, public vs admin cache policy, weak-password rejection and 2FA ciphertext at rest. `PasswordPolicyRegressionTest` exercises repetitive/breached passwords, lookup outages, hash-prefix privacy, all password-setting callers, accepted changes and rejected-write isolation. `PerformanceTest` enforces demo-data query budgets, not scale targets. See `qa.md` and the current remediation ledger in `../PROJECT_PROGRESS.md` for evidence and open runtime checks; historical browser/coverage claims are not current certification.

## Residual risks / follow-ups

- `style-src 'unsafe-inline'` is retained for inline component styles and charts; tighten with hashes/nonces in a future pass.
- Administrators can store trusted HTML/AdSense creatives by design; only grant `ad.manage` to trusted staff.
- Custom CSS/JS is Super-Admin-only but executes on public pages; review changes and keep backups.
- Production should terminate TLS at the proxy, keep HSTS enabled, and set `SETTINGS_ENCRYPTION_KEY` to a stable secret independent of `APP_SECRET`.

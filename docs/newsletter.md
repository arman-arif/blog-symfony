# Newsletter and email

## Subscriptions

`POST /newsletter/subscribe` powers both the homepage and footer forms.

- CSRF token `newsletter_subscribe` and a per-IP rate limit (5/hour).
- Emails are normalized to lowercase and validated server-side.
- `newsletter.double_opt_in` controls the flow. With double opt-in, the subscriber enters `pending` and receives a confirmation email; without it, the subscriber is immediately `confirmed` and receives a welcome email.
- Existing confirmed subscribers are not duplicated. Pending subscribers receive a fresh confirmation; unsubscribed addresses can opt in again.

Tokens are opaque 64-hex random values stored on the subscriber. Confirmation and unsubscribe links are:

- `GET /newsletter/confirm/{token}`
- `GET /newsletter/unsubscribe/{token}`
- `POST /newsletter/unsubscribe/{token}` for the advertised one-click unsubscribe flow (the opaque token authorizes it; no session or CSRF token is required).

Confirmation and unsubscribe emails include `List-Unsubscribe` and `List-Unsubscribe-Post: List-Unsubscribe=One-Click` headers.

## Admin

`/admin/newsletter` (permission `newsletter.manage`):

- Subscriber list with status tabs/counts, email search, pagination.
- Activate/deactivate actions (CSRF-protected, audited).
- Streamed CSV export with id, email, status and timestamps.
- Campaign list and history with delivery statistics.
- Campaign editor: subject, sanitized rich HTML, draft/scheduled status, schedule time.
- Queued/completed campaigns are read-only. Rejected edits bind to isolated copies and cannot leak into later database writes. Schedule input is UTC.
- Test send to one address; real send queues batches of 50 confirmed subscribers.

## Campaign delivery

`NewsletterCampaignService`:

- Sanitizes campaign HTML with the article content sanitizer.
- Renders campaign emails through `emails/newsletter.html.twig`, which wraps the content in the brand layout and applies `inline_css` for email-client compatibility.
- Queueing commits a unique `NewsletterDelivery` record per recipient and the `queued` campaign state before dispatching `SendNewsletterCampaignMessage` batches. Audience selection uses keyset chunks of 50 and a maximum subscriber id; it does not hydrate the entire list. Preparing the audience is still synchronous database work, not SMTP delivery.
- Workers recheck each recipient under a subscriber row lock. Missing, pending, unsubscribed or otherwise ineligible recipients are `skipped`; ids outside the committed audience are ignored. Subscriber deletion retains ledger identity and accounting without retaining a copied email address.
- The batch worker calls the mail transport directly rather than enqueueing another email job. `sent` means **transport acceptance**, not enqueueing or inbox delivery. A transport exception is recorded as `failed` without aborting the remaining recipients or automatically retrying that SMTP call.
- A recipient is committed as `sending` immediately before SMTP. Recent interrupted attempts are left alone. After 15 minutes (or if the attempt timestamp is missing), recovery marks them `uncertain` without resending. SMTP and database commits cannot be atomic: an accepted message whose final database update fails must not be blindly replayed.
- Campaign row locks serialize ledger/counter changes across hosts. Replayed/overlapping batches skip completed or already-started recipients. Repeated queue requests do not rebuild the audience or reset statistics.
- `stats` contains `total`, `pending`, `sending`, `sent`, `failed`, `skipped` and `uncertain`. A campaign remains `queued`/`sending` until all recipients are terminal, then becomes `sent` or `failed` if any failures/uncertainties occurred. Its existing `sentAt` field records completion of processing, including unsuccessful campaigns.

`SendScheduledNewsletterCampaignsMessage` runs every minute from `ArticlePublishingSchedule` and queues due campaigns, rechecking the schedule after locking. It also redispatches pending ledger rows for unfinished campaigns, recovering a lost broker dispatch after the database commit. Duplicate recovery messages are harmless to delivery accounting but can increase queue traffic during a prolonged backlog. Production requires the `scheduler_default` and `async` Messenger workers.

### Failure recovery

- Start/restart both workers and inspect `messenger:failed:show` plus application logs. Retrying a failed **batch** is safe for committed recipients; it cannot resend terminal `sent`, `failed`, `skipped` or `uncertain` rows.
- Investigate `uncertain` rows against SMTP/provider logs; do not reset ledger states or counters to force a resend. There is no automatic or administrative resend workflow for an uncertain recipient. Any deliberately new campaign requires operator review of its audience to avoid duplicates.
- Generic transactional `SendEmailMessage` jobs retain Messenger's retry behavior; the campaign ledger does not cover password-reset/confirmation/test emails.
- Before upgrading, stop workers and reconcile database migration history. This workspace's development database contains the unavailable `Version20261113124023` and an older, incompatible `newsletter_delivery` table. **Do not apply the new ledger creation migration there blindly**, mark it executed, or drop the old data to bypass the mismatch. Back up and reconcile that schema/history first.

## Configuration

| Setting | Default |
|---|---|
| `newsletter.double_opt_in` | true |
| `mail.from_address` / `mail.from_name` | shared sender address/display name |

The rate limit is `newsletter_subscribe` in `config/packages/rate_limiter.yaml`.

`SettingsTransport` decorates the framework transport used by both Mailer and its Messenger handler. Saved `mail.transport` selects `env` (the configured `MAILER_DSN`), `smtp` or `sendmail` at delivery time; SMTP credentials are not serialized into queued messages. Changes invalidate the shared settings cache and scalar runtime reads avoid stale managed settings within a batch. Multi-host deployments must use a shared cache/invalidation strategy; separate filesystem caches can retain old settings until their one-hour TTL.

SMTP `mail.encryption=tls` requires TLS (`require_tls`); `ssl` selects implicit TLS (`smtps`); the empty value explicitly disables automatic TLS. Certificate verification remains enabled. Sender address/name use the shared mail settings. TLS/authentication failure, provider acceptance and credential changes still require a live-SMTP deployment check.

CSV export streams scalar rows in chunks of 500, with an id high-water mark and spreadsheet-formula neutralization for dangerous email prefixes. It does not hydrate the entire subscriber list or buffer the full CSV in application memory.

## Tests

`NewsletterTest`, `NewsletterDeliveryRegressionTest` and `SettingsMailDeliveryRegressionTest` cover opt-in/one-click unsubscribe, validation/rate limiting, admin status and streamed CSV across chunk boundaries, actual Messenger campaign/email consumers, saved SMTP/TLS selection and updates, recipient eligibility/deletion, replay/partial progress, recovery, accounting, rejected edits and injected audience/post-SMTP database failures.

The external SMTP boundary is controlled in tests. A separate Doctrine/Redis worker process, live SMTP/inbox delivery, multi-host concurrency and large-audience/backlog stress tests have not been independently verified during remediation; passing these tests does not establish production readiness.

# Newsletter and email

## Subscriptions

`GET/POST /newsletter/subscribe` powers both the homepage and footer forms.

- CSRF token `newsletter_subscribe` and a per-IP rate limit (5/hour).
- Emails are normalized to lowercase and validated server-side.
- `newsletter.double_opt_in` controls the flow. With double opt-in, the subscriber enters `pending` and receives a confirmation email; without it, the subscriber is immediately `confirmed` and receives a welcome email.
- Existing confirmed subscribers are not duplicated. Pending subscribers receive a fresh confirmation; unsubscribed addresses can opt in again.

Tokens are opaque 64-hex random values stored on the subscriber. Confirmation and unsubscribe links are:

- `GET /newsletter/confirm/{token}`
- `GET /newsletter/unsubscribe/{token}`

Confirmation and unsubscribe emails include `List-Unsubscribe` and `List-Unsubscribe-Post: List-Unsubscribe=One-Click` headers.

## Admin

`/admin/newsletter` (permission `newsletter.manage`):

- Subscriber list with status tabs/counts, email search, pagination.
- Activate/deactivate actions (CSRF-protected, audited).
- Streamed CSV export with id, email, status and timestamps.
- Campaign list and history with delivery statistics.
- Campaign editor: subject, sanitized rich HTML, draft/scheduled status, schedule time.
- Test send to one address; real send queues batches of 50 confirmed subscribers.

## Campaign delivery

`NewsletterCampaignService`:

- Sanitizes campaign HTML with the article content sanitizer.
- Renders campaign emails through `emails/newsletter.html.twig`, which wraps the content in the brand layout and applies `inline_css` for email-client compatibility.
- Queues `SendNewsletterCampaignMessage` batches through Messenger. The handler sends each email, records sent/failed counts and never aborts the batch on a single delivery failure.
- Campaign status becomes `sent` when queued; `stats.sent` and `stats.failed` are updated asynchronously.

`SendScheduledNewsletterCampaignsMessage` runs every minute from `ArticlePublishingSchedule` (stateful scheduler) and queues any `scheduled` campaign whose `scheduledAt` has passed. Production requires the `scheduler_default` and `async` Messenger workers.

## Configuration

| Setting | Default |
|---|---|
| `newsletter.double_opt_in` | true |
| `newsletter.from_address` / `from_name` | mail settings |

The rate limit is `newsletter_subscribe` in `config/packages/rate_limiter.yaml`.

## Tests

`NewsletterTest` covers double opt-in confirmation and unsubscribe, single opt-in, invalid addresses, burst rate limiting, admin search/status/CSV, campaign draft/test send/batch delivery, and scheduled campaign queueing.

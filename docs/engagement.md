# Comments and reader engagement

## Comments

Comments are persisted as `Comment` entities with statuses `pending`, `approved`, `rejected` and `spam`. Public pages show approved comments only. Threaded replies are limited by `comments.max_depth` (default 3); replies may target approved comments on the same article.

### Submission rules

- Article comments must be enabled (`article.allowComments`) and the global `comments.enabled` setting must be on.
- Anonymous comments require `comments.anonymous` (default on). Registered readers use their account identity.
- Guest comments require a name and email address; emails are never displayed.
- CSRF is enforced by the Symfony form.
- A hidden start timestamp rejects submissions faster than `comments.min_seconds` (default 3) and older than one hour.
- A honeypot field (`website`) rejects obvious bots.
- A rate limiter (`comment_submit`, 5 per 10 minutes per IP) limits bursts.
- Link count is capped by `comments.max_links` (default 2).
- Blocked words from `comments.blocked_words` are rejected.
- `SpamCheckerInterface` is the extension point for Akismet/Turnstile-like adapters; the default `NullSpamChecker` does nothing.

Trusted commenters (users with `comment.moderate`, or the article author) are auto-approved when `comments.auto_approve_trusted` is on; everyone else enters the moderation queue.

### Notifications

A new pending comment dispatches a `new_comment` email to the article author through Symfony Mailer. Mailer messages use the async Messenger transport in production, so a worker is required. Failures are logged without losing the comment.

### Moderation

`/admin/comments` provides status tabs, search, pagination and inline approve/reject/spam actions. Moderators can edit content or delete (soft) comments. `CommentVoter` allows editors/administrators to moderate any comment and authors to moderate comments on their own articles. Every action is CSRF-protected and audited.

### Public endpoints

| Method | Path | Purpose |
|---|---|---|
| POST | `/blog/{slug}/comments` | Submit comment or reply |
| POST | `/blog/{slug}/comments/{id}/report` | Flag an approved comment |

## Likes, bookmarks and saved stories

- `POST /blog/{slug}/like` toggles the current user's like. `ArticleLike` has a unique `(article,user)` constraint; the denormalized `article.likeCount` is kept in sync.
- `POST /blog/{slug}/bookmark` toggles a bookmark. `ArticleBookmark` has the same unique constraint.
- Both endpoints require an authenticated user, a valid `engagement` CSRF token, and return JSON `{active, count?}`. Anonymous requests receive `401` with a login URL.
- `GET /saved` lists the authenticated reader's saved published stories.

The article page renders real initial states (`aria-pressed`) and updates them with these JSON endpoints; no localStorage preview remains.

## Article views

`RecordArticleViewMessage` is dispatched asynchronously from the article page. `RecordArticleViewHandler`:

- ignores bot/crawler user agents;
- records at most one view per visitor hash per 24 hours;
- stores the visitor hash (salted with the kernel secret) and referrer in `ArticleView`;
- increments the denormalized `article.viewCount`.

Requires the async Messenger worker in production. Raw views are pruned/rolled up in M11.

## Settings

| Key | Default |
|---|---|
| comments.enabled | true |
| comments.anonymous | true |
| comments.max_depth | 3 |
| comments.auto_approve_trusted | true |
| comments.min_seconds | 3 |
| comments.max_links | 2 |
| comments.blocked_words | [] |

Settings are read through `SettingsServiceInterface`; M13 persists them in the database.

## Security notes

Comment text is rendered escaped (no raw HTML). CSRF tokens protect submission, reporting, moderation and engagement. IP addresses are hashed before storage. Rate limits are keyed by IP. Email delivery is asynchronous and failure-tolerant.

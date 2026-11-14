# Analytics and reporting

## Data pipeline

Analytics CSV uses a streamed output response, retaining the report's maximum 366 dates and top ten articles and its authenticated owner scope. Spreadsheet-formula titles are neutralized, exports are private/no-store, and Nginx buffering is disabled. It is a bounded report export, not a full-catalog dump.

1. `RecordArticleViewMessage` stores de-duplicated raw views (one per visitor hash per 24 hours, bots ignored).
2. `AnalyticsRollupService::aggregateRecent($days)` reads raw `article_view` rows grouped by article/day and upserts `article_daily_stat` with the **set** daily total, making repeated runs idempotent. It uses MySQL/MariaDB `ON DUPLICATE KEY UPDATE`.
3. Raw views older than 30 days are pruned. Daily rollups are retained.
4. `AggregateAnalyticsMessage` runs hourly from the stateful Symfony Scheduler; `app:analytics:rollup` provides manual/backfill control (`--days`, `--retention`).

Analytics pages and the dashboard read only `article_daily_stat`, never raw views.

## Dashboard

`/admin` shows real:

- last-30-day views from daily rollups;
- published stories, active editorial users and reader conversations (with pending count);
- 7-day views chart and 30-day category share;
- five most recently updated articles;
- five latest comments with moderation status;
- five latest activity entries;
- status counts for drafts, review, scheduled and published.

## Reporting

`/admin/analytics` (`analytics.view`) accepts `from`/`to` dates (defaults: last 30 days, maximum 366 days, invalid values fall back, reversed ranges are swapped):

- daily and monthly view charts;
- most viewed articles with lifetime likes, approved comments, bookmarks, engagement and engagement rate;
- author performance, popular categories and popular tags;
- total views, published stories, confirmed subscribers and engagement rate;
- CSV export of daily views plus the article table.

Reports are cached for 10 minutes in the tag-aware `cache.content` pool and invalidated by article, comment, engagement, subscription and rollup changes.

## Permission and privacy

`analytics.view` gates all analytics and export routes. Super Admins, Admins, Editors and Authors have it by default; Contributors do not. Visitor hashes are salted and raw IP addresses are not stored. External analytics remain limited to the consent-gated GA4 snippet from M9.

## Performance verification

With 100,000 raw views inserted across two days and rolled up into daily stats, a production-mode request for the analytics page measured:

- server response complete: **39 ms**
- full page load event: **92 ms**

The dashboard measured 66 ms response / 112 ms load in the same run. Measurements were taken on the local MariaDB service with `APP_ENV=prod`, a warm production cache and the PHP built-in server; they satisfy the <200 ms target for this environment. Re-run with:

```bash
php bin/console app:analytics:rollup --days=2
```

and load `/admin/analytics` after generating representative raw views.

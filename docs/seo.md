# SEO

## Metadata resolution

`App\Service\Seo\SeoHeadFactory` resolves metadata from the current route and persists a single source of truth for:

- document title fallback and description;
- canonical URL;
- robots directives;
- Open Graph and Twitter Card fields;
- schema.org JSON-LD;
- GA4 and Search Console values.

Resolution order:

1. Entity SEO fields (`SeoMetadata`) for articles, categories, tags and pages.
2. Entity content fallbacks (title, excerpt, description, reading content).
3. Site defaults in `SettingsService` (`seo.title_suffix`, `seo.description`, `general.name`, `general.tagline`).

All public pages render `templates/partials/seo_head.html.twig`. Search and filtered listing pages are `noindex,follow`; admin/auth/account pages default to noindex. Articles with `seo.noindex` or non-public visibility are excluded from public discovery.

## Structured data

A single JSON-LD graph is emitted per page:

- `Organization` and `WebSite` with a `SearchAction` site-wide.
- `BlogPosting` with headline, description, image, dates, author, publisher, canonical URL and word count on article pages.
- `Person` for authors.
- `BreadcrumbList` on article, collection and page routes.
- `CollectionPage`-style metadata is handled through canonical/OG fields; the graph stays focused on the required types.

JSON is encoded with hex escaping for HTML-significant characters to prevent script breakout.

## Sitemaps and robots

- `/sitemap.xml` is a sitemap index.
- `/sitemap-articles.xml`, `/sitemap-categories.xml`, `/sitemap-tags.xml`, `/sitemap-authors.xml` and `/sitemap-pages.xml` contain only public, published, indexable content.
- Drafts, scheduled, unpublished, deleted, private and `noindex` records are excluded.
- Sitemaps are cached for one hour in the tag-aware `cache.content` pool (`articles`/`sitemap` tags) and invalidated by editorial and taxonomy changes.
- `/robots.txt` is generated from `seo.robots` and always advertises the sitemap.

## Redirects

`RedirectListener` runs before routing for public `GET`/`HEAD` requests, looks up `Redirect.sourcePath` through the tag-aware cache, counts a hit and returns the configured status (301/302). Admin, auth, asset, upload, feed, sitemap, robots and profiler paths are skipped.

When a published article's slug changes through the article editor, `ArticleService` creates or updates a 301 redirect from `/blog/{oldSlug}` to `/blog/{newSlug}` and invalidates the redirect cache.

## Analytics and verification

- GA4 loads only when `seo.ga_id` is configured **and** the visitor has accepted optional consent (`fieldnotes_consent=all` cookie, mirrored from the consent banner/local storage).
- Google Search Console verification is emitted as a meta tag from `seo.gsc_verification`; it performs no tracking.
- No analytics or advertising script is included with the default settings.

# Public website integration

## Data source

Public controllers use `App\Service\Article\PublicCatalog`, which queries real repositories. Every public query requires:

- `status = published`
- `publishedAt IS NOT NULL` and `publishedAt <= now`
- not soft-deleted

Drafts, pending, scheduled, unpublished and deleted articles therefore return 404 and never appear in listings, feeds, archives or related content.

`MockDataProvider` remains only for administration screens that are still in their UI milestone, the styleguide and demo fixtures. The old `MockCatalog` was removed.

## Homepage sections

`PublicCatalog::homepage()` returns:

- **Latest**: newest 18 published articles.
- **Popular**: most viewed in the last 30 days from `ArticleView`, falling back to the denormalized `viewCount` while analytics data is sparse.
- **Trending**: most viewed in the last 7 days, falling back to the latest stories.
- **Recommended**: a slice of recent articles.
- **Categories, tags and authors** with published article counts for navigation and highlights.

The **IDs** of these sections are cached for five minutes in the tag-aware `cache.content` pool with tags `articles` and `homepage`. `ArticleService` invalidates those tags on create, update, status change, delete, restore, duplicate and revision restore, so publishing changes appear immediately.

## Listing and search

`GET /blog` and `GET /search` support `q`, `category` (slug), `tag` (slug), `author` (slug), `sort` (`latest`, `popular`, `oldest`) and `page`. Pages are clamped to the valid range and preserve filters in pagination links. Empty and no-match states are distinct.

Search uses a MySQL/MariaDB `FULLTEXT` index on `article(title, excerpt, content)` through `MATCH ... AGAINST` (natural language). A per-term `LIKE` fallback keeps partial and short-word searches useful. Search input is parameterized and escaped in output.

## Article pages

- Sanitized article HTML is rendered in the reader layout, with server-generated heading IDs driving the sticky table of contents.
- Reading progress, copy-link and reader engagement previews use the existing vanilla JS.
- Previous/next neighbours are determined by `publishedAt` and ID; related articles share at least one category or tag.
- Comments remain the M1 presentation fixture until M8 connects persistence and moderation.

## Discovery and feeds

- `/authors`, `/categories`, `/tags` list real entities with published counts.
- `/authors/{slug}`, `/categories/{slug}`, `/tags/{slug}` paginate published articles.
- `/feed.xml` returns the latest 20 published stories as RSS 2.0.
- `/feed/category/{slug}.xml` returns a category feed; missing categories return 404.
- The base layout advertises the RSS feed.

## Caching and performance

- Listing, archive and detail queries eager-load author, categories, tags and featured image.
- Pagination uses Doctrine's `Paginator` with fetch joins.
- Homepage aggregates are tag-cached; other pages query directly so publish changes are always fresh.
- Public caching headers and CDN/HTTP cache policy are part of M14.

# UI → domain data contract

`App\Mock\MockDataProvider` supplies plain associative arrays. Twig accesses them with entity-compatible property names; immutable dates are `DateTimeImmutable`, not formatted strings. IDs/slugs are stable and related values are nested objects/arrays, never artificial display-only columns.

| Object | Fields |
|---|---|
| Article | id, title, subtitle, slug, excerpt, content (sanitized HTML later), status, author, categories[], tags[], featuredImage, publishedAt?, scheduledAt?, createdAt, updatedAt, readingTime, viewCount, likeCount, allowComments, seo |
| User / author | id, displayName, slug, bio, jobTitle, avatar?, email, isActive, isVerified; articleCount is a query projection |
| Category | id, name, slug, description, parent?, image?, articleCount (query projection) |
| Tag | id, name, slug, description, articleCount (query projection) |
| Media | id, url (storage-resolved), filename, originalName, alt, caption, mimeType, width, height, size, createdAt |
| Comment | id, content, guestName, status, createdAt, parent?, replies[] (thread projection) |
| Role | id, name, description, permissions[], userCount (query projection) |
| SeoMetadata | title, description, focusKeyword, canonicalUrl?, noindex |

The provider contains 30 published and six non-public articles, eight categories including two children, 25 tags, six authors, threaded comments, six media illustrations and five roles. Status/visibility filters must be explicit when constructing public previews. Public article content is rendered through structured Twig in M1; unsanitized provider content is never marked raw.

When introducing entities in M3, preserve these property names. Aggregate/query projections can be DTOs rather than persistent columns. In M7 swap the preview provider for published query services; never leak draft content. UI-only preview submissions explicitly validate without persisting or sending email.

# UI → domain data contract

`App\Mock\MockDataProvider` supplies plain associative arrays. Twig accesses them with entity-compatible property names; immutable dates are `DateTimeImmutable`, not formatted strings. IDs/slugs are stable and related values are nested objects/arrays, never artificial display-only columns.

| Object | Fields |
|---|---|
| Article | id, title, subtitle, slug, excerpt, content (sanitized HTML later), status, visibility, deletedAt?, author, categories[], tags[], featuredImage, publishedAt?, scheduledAt?, createdAt, updatedAt, readingTime, viewCount, likeCount, allowComments, seo |
| User / author | id, displayName, slug, bio, jobTitle, avatar?, email, isActive, isVerified; articleCount is a query projection |
| Category | id, name, slug, description, parent?, image?, articleCount (query projection) |
| Tag | id, name, slug, description, articleCount (query projection) |
| Media | id, url (storage-resolved), filename, originalName, alt, caption, mimeType, width, height, size, createdAt |
| Comment | id, content, guestName, status, createdAt, parent?, replies[] (thread projection) |
| Role | id, name, description, permissions[], userCount (query projection) |
| Page | id, slug, title, content (sanitized HTML later), createdAt, updatedAt; description/eyebrow/sections[] are the structured preview presentation projection |
| SeoMetadata | title, description, focusKeyword, canonicalUrl?, noindex; ogImage is an optional Media association at backend integration |
| ActivityLog | id, user, action, subjectType, subjectId, context.summary (escaped), createdAt |
| ArticleRevision | id, article.id, title, content (text snapshot in M1), createdBy, createdAt, isAutosave |
| Dashboard projection | stats[] (label/value/icon/note), statuses (counts), recentArticles[], comments[], activity[], views/categories (labels[]/values[]); chart series are an explicitly illustrative snapshot, not live tracking |

The provider contains 30 published and six non-public articles, eight categories including two children, 25 tags, six authors, threaded comments, six media illustrations and five roles. Status/visibility filters must be explicit when constructing public previews. Public article content is rendered through structured Twig in M1; unsanitized provider content is never marked raw.

When introducing entities in M3, preserve these property names. Aggregate/query projections can be DTOs rather than persistent columns. In M7 swap the preview provider for published query services; never leak draft content. UI-only preview submissions explicitly validate without persisting or sending email.

M1.4 static pages use escaped `sections[].title` / `sections[].text` rather than raw HTML. Replace that content loop with the M5-sanitized `page.content` output at integration. Consent choices and theme preferences stay in local storage; optional tracking remains disabled. Public forms use the shared `PreviewForm` component: its fieldset is enabled only after the submit-prevention handler is attached, and stays disabled if JavaScript is unavailable.

M1.6 adds two separate `trashedArticles()` fixtures (38 admin rows, still 36 active article fixtures) and `ArticleRevision` text snapshots. `newArticle()` has a null id for the unsaved preview. RichEditor writes Quill semantic HTML to `content`, but nothing is posted or persisted; server-side sanitizing, unique slugs, workflow/permission validation and real autosaves remain M5. Media selection uses stable IDs for the eventual Form bindings; its local event contract is in `docs/admin-ui.md`.

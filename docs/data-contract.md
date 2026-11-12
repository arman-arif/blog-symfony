# UI → domain data contract

`App\Mock\MockDataProvider` supplies plain associative arrays. Twig accesses them with entity-compatible property names; immutable dates are `DateTimeImmutable`, not formatted strings. IDs/slugs are stable and related values are nested objects/arrays, never artificial display-only columns.

| Object | Fields |
|---|---|
| Article | id, title, subtitle, slug, excerpt, content (sanitized HTML later), status, visibility, deletedAt?, author, categories[], tags[], featuredImage, publishedAt?, scheduledAt?, createdAt, updatedAt, readingTime, viewCount, likeCount, allowComments, seo |
| User / author | id, displayName, slug, bio, jobTitle, avatar?, email, isActive, isVerified; articleCount is a query projection |
| Category | id, name, slug, description, parent?, image?, seo, articleCount (query projection) |
| Tag | id, name, slug, description, seo, articleCount (query projection) |
| Media | id, url (storage-resolved), filename, originalName, alt, caption, mimeType, width?, height?, size, createdAt, uploadedBy (admin projection) |
| Comment | id, content, guestName, status, createdAt, parent?, replies[] (thread projection) |
| Role / Permission | Role: id, name, description, permissions[] (capability strings in preview), userCount (query projection); Permission: code, label, group (presentation grouping) |
| NewsletterSubscriber | id, email, status, createdAt, confirmedAt?, unsubscribedAt? |
| NewsletterCampaign | id, subject, html (unsanitized/inert fixture; semantic editor output locally), status, scheduledAt?, sentAt?, stats.sent, stats.failed |
| Settings projection | general / system / appearance / seo / smtp grouped values; Setting entities remain key/value/group/isSensitive records in M3/M13, not one entity per screen |
| Analytics projection | daily[] (date, views, engagements), monthly (labels, values), articles[], categories[], tags[], authors[]; fixed fictional projections, not ArticleView entities or measured performance |
| Page | id, slug, title, content (sanitized HTML later), createdAt, updatedAt; description/eyebrow/sections[] are the structured preview presentation projection |
| SeoMetadata | title, description, focusKeyword, canonicalUrl?, noindex; ogImage is an optional Media association at backend integration |
| ActivityLog | id, user, action, subjectType, subjectId, context.summary (escaped), createdAt |
| ArticleRevision | id, article.id, title, content (text snapshot in M1), createdBy, createdAt, isAutosave |
| Menu / MenuItem | Menu: id, name, location, items[]; item: id, label, type, target, parent?, position |
| Advertisement | id, name, placement, type, content (inert code in preview), image?, startsAt?, endsAt?, isActive, priority |
| Dashboard projection | stats[] (label/value/icon/note), statuses (counts), recentArticles[], comments[], activity[], views/categories (labels[]/values[]); chart series are an explicitly illustrative snapshot, not live tracking |

The provider contains 30 published and six non-public articles, eight categories including two children, 25 tags, six authors, threaded comments, six media illustrations and five roles. Status/visibility filters must be explicit when constructing public previews. Public article content is rendered through structured Twig in M1; unsanitized provider content is never marked raw.

When introducing entities in M3, preserve these property names. Aggregate/query projections can be DTOs rather than persistent columns. **M7 is complete:** public routes now read `PublicCatalog`/repositories and render entities; `articleCount` remains a transient projection. `MockDataProvider` is retained only for administration screens not yet connected, the dev styleguide and demo fixtures. Draft content is never exposed publicly. UI-only preview submissions explicitly validate without persisting or sending email.

M1.4 static pages use escaped `sections[].title` / `sections[].text` rather than raw HTML. Replace that content loop with the M5-sanitized `page.content` output at integration. Consent choices and theme preferences stay in local storage; optional tracking remains disabled. Public forms use the shared `PreviewForm` component: its fieldset is enabled only after the submit-prevention handler is attached, and stays disabled if JavaScript is unavailable.

M1.6 adds two separate `trashedArticles()` fixtures (38 admin rows, still 36 active article fixtures) and `ArticleRevision` text snapshots. `newArticle()` has a null id for the unsaved preview. RichEditor writes Quill semantic HTML to `content`, but nothing is posted or persisted; server-side sanitizing, unique slugs, workflow/permission validation and real autosaves remain M5. Media selection uses stable IDs for the eventual Form bindings; its local event contract is in `docs/admin-ui.md`.

M1.7a `contentRecords()` supplies module fixtures without altering public visibility: six illustrations plus one bundled document, five moderation records (article, guestEmail and reportCount included), five pages with status/seo, two nested menus and three advertisements. `contentRecord(module, null)` supplies empty unsaved form values. Local menu IDs prefixed `local-` are UI-only and must become backend-generated IDs on persistence. Upload simulation does not introduce any file record.

M1.7b adds role assignments/permission strings, an inactive user, 18 subscribers and three campaigns. User forms bind `roles[]` IDs; role/matrix forms bind capability codes. `RichEditor.outputName` is `html` for campaigns and `content` for articles/pages. HTML fixtures are never rendered raw. Settings media fields use distinct `logo`, `favicon`, `avatar` and `ogImage` IDs. SMTP passwords, current/new/confirm passwords and 2FA secrets are deliberately absent from fixtures; entered dummy values are never persisted, sent or included in preview events. Account activation and consent are UI-only, not changed by mock status controls.

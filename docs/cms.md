# Core CMS

## Editorial workflow

Articles move through a `symfony/workflow` state machine (`article_publishing`):

`draft → pending_review → scheduled | published → unpublished`, plus `draft → published` and `unpublished → draft`.

Transitions are permission-gated in `ArticleService`:

| Transition | Permission |
|---|---|
| submit | `article.submit` |
| return_to_draft | `article.review` |
| publish | `article.publish` |
| schedule | `article.schedule` |
| unpublish | `article.publish` |
| revise | `article.edit.own` |

Authors can edit and delete their own articles through `ArticleVoter` (`article.edit.own` / `article.delete.own`); editors and administrators use the `.any` permissions. A Super Administrator bypasses granular checks.

## Content pipeline

`App\Service\Article\ContentProcessor`:

1. Sanitizes editor HTML with Symfony HtmlSanitizer using an allow-list: headings, paragraphs, lists, links, images, figures, tables, pre/code, blockquotes, horizontal rules, and iframe embeds from YouTube/Vimeo only.
2. Forces HTTPS and removes scripts, event handlers, unsafe schemes and unknown elements.
3. Adds deterministic `id` attributes to `h2`/`h3`/`h4` headings for the public table of contents.
4. Derives reading time at 200 words/minute and an automatic excerpt when none is supplied.

Sanitized content is stored in `article.content`; revisions and autosaves are sanitized before persistence. Only sanitized content may be rendered with `|raw` in admin previews.

## Article management

- Create, edit, delete (soft), restore, duplicate, preview and list.
- Manual saves create a revision snapshot with title, content, author and timestamp.
- Autosave endpoint `POST /admin/articles/{id}/autosave` (JSON, CSRF header `X-CSRF-Token`, sliding-window rate limit of 12/minute) stores an autosave revision and keeps the latest 10 per article.
- Revision history supports compare and restore.
- Slug uniqueness is enforced in the service and database; empty slugs are generated from the title and receive numeric suffixes on conflict.
- The article form exposes title, slug, subtitle, excerpt, sanitized body, status, visibility, schedule, author, categories, tags, featured image, comments toggle and SEO metadata.

## Taxonomy

Categories use the Gedmo nested set (`lft`, `rgt`, `lvl`, `root`). Parent selection supports hierarchy; a category cannot be its own parent, and deleting a category with children is refused with a message to move or delete children first.

Tags support create, edit, delete, search, autocomplete (`GET /admin/tags/autocomplete?q=`) and idempotent quick-create (`POST /admin/tags/quick-create`, JSON/CSRF). Slugs are generated and guaranteed unique.

## Scheduled publishing

`App\Scheduler\ArticlePublishingSchedule` dispatches `PublishScheduledArticlesMessage` every minute. `PublishScheduledArticlesHandler` publishes scheduled articles whose `scheduledAt` has passed, setting `publishedAt` and clearing the schedule. The schedule uses the `scheduler_default` Messenger transport, so production requires a running worker:

```bash
php bin/console messenger:consume scheduler_default async --time-limit=3600
```

Tests dispatch the message through the message bus and assert the article becomes published.

## Browser behavior

The Quill editor writes semantic HTML into the form's content field. Formatting toolbar actions cover headings, emphasis, lists, quotes, code, links, tables, local media images, video embeds, dividers, alignment and undo/redo. Autosave runs four seconds after a change and reports its state in the editor header; manual save creates the permanent revision.

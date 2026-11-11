# Project: Professional Blogging Platform

## 1. Project Objective

Build a modern, professional, responsive, SEO-friendly blogging platform with a comprehensive Content Management System (CMS).

The platform must include:

1. A modern public-facing blogging website.
2. A comprehensive administrative dashboard.
3. Content and media management.
4. Multi-user and role-based access control.
5. SEO management.
6. Reader engagement functionality.
7. Newsletter management.
8. Analytics and reporting.
9. Website customization.
10. Security and performance optimization.

The application must be developed incrementally, following a strict UI/UX-first development approach.

**Critical development principle: Complete the UI/UX design of the entire application before implementing backend business logic.**

---

# 2. Mandatory Technology Stack

### Backend
- PHP 8.3+
- Symfony (latest stable version compatible with the PHP environment)
- Doctrine ORM
- Symfony Security
- Symfony Validator
- Symfony Form
- Symfony Messenger
- Symfony Mailer
- Symfony Cache

### Frontend
- Twig
- Tailwind CSS 3.4
- Vanilla JavaScript
- HTML5
- CSS3

### Database
- MySQL 8.0+

### Asset Management
- Tailwind CSS standalone CLI.
- Symfony Asset Component.
- Locally hosted static assets.

### STRICT RESTRICTION: NO NODE.JS

Node.js must not be installed, required, or used anywhere in this project.

Absolutely prohibited:

- Node.js
- npm
- npx
- Yarn
- pnpm
- Bun
- Vite
- Webpack
- Webpack Encore
- Any Node.js-based build tooling.

Use the standalone Tailwind CSS CLI executable to compile stylesheets.

Use vanilla JavaScript for frontend interactivity.

The application must be installable, executable, and deployable using PHP, Composer, MySQL, and a standard web server without requiring Node.js.

---

# 3. Mandatory Development Workflow

Follow the development phases in the exact order described below.

Do not skip phases or start implementing backend functionality prematurely.

## PHASE 0: Project Inspection and Planning

Before writing code:

1. Inspect the existing project directory.
2. Identify existing files, configurations, dependencies, and architecture.
3. Determine whether the project is new or an existing Symfony application.
4. Avoid overwriting existing code without understanding its purpose.
5. Establish the proposed directory structure.
6. Identify all application modules.
7. Create a development roadmap.
8. Create a `PROJECT_PROGRESS.md` file to track implementation progress.

Deliverables:

- Project structure proposal.
- Technology configuration plan.
- Module breakdown.
- Development roadmap.
- Initial progress document.

Do not start implementing business logic in this phase.

---

## PHASE 1: UI/UX Design – Highest Priority

This is the most important initial development phase.

Design and implement the complete visual interface before developing backend business logic.

The objective is to produce a polished, consistent, production-quality user interface for both the public website and administration panel.

### 1.1 Design System

Establish a consistent design system before building individual pages.

Define:

- Primary and secondary colors.
- Background and surface colors.
- Typography and font hierarchy.
- Font sizes and weights.
- Spacing system.
- Border radius.
- Shadows.
- Button styles.
- Input styles.
- Select and dropdown styles.
- Card styles.
- Table styles.
- Modal styles.
- Badge styles.
- Alert styles.
- Toast notifications.
- Navigation styles.
- Icon system.
- Responsive breakpoints.

Use a consistent visual language throughout the application.

Avoid generic-looking UI components.

### 1.2 Public Website UI

Design and implement all public-facing pages.

#### Homepage
- Header and navigation.
- Hero or featured article section.
- Latest articles.
- Popular articles.
- Trending articles.
- Category-based article sections.
- Recommended articles.
- Author highlights.
- Newsletter subscription section.
- Footer.

#### Blog Listing
- Article grid/list layouts.
- Category filters.
- Tag filters.
- Search interface.
- Sorting controls.
- Article cards.
- Pagination.
- Empty states.

#### Blog Details
- Article title and subtitle.
- Featured image.
- Author information.
- Publication date.
- Reading time.
- Article content layout.
- Table of contents.
- Reading progress indicator.
- Social sharing interface.
- Article tags.
- Related articles.
- Author biography.
- Comment interface.

#### Additional Pages
- About Us.
- Contact Us.
- Privacy Policy.
- Terms and Conditions.
- Cookie Policy.
- Authors.
- Author Profile.
- Categories.
- Category Details.
- Tags.
- Search Results.
- 404 Page.
- 500 Error Page.

### 1.3 Administration Dashboard UI

Create a separate, professional administration interface.

Design the following:

#### Dashboard
- Sidebar navigation.
- Top navigation bar.
- Statistics cards.
- Analytics charts.
- Recent articles.
- Recent comments.
- Recent activities.
- Quick actions.

#### Article Management
- Article listing.
- Article creation.
- Article editing.
- Article preview.
- Draft management.
- Scheduled articles.
- Revision history.

#### Content Editor
- Rich-text editing interface.
- Formatting toolbar.
- Heading controls.
- Image insertion.
- Link insertion.
- Table insertion.
- Code blocks.
- Blockquotes.
- Article settings panel.
- Featured image selector.
- SEO settings panel.

#### Other Administration Pages
- Category management.
- Tag management.
- Media library.
- Comment moderation.
- User management.
- Role management.
- Permission management.
- Newsletter management.
- Analytics.
- Advertisement management.
- Static page management.
- Navigation menu management.
- Appearance management.
- SEO settings.
- General settings.
- SMTP settings.
- Activity logs.
- Administrator profile.

### 1.4 UI States

Do not design only the ideal/default state.

Implement the visual states for:

- Loading.
- Empty data.
- Error.
- Success.
- Validation errors.
- Disabled controls.
- Confirmation dialogs.
- Delete confirmations.
- No search results.
- No permission.
- Responsive navigation.

### 1.5 Responsive Design

Every page must support:

- Desktop.
- Laptop.
- Tablet.
- Mobile.

Follow a mobile-first approach.

Pay particular attention to:

- Navigation.
- Article layouts.
- Tables.
- Forms.
- Dashboard cards.
- Sidebar navigation.
- Rich-text editor.
- Modals.

### 1.6 UI Implementation Requirements

- Use Twig template inheritance.
- Create reusable Twig components.
- Create reusable template partials.
- Use Tailwind utility classes.
- Use vanilla JavaScript.
- Use accessible semantic HTML.
- Maintain consistent spacing and typography.
- Avoid unnecessary dependencies.
- Avoid duplicated markup.

Use realistic mock data to populate all interfaces.

At this stage, forms, filters, dropdowns, tabs, modals, navigation, and other interactions should work visually using frontend logic or mock data.

Backend persistence is not required yet.

### 1.7 UI/UX Completion Checklist

Before moving to Phase 2, verify:

- Every public page has been implemented.
- Every administration page has been implemented.
- All reusable components are consistent.
- All responsive layouts work.
- All navigation links work.
- All dropdowns and modals work.
- Forms display appropriate validation states.
- Tables and pagination work with mock data.
- No broken layouts exist.
- No missing pages exist.
- No unnecessary placeholder screens remain.

**Do not proceed to backend implementation until the complete UI/UX layer is implemented and reviewed.**

---

## PHASE 2: Symfony Architecture and Backend Foundation

Once the UI/UX phase is complete, establish the application architecture.

Implement:

- Symfony application configuration.
- Environment configuration.
- MySQL connection.
- Doctrine ORM.
- Doctrine migrations.
- Symfony Security.
- Authentication.
- Authorization.
- Service layer.
- Repository layer.
- Form handling.
- Validation.
- Exception handling.
- Logging.
- Cache configuration.

### Architectural Standards

Use a maintainable, modular architecture.

Follow these conventions:

- Controllers handle HTTP requests.
- Services contain business logic.
- Repositories handle database queries.
- Entities represent domain models.
- Form Types handle form definitions.
- Validators handle validation.
- Twig handles presentation.

Keep controllers lightweight.

Avoid putting business logic inside Twig templates or controllers.

---

## PHASE 3: Database Design and Implementation

Design a normalized MySQL database.

Suggested entities:

- User
- Role
- Permission
- Article
- ArticleRevision
- Category
- Tag
- ArticleCategory
- ArticleTag
- Comment
- ArticleLike
- ArticleBookmark
- ArticleView
- Media
- Page
- Menu
- MenuItem
- NewsletterSubscriber
- NewsletterCampaign
- Advertisement
- Setting
- Redirect
- ActivityLog

Additional entities may be introduced when necessary.

### Database Requirements

- Doctrine entities.
- Doctrine migrations.
- Proper relationships.
- Foreign key constraints.
- Unique constraints.
- Database indexes.
- Transaction management.
- Appropriate deletion policies.
- Created and updated timestamps.
- Soft deletion where appropriate.

Avoid unnecessary database complexity.

---

## PHASE 4: Authentication and Authorization

Implement:

### Authentication
- Login.
- Logout.
- Registration (configurable).
- Forgot password.
- Password reset.
- Email verification.
- User profile.
- Password change.
- Account activation/deactivation.
- Last login information.

### Default Roles

**Super Administrator**
- Full system access.
- Manage administrators.
- Manage roles and permissions.
- Manage all settings.

**Administrator**
- Manage website content.
- Manage users.
- Manage categories and tags.
- Manage comments.
- Access analytics.
- Configure permitted settings.

**Editor**
- Manage articles.
- Review submitted articles.
- Publish articles.
- Manage comments.
- Manage categories and tags.

**Author**
- Create articles.
- Manage own articles.
- Submit articles for review.
- Manage own profile.

**Contributor**
- Create articles.
- Edit own drafts.
- Submit articles for review.

Implement granular permissions rather than relying exclusively on role names.

Enforce all permissions on the backend.

---

## PHASE 5: Core CMS Implementation

Implement the backend functionality and connect it to the previously developed UI.

### Article Management
- Create articles.
- Edit articles.
- Delete articles.
- Duplicate articles.
- Save drafts.
- Publish articles.
- Schedule publication.
- Unpublish articles.
- Preview articles.
- Manage article excerpts.
- Manage article slugs.
- Manage featured images.
- Assign authors.
- Assign categories.
- Assign tags.
- Enable/disable comments.
- Autosave.
- Revision history.
- Restore revisions.

### Content Editor
Support:

- Headings.
- Paragraphs.
- Bold and italic.
- Lists.
- Blockquotes.
- Links.
- Images.
- Image captions.
- Tables.
- Code blocks.
- Embedded videos.
- Separators.
- Text alignment.
- Undo and redo.

Ensure secure HTML rendering.

### Category Management
- Create.
- Edit.
- Delete.
- Hierarchical categories.
- Category descriptions.
- Category images.
- Category SEO settings.

### Tag Management
- Create.
- Edit.
- Delete.
- Tag descriptions.
- Tag SEO settings.

Prevent duplicate slugs.

---

## PHASE 6: Media Management

Implement a centralized media library.

Features:

- Image uploads.
- Document uploads.
- Media browsing.
- Media searching.
- Media filtering.
- Image resizing.
- Thumbnail generation.
- Image optimization.
- Alternative text management.
- Caption management.
- Media deletion.

Validate file types, MIME types, and upload sizes.

Use randomized filenames.

Prevent executable file uploads.

Initially support local storage while keeping the architecture extensible for S3-compatible storage.

---

## PHASE 7: Public Website Integration

Connect the public website UI to real database content.

Implement:

- Homepage content sections.
- Blog listing.
- Blog details.
- Category pages.
- Tag pages.
- Author pages.
- Search.
- Sorting.
- Pagination.
- Related articles.
- Popular articles.
- Trending articles.
- Reading time estimation.
- Reading progress.
- RSS feed.
- Previous and next articles.

Only published and publicly accessible content should be available to visitors.

Ensure SEO-friendly routing.

---

## PHASE 8: Comments and Reader Engagement

### Comments
- Submit comments.
- Reply to comments.
- Nested comment threads.
- Moderate comments.
- Approve/reject comments.
- Edit/delete comments.
- Report comments.
- Spam protection.
- Disable comments per article.

### Engagement
- Article likes.
- Article bookmarks.
- Save for later.
- Social sharing.
- Article view counts.

Prevent duplicate likes and bookmarks.

Support configurable anonymous commenting with spam protection.

---

## PHASE 9: SEO Implementation

Implement comprehensive SEO functionality.

### Article SEO
- SEO title.
- Meta description.
- Focus keyword.
- Canonical URL.
- Open Graph metadata.
- Twitter Card metadata.
- Indexing controls.
- Structured data.

### Website SEO
- XML sitemap.
- Robots.txt.
- Article schema.
- Organization schema.
- Author schema.
- Breadcrumb schema.
- SEO-friendly URLs.
- Image alt text.
- Redirect management.
- Automatic metadata generation.

Integrate Google Analytics and Google Search Console through configurable tracking IDs or snippets.

Never expose private content in public sitemaps.

---

## PHASE 10: Newsletter and Email Management

Implement:

- Newsletter subscription.
- Email verification.
- Double opt-in (configurable).
- Subscriber management.
- Subscriber activation/deactivation.
- Unsubscribe.
- Subscriber export.
- Newsletter campaigns.
- HTML email templates.
- Campaign scheduling.
- Campaign history.
- Email notifications.

Use Symfony Mailer.

Use Symfony Messenger for background email processing where appropriate.

---

## PHASE 11: Analytics and Reporting

Implement the administration analytics dashboard.

### Dashboard Statistics
- Total articles.
- Published articles.
- Draft articles.
- Scheduled articles.
- Total users.
- Total authors.
- Total comments.
- Pending comments.
- Newsletter subscribers.
- Article views.

### Content Analytics
- Popular articles.
- Most viewed articles.
- Popular categories.
- Popular tags.
- Author performance.
- Article engagement.
- Daily views.
- Monthly views.

### Traffic Analytics
Implement internal traffic statistics where practical.

Support external analytics integration for advanced visitor information.

Use date filtering and caching.

Avoid expensive database queries on every dashboard request.

---

## PHASE 12: Monetization

Design an extensible architecture for:

- Advertisement management.
- Google AdSense.
- Banner advertisements.
- Advertisement placements.
- Sponsored articles.
- Affiliate links.
- Premium articles.
- Paid subscriptions.
- Membership plans.
- Donations.

Use configurable external integrations rather than hardcoding credentials.

These features can be implemented as separate modules without affecting the core CMS.

---

## PHASE 13: Website Customization and Settings

Implement:

### General Settings
- Website name.
- Tagline.
- Description.
- Logo.
- Favicon.
- Contact email.
- Contact information.
- Timezone.
- Date format.
- Time format.
- Language.

### Appearance
- Header customization.
- Footer customization.
- Homepage sections.
- Sidebar configuration.
- Theme colors.
- Light/dark mode.
- Social links.

### Navigation
- Menu creation.
- Menu editing.
- Menu deletion.
- Custom links.
- Internal page links.
- Category links.
- Nested menu items.
- Menu reordering.
- Menu locations.

### System Settings
- SMTP configuration.
- Email templates.
- Registration settings.
- Comment settings.
- Newsletter settings.
- Media upload limits.
- Cache configuration.
- Maintenance mode.
- Custom CSS.
- Custom JavaScript.

Restrict sensitive settings to authorized administrators.

---

## PHASE 14: Security and Performance

### Security

Implement:

- CSRF protection.
- XSS prevention.
- SQL injection prevention.
- Secure password hashing.
- Secure session management.
- Login rate limiting.
- Backend authorization.
- Input validation.
- Output escaping.
- Secure file uploads.
- MIME type validation.
- Secure password reset tokens.
- Email verification.
- Activity logging.
- Two-factor authentication support.

Use Doctrine ORM and parameterized queries.

Never store credentials in source code.

### Performance

Implement:

- Database indexing.
- Efficient Doctrine queries.
- Pagination.
- Query optimization.
- Symfony caching.
- HTTP caching.
- Image optimization.
- Lazy loading.
- Asset compression.
- Browser caching.
- Responsive images.
- N+1 query prevention.

Support optional Redis integration.

The application must also work without Redis.

---

## PHASE 15: Testing and Quality Assurance

Use PHPUnit and Symfony testing tools.

### Backend Testing
- Authentication.
- Authorization.
- Article CRUD.
- Category CRUD.
- Tag CRUD.
- Comment moderation.
- User management.
- SEO metadata.
- File uploads.
- Newsletter functionality.

### Functional Testing
- Article publishing workflow.
- Scheduled publishing.
- Search.
- Filtering.
- Pagination.
- Comments.
- Newsletter subscription.
- Menu management.
- User permissions.

### UI Testing
- Responsive layouts.
- Navigation.
- Forms.
- Modals.
- Tables.
- Empty states.
- Error states.
- Mobile layouts.

Run tests after each major module.

Fix failures before marking a module as complete.

---

## PHASE 16: Deployment and Documentation

Prepare the application for production deployment.

Support:

- Ubuntu/Debian.
- Apache.
- Nginx.
- PHP-FPM.
- MySQL.
- Composer.
- Cron jobs.

Provide documentation for:

- Installation.
- Environment configuration.
- Database setup.
- Database migrations.
- Tailwind standalone CLI installation.
- Asset compilation.
- Production deployment.
- Scheduled tasks.
- Messenger workers.
- File permissions.
- Backup and restore.
- Cache management.

Create a comprehensive `README.md`.

Document the application architecture, database schema, modules, roles, and permissions.

The production server must not require Node.js.

---

# 4. Progress Tracking and Implementation Discipline

Maintain `PROJECT_PROGRESS.md` throughout development.

Use the following structure:

| Phase | Module | Status | Notes |
|---|---|---|---|
| 0 | Project Inspection | Pending | |
| 1 | UI/UX Design | Pending | |
| 2 | Backend Architecture | Pending | |
| 3 | Database | Pending | |
| 4 | Authentication | Pending | |
| 5 | Core CMS | Pending | |
| 6 | Media Management | Pending | |
| 7 | Public Website Integration | Pending | |
| 8 | Comments & Engagement | Pending | |
| 9 | SEO | Pending | |
| 10 | Newsletter | Pending | |
| 11 | Analytics | Pending | |
| 12 | Monetization | Pending | |
| 13 | Website Settings | Pending | |
| 14 | Security & Performance | Pending | |
| 15 | Testing | Pending | |
| 16 | Deployment & Documentation | Pending | |

Allowed statuses:

- Pending
- In Progress
- Completed
- Blocked

After completing each phase:

1. Update the progress document.
2. Record implemented functionality.
3. Record any known issues.
4. Run relevant tests.
5. Verify the implementation.
6. Proceed to the next phase.

Do not mark incomplete functionality as completed.

---

# 5. Mandatory Coding Agent Instructions

1. Follow the development phases in the exact order specified.
2. Prioritize the complete UI/UX implementation before backend business logic.
3. Build all public and administrative screens using realistic mock data during Phase 1.
4. Do not create UI-only placeholders as final implementations.
5. Reuse the UI components developed in Phase 1 when integrating backend functionality.
6. Keep frontend and backend responsibilities separate.
7. Use Symfony services for business logic.
8. Use Doctrine migrations for database schema changes.
9. Implement server-side validation.
10. Enforce authorization on every protected backend operation.
11. Do not hardcode configurable settings.
12. Avoid unnecessary dependencies.
13. Do not overwrite existing project files without inspection.
14. Do not skip testing.
15. Fix errors before proceeding to the next major phase.
16. Keep `PROJECT_PROGRESS.md` updated.
17. Do not introduce Node.js or any Node.js-dependent tooling.
18. Do not claim a feature is complete unless its required frontend, backend, database, validation, and authorization components are implemented.

## Final Deliverable

A complete, production-ready blogging CMS with:

- A professionally designed public blogging website.
- A comprehensive administrative dashboard.
- Fully functional content management.
- Multi-user authentication and authorization.
- SEO management.
- Media management.
- Reader engagement.
- Newsletter management.
- Analytics and reporting.
- Website customization.
- Secure and optimized backend.
- Production deployment documentation.

**Mandatory technology stack: Symfony + PHP + Twig + Tailwind CSS + MySQL.**

**Mandatory development sequence: UI/UX First → Backend Architecture → Database → Authentication → Core CMS → Public Website Integration → Additional Modules → Testing → Deployment.**

**Absolute restriction: No Node.js, npm, npx, Yarn, pnpm, Bun, Vite, Webpack, or any Node.js-based tooling.**


<?php

declare(strict_types=1);

namespace App\Mock;

final class MockDataProvider
{
    /** @return list<array<string, mixed>> */
    public function authors(): array
    {
        $people = [
            ['Maya Chen', 'Design editor', 'Maya explores the small decisions that make everyday experiences more human.'],
            ['Oliver Brooks', 'Technology writer', 'Oliver writes about the intersection of thoughtful tools and meaningful work.'],
            ['Amara Okafor', 'Culture contributor', 'Amara connects art, cities and the stories we tell about ourselves.'],
            ['Theo Laurent', 'Nature columnist', 'Theo finds inspiration in landscapes, slow travel and getting a little lost.'],
            ['Sofia Reyes', 'Wellbeing editor', 'Sofia shares evidence-informed ideas for a more intentional, balanced life.'],
            ['James Park', 'Independent journalist', 'James asks better questions about the future of work and community.'],
        ];

        return array_map(fn (array $person, int $id): array => [
            'id' => $id + 1, 'displayName' => $person[0], 'slug' => $this->slug($person[0]),
            'jobTitle' => $person[1], 'bio' => $person[2], 'avatar' => null, 'articleCount' => 5,
            'email' => 'writer'.($id + 1).'@example.test', 'isActive' => true, 'isVerified' => true,
        ], $people, array_keys($people));
    }

    /** @return list<array<string, mixed>> */
    public function categories(): array
    {
        $names = ['Design', 'Technology', 'Culture', 'Nature', 'Wellbeing', 'Work & Life', 'Architecture', 'Digital Craft'];

        return array_map(fn (string $name, int $id): array => [
            'id' => $id + 1, 'name' => $name, 'slug' => $this->slug($name),
            'description' => 'Fresh perspectives, practical ideas and stories worth your time from the world of '.strtolower($name).'.',
            'parent' => $id > 5 ? ['id' => 1, 'name' => 'Design', 'slug' => 'design'] : null,
            'image' => $this->media()[$id % 6], 'articleCount' => $id < 6 ? 5 : 2,
            'seo' => ['title' => $name, 'description' => '', 'noindex' => false],
        ], $names, array_keys($names));
    }

    /** @return list<array<string, mixed>> */
    public function tags(): array
    {
        $names = ['Creativity', 'Slow living', 'Web design', 'AI', 'Sustainability', 'Architecture', 'Photography', 'Books', 'Travel', 'Community', 'Mindfulness', 'Remote work', 'Typography', 'Open source', 'Food', 'Gardens', 'Craft', 'Cities', 'Learning', 'Writing', 'Research', 'Accessibility', 'Habits', 'Innovation', 'Interviews'];

        return array_map(fn (string $name, int $id): array => ['id' => $id + 1, 'name' => $name, 'slug' => $this->slug($name), 'description' => 'Stories and perspectives about '.strtolower($name).'.', 'articleCount' => 2 + $id % 8, 'seo' => ['title' => $name, 'description' => '', 'noindex' => false]], $names, array_keys($names));
    }

    /** @return list<array<string, mixed>> */
    public function media(): array
    {
        $names = ['Quiet geometry', 'A considered workspace', 'The rhythm of the city', 'Into the green', 'A softer morning', 'Room to think'];

        return array_map(static fn (string $name, int $id): array => [
            'id' => $id + 1, 'url' => 'images/cover-'.($id + 1).'.svg', 'filename' => 'cover-'.($id + 1).'.svg',
            'originalName' => $name.'.svg', 'alt' => $name.' — original editorial illustration', 'caption' => 'Original illustration for Fieldnotes.',
            'mimeType' => 'image/svg+xml', 'width' => 1200, 'height' => 800, 'size' => 1400,
            'createdAt' => new \DateTimeImmutable('2026-10-10'),
        ], $names, array_keys($names));
    }

    /** @return list<array<string, mixed>> */
    public function articles(): array
    {
        $titles = [
            'The quiet power of making things with intention', 'Technology that gives us our time back', 'What a city remembers when we walk', 'Learning to read the landscape', 'A gentler way to start your day', 'The case for doing fewer things, better',
            'Why good design begins with listening', 'The human side of artificial intelligence', 'The independent bookshops keeping stories alive', 'A field guide to getting a little lost', 'Small rituals, lasting change', 'Building a workspace that works for you',
            'Finding beauty in the everyday', 'Open tools, shared possibilities', 'Meet the makers shaping a new creative scene', 'The gardens bringing neighbours together', 'Rest is not a reward for productivity', 'How to protect your most creative hours',
            'The art of leaving room for something unexpected', 'A slower, more thoughtful internet', 'The photographs that changed how we see', 'What the forest can teach us about connection', 'Cooking as a practice of paying attention', 'Good questions make better work',
            'Designing for everyone, not just ourselves', 'Digital craft in an age of automation', 'The surprising life of a public library', 'A weekend without a destination', 'Finding a rhythm that feels like yours', 'The future of work is a conversation',
            'An unfinished notebook of ideas', 'A conversation with tomorrow', 'Observations from the studio', 'A seasonal guide to the coast', 'New rituals for a new season', 'A better meeting begins before it starts',
        ];
        $authors = $this->authors();
        $categories = $this->categories();
        $tags = $this->tags();
        $media = $this->media();
        $articles = [];
        foreach ($titles as $id => $title) {
            $status = $id < 30 ? 'published' : ['draft', 'pending_review', 'scheduled', 'unpublished', 'draft', 'scheduled'][$id - 30];
            $articles[] = [
                'id' => $id + 1, 'title' => $title, 'slug' => $this->slug($title),
                'subtitle' => 'Small observations. New perspectives. A more considered way forward.',
                'excerpt' => 'In a world that asks us to move faster, there is value in pausing to notice. We explore the people, practices and ideas helping us make space for what matters.',
                'content' => '', 'status' => $status, 'visibility' => 'public', 'deletedAt' => null, 'author' => $authors[$id % 6],
                'categories' => [$categories[$id % 6]], 'tags' => [$tags[$id % 25], $tags[($id + 4) % 25]],
                'featuredImage' => $media[$id % 6], 'publishedAt' => 'published' === $status ? new \DateTimeImmutable('2026-11-11 -'.$id.' days') : null,
                'scheduledAt' => 'scheduled' === $status ? new \DateTimeImmutable('2026-12-01') : null,
                'createdAt' => new \DateTimeImmutable('2026-10-01'), 'updatedAt' => new \DateTimeImmutable('2026-11-11'),
                'readingTime' => 4 + $id % 8, 'viewCount' => 1200 + ($id * 317) % 8600,
                'likeCount' => 42 + $id * 3, 'allowComments' => 0 !== $id % 7,
                'seo' => ['title' => $title, 'description' => '', 'focusKeyword' => '', 'canonicalUrl' => null, 'noindex' => false],
            ];
        }

        return $articles;
    }

    /** @return list<array<string, mixed>> */
    public function trashedArticles(): array
    {
        return array_map(static fn (array $article, int $index): array => array_merge($article, [
            'id' => 37 + $index, 'title' => ['An earlier direction for the journal', 'Notes from a retired series'][$index],
            'slug' => 'retired-preview-'.($index + 1), 'status' => 'draft',
            'deletedAt' => new \DateTimeImmutable('2026-11-09'),
        ]), array_slice($this->articles(), 0, 2), range(0, 1));
    }

    /** @return array<string, mixed>|null */
    public function adminArticle(int $id): ?array
    {
        foreach (array_merge($this->articles(), $this->trashedArticles()) as $article) {
            if ($article['id'] === $id) {
                return $article;
            }
        }

        return null;
    }

    /** Empty form values only; does not create an article.
     * @return array<string, mixed>
     */
    public function newArticle(): array
    {
        return array_merge($this->articles()[0], [
            'id' => null, 'title' => '', 'slug' => '', 'subtitle' => '', 'excerpt' => '', 'content' => '',
            'status' => 'draft', 'publishedAt' => null, 'scheduledAt' => null, 'featuredImage' => null,
            'categories' => [], 'tags' => [], 'viewCount' => 0, 'likeCount' => 0, 'allowComments' => true,
            'seo' => ['title' => '', 'description' => '', 'focusKeyword' => '', 'canonicalUrl' => null, 'noindex' => false],
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function revisions(int $articleId): array
    {
        $article = $this->adminArticle($articleId);
        if (null === $article) {
            return [];
        }

        return array_map(static fn (int $index): array => [
            'id' => $articleId * 10 + $index, 'article' => ['id' => $articleId],
            'title' => 1 === $index ? 'A first observation from the notebook' : $article['title'],
            'content' => 1 === $index ? 'Good work begins when we leave enough room to pay attention.' : $article['excerpt'],
            'createdBy' => $article['author'], 'createdAt' => new \DateTimeImmutable('2026-11-11 15:00 -'.(3 - $index).' hours'),
            'isAutosave' => 2 === $index,
        ], range(1, 3));
    }

    /** @return list<array<string, mixed>> */
    public function comments(): array
    {
        return [
            ['id' => 1, 'content' => 'The idea of leaving room for the unexpected really resonates. My best work often starts with a walk, not a plan.', 'guestName' => 'Alex Morgan', 'status' => 'approved', 'createdAt' => new \DateTimeImmutable('2026-11-11'), 'parent' => null, 'replies' => [
                ['id' => 2, 'content' => 'Exactly! A change of scenery can be the most productive part of a day.', 'guestName' => 'Maya Chen', 'status' => 'approved', 'createdAt' => new \DateTimeImmutable('2026-11-12'), 'parent' => ['id' => 1], 'replies' => []],
            ]],
            ['id' => 3, 'content' => 'A thoughtful reminder to pay attention to the process. Thank you for sharing this perspective.', 'guestName' => 'Sam Rivera', 'status' => 'pending', 'createdAt' => new \DateTimeImmutable('2026-11-12'), 'parent' => null, 'replies' => []],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function roles(): array
    {
        $codes = array_column($this->permissions(), 'code');
        $assignments = [
            $codes,
            array_values(array_diff($codes, ['role.manage', 'permission.manage', 'settings.smtp.manage', 'settings.system.manage', 'settings.code.manage'])),
            ['admin.access', 'article.create', 'article.edit.own', 'article.edit.any', 'article.delete.own', 'article.delete.any', 'article.publish', 'article.schedule', 'article.review', 'article.submit', 'category.manage', 'tag.manage', 'media.upload', 'media.manage.any', 'comment.moderate', 'analytics.view', 'profile.manage.own'],
            ['admin.access', 'article.create', 'article.edit.own', 'article.delete.own', 'article.submit', 'media.upload', 'comment.moderate', 'analytics.view', 'profile.manage.own'],
            ['admin.access', 'article.create', 'article.edit.own', 'article.delete.own', 'article.submit', 'media.upload', 'profile.manage.own'],
        ];

        return array_map(static fn (string $name, int $id): array => ['id' => $id + 1, 'name' => $name, 'description' => $name.' editorial access', 'permissions' => $assignments[$id], 'userCount' => 0 === $id ? 2 : 1], ['Super Administrator', 'Administrator', 'Editor', 'Author', 'Contributor'], range(0, 4));
    }

    /** Permission names are presentation fixtures, not authorization decisions.
     * @return list<array{code: string, label: string, group: string}>
     */
    public function permissions(): array
    {
        $groups = [
            'Workspace' => ['admin.access' => 'Access the workspace', 'profile.manage.own' => 'Manage own profile'],
            'Editorial' => ['article.create' => 'Create articles', 'article.edit.own' => 'Edit own articles', 'article.edit.any' => 'Edit any article', 'article.delete.own' => 'Delete own articles', 'article.delete.any' => 'Delete any article', 'article.submit' => 'Submit for review', 'article.review' => 'Review submissions', 'article.publish' => 'Publish articles', 'article.schedule' => 'Schedule articles', 'category.manage' => 'Manage categories', 'tag.manage' => 'Manage tags', 'media.upload' => 'Upload media', 'media.manage.any' => 'Manage any media', 'comment.moderate' => 'Moderate comments'],
            'Audience' => ['newsletter.manage' => 'Manage newsletter', 'analytics.view' => 'View analytics'],
            'Site' => ['page.manage' => 'Manage pages', 'menu.manage' => 'Manage menus', 'ad.manage' => 'Manage advertisements'],
            'System' => ['user.manage' => 'Manage users', 'role.manage' => 'Manage roles', 'permission.manage' => 'Manage permissions', 'settings.general.manage' => 'Manage general settings', 'settings.appearance.manage' => 'Manage appearance', 'settings.seo.manage' => 'Manage SEO', 'settings.smtp.manage' => 'Manage SMTP', 'settings.system.manage' => 'Manage system settings', 'settings.code.manage' => 'Manage custom code', 'activity_log.view' => 'View activity logs'],
        ];
        $permissions = [];
        foreach ($groups as $group => $items) {
            foreach ($items as $code => $label) {
                $permissions[] = ['code' => $code, 'label' => $label, 'group' => $group];
            }
        }

        return $permissions;
    }

    /** @return list<array<string, mixed>> */
    public function users(): array
    {
        return array_map(fn (array $author, int $id): array => array_merge($author, [
            'roles' => [$this->roles()[$id % 5]], 'lastLoginAt' => new \DateTimeImmutable('2026-11-11'),
            'isActive' => 5 !== $id,
        ]), $this->authors(), range(0, 5));
    }

    /** @return list<array<string, mixed>> */
    public function activityLogs(): array
    {
        $users = $this->users();
        $actions = [
            ['article.published', 'Article', 1, 'Published “The quiet power of making things with intention”'],
            ['article.updated', 'Article', 31, 'Updated an unfinished notebook of ideas'],
            ['comment.approved', 'Comment', 1, 'Approved Alex Morgan’s conversation starter'],
            ['media.uploaded', 'Media', 4, 'Added “Into the green” to the media library'],
            ['user.login', 'User', 2, 'Signed in to the editorial workspace'],
        ];

        return array_map(static fn (array $action, int $id): array => [
            'id' => $id + 1, 'user' => $users[$id % 6], 'action' => $action[0],
            'subjectType' => $action[1], 'subjectId' => $action[2], 'context' => ['summary' => $action[3]],
            'createdAt' => new \DateTimeImmutable('2026-11-11 16:30 -'.$id.' hours'),
        ], $actions, array_keys($actions));
    }

    /** Read-only aggregates and chart projections; not live traffic or stored metrics.
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $articles = $this->articles();
        $comments = $this->comments();
        $commentCount = count($comments) + array_sum(array_map(static fn (array $comment): int => count($comment['replies']), $comments));
        $statuses = array_count_values(array_column($articles, 'status'));

        return [
            'stats' => [
                ['label' => 'Article views', 'value' => array_sum(array_column($articles, 'viewCount')), 'icon' => 'chart', 'note' => 'Illustrative lifetime views'],
                ['label' => 'Published stories', 'value' => $statuses['published'] ?? 0, 'icon' => 'edit', 'note' => count($articles).' stories in the workspace'],
                ['label' => 'Editorial team', 'value' => count($this->users()), 'icon' => 'user', 'note' => count($this->authors()).' contributing voices'],
                ['label' => 'Reader conversations', 'value' => $commentCount, 'icon' => 'mail', 'note' => '1 comment awaiting review'],
            ],
            'statuses' => $statuses, 'recentArticles' => array_slice(array_reverse($articles), 0, 5),
            'comments' => $comments, 'activity' => $this->activityLogs(),
            'views' => ['labels' => ['Nov 5', 'Nov 6', 'Nov 7', 'Nov 8', 'Nov 9', 'Nov 10', 'Nov 11'], 'values' => [1240, 1860, 1540, 2280, 2010, 2650, 3120]],
            'categories' => ['labels' => ['Design', 'Technology', 'Culture', 'Nature', 'Wellbeing', 'Work & Life'], 'values' => [32, 24, 18, 12, 8, 6]],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public function pages(): array
    {
        $pages = [
            'about' => ['title' => 'A journal for the things that matter.', 'eyebrow' => 'Our story', 'description' => 'Independent voices. Thoughtful ideas. A little more room for curiosity.', 'sections' => [
                ['title' => 'Why Fieldnotes exists', 'text' => 'We started with a simple belief: the internet can be a place to slow down, not just scroll faster. Fieldnotes brings together writers, makers and observers who pay attention to the small things that shape a meaningful life.'],
                ['title' => 'Different perspectives, shared curiosity', 'text' => 'From design and technology to culture and the natural world, we look for stories that offer a fresh way of seeing. We value clear writing, careful research and the honest admission that there is always more to learn.'],
                ['title' => 'Made with care', 'text' => 'Our editorial principles are simple: credit the people behind the work, distinguish opinion from evidence and leave readers with something useful to carry into their day. Sponsored material will always be clearly disclosed.'],
            ]],
            'contact' => ['title' => 'Let’s start a conversation.', 'eyebrow' => 'Get in touch', 'description' => 'A story idea, a thoughtful question, a new collaboration. We’d love to hear it.', 'sections' => []],
            'privacy' => ['title' => 'Your privacy matters.', 'eyebrow' => 'Privacy policy / preview edition', 'description' => 'A plain-language explanation of what this demonstration does — and does not — collect.', 'sections' => [
                ['title' => 'Preview submissions', 'text' => 'Contact, comment and newsletter forms currently validate in your browser only. No submitted email address or message is sent to a server or stored in a database. Comments you add disappear when you reload the page.'],
                ['title' => 'Local preferences', 'text' => 'Your browser may store your light/dark preference, consent choice and preview likes/bookmarks locally. These preferences are not transmitted as analytics. You can clear them through your browser’s site-data controls.'],
                ['title' => 'Third-party destinations', 'text' => 'Following social links or explicitly loading an optional video connects your browser to that provider. Their privacy policies apply. Fonts, illustrations, styles and interface scripts are hosted locally.'],
                ['title' => 'Before production', 'text' => 'Account registration, email delivery, analytics and moderation require a reviewed production privacy notice, retention policy and suitable consent controls before they are enabled. This preview is not a promise that those services are already available.'],
            ]],
            'terms' => ['title' => 'A thoughtful space, shared responsibly.', 'eyebrow' => 'Terms & conditions / preview edition', 'description' => 'Guidelines for reading, sharing and contributing to this editorial demonstration.', 'sections' => [
                ['title' => 'An implementation preview', 'text' => 'Fieldnotes is currently a development demonstration with fictional editorial records. It is not yet a production publishing, payment or membership service. Nothing in this preview creates a paid subscription or service agreement.'],
                ['title' => 'Sharing and attribution', 'text' => 'Please share links to stories rather than republishing complete articles. Original site illustrations and editorial text belong to their creators; bundled fonts retain their SIL Open Font License.'],
                ['title' => 'A constructive conversation', 'text' => 'Contributions should be relevant, respectful and free of personal attacks, unlawful material or undisclosed promotion. Moderation and reporting functionality will be connected during backend development.'],
                ['title' => 'Editorial transparency', 'text' => 'Opinion is not professional medical, legal or financial advice. Material relationships and sponsorship will be disclosed. Final production terms must be reviewed for the operator’s jurisdiction before launch.'],
            ]],
            'cookies' => ['title' => 'A clear note about cookies.', 'eyebrow' => 'Cookie policy / preview edition', 'description' => 'You should know what is running in your browser, and have a meaningful choice.', 'sections' => [
                ['title' => 'Essential site preferences', 'text' => 'This preview uses local browser storage for display preferences and your consent choice. No advertising or analytics tracking is active. Authentication session cookies will be documented when the identity module is implemented.'],
                ['title' => 'Optional services', 'text' => 'Optional analytics and advertising remain disabled even if you allow them in the preview consent panel. External videos load only when you explicitly choose to load a film; social-sharing links open only when clicked.'],
                ['title' => 'Change your mind', 'text' => 'Use the cookie-preferences control below or in the footer to revise your consent. You can also remove local preferences through your browser’s settings. A production cookie inventory and retention schedule will accompany any new tracking integration.'],
            ]],
        ];
        foreach (array_keys($pages) as $id => $slug) {
            $pages[$slug] = array_merge([
                'id' => $id + 1, 'slug' => $slug, 'content' => '',
                'createdAt' => new \DateTimeImmutable('2026-10-01'), 'updatedAt' => new \DateTimeImmutable('2026-11-11'),
            ], $pages[$slug]);
        }

        return $pages;
    }

    /** Entity-shaped fixtures for the content administration screens only.
     * @return list<array<string, mixed>>
     */
    public function contentRecords(string $module): array
    {
        return match ($module) {
            'category' => $this->categories(),
            'tag' => $this->tags(),
            'media' => array_merge(array_map(fn (array $item): array => array_merge($item, ['uploadedBy' => $this->authors()[0]]), $this->media()), [[
                'id' => 7, 'url' => 'media/editorial-guide.txt', 'filename' => 'editorial-guide.txt', 'originalName' => 'Editorial guide.txt',
                'alt' => '', 'caption' => 'A short guide for our contributors.', 'mimeType' => 'text/plain', 'size' => 263,
                'width' => null, 'height' => null, 'createdAt' => new \DateTimeImmutable('2026-11-01'), 'uploadedBy' => $this->authors()[1],
            ]]),
            'page' => array_values(array_map(static fn (array $page): array => array_merge($page, ['status' => 'published', 'seo' => ['title' => $page['title'], 'description' => $page['description'], 'noindex' => false]]), $this->pages())),
            'comment' => $this->moderationComments(),
            'menu' => $this->menus(),
            'ad' => $this->advertisements(),
            'user' => $this->users(),
            'role' => $this->roles(),
            'subscriber' => $this->subscribers(),
            'campaign' => $this->campaigns(),
            'activity' => $this->activityLogs(),
            default => [],
        };
    }

    /** Null id selects an empty, unsaved form; a missing existing id returns null.
     * @return array<string, mixed>|null
     */
    public function contentRecord(string $module, ?int $id): ?array
    {
        if (null === $id) {
            return [
                'id' => null, 'name' => '', 'title' => '', 'slug' => '', 'description' => '', 'content' => '',
                'parent' => null, 'image' => null, 'status' => 'draft', 'sections' => [], 'items' => [],
                'location' => 'header', 'placement' => 'sidebar', 'type' => 'image', 'startsAt' => null, 'endsAt' => null,
                'isActive' => false, 'priority' => 10, 'seo' => ['title' => '', 'description' => '', 'noindex' => false],
                'displayName' => '', 'email' => '', 'bio' => '', 'jobTitle' => '', 'roles' => [], 'permissions' => [],
                'subject' => '', 'html' => '', 'scheduledAt' => null, 'sentAt' => null, 'stats' => ['sent' => 0, 'failed' => 0],
            ];
        }
        foreach ($this->contentRecords($module) as $record) {
            if ($record['id'] === $id) {
                return $record;
            }
        }

        return null;
    }

    /** @return list<array<string, mixed>> */
    public function moderationComments(): array
    {
        $article = $this->articles()[0];
        $comments = $this->comments();
        $records = [$comments[0], $comments[0]['replies'][0], $comments[1]];
        $records[] = ['id' => 4, 'guestName' => 'Taylor Reed', 'content' => 'This response was removed because it did not follow the community guidelines.', 'status' => 'rejected', 'createdAt' => new \DateTimeImmutable('2026-11-10'), 'parent' => null, 'replies' => []];
        $records[] = ['id' => 5, 'guestName' => 'Promotion account', 'content' => 'An unsolicited promotional message held in the spam queue.', 'status' => 'spam', 'createdAt' => new \DateTimeImmutable('2026-11-09'), 'parent' => null, 'replies' => []];

        return array_map(static fn (array $comment): array => array_merge($comment, ['article' => $article, 'guestEmail' => 'reader'.$comment['id'].'@example.test', 'reportCount' => 'rejected' === $comment['status'] ? 1 : 0]), $records);
    }

    /** @return list<array<string, mixed>> */
    public function menus(): array
    {
        return [
            ['id' => 1, 'name' => 'Primary navigation', 'location' => 'header', 'items' => [
                ['id' => 1, 'label' => 'Journal', 'type' => 'page', 'target' => '/blog', 'parent' => null, 'position' => 0],
                ['id' => 2, 'label' => 'Design', 'type' => 'category', 'target' => '/categories/design', 'parent' => ['id' => 1], 'position' => 0],
                ['id' => 3, 'label' => 'Technology', 'type' => 'category', 'target' => '/categories/technology', 'parent' => ['id' => 1], 'position' => 1],
                ['id' => 4, 'label' => 'Our story', 'type' => 'page', 'target' => '/about', 'parent' => null, 'position' => 1],
            ]],
            ['id' => 2, 'name' => 'Reader resources', 'location' => 'footer', 'items' => [
                ['id' => 5, 'label' => 'Contributors', 'type' => 'page', 'target' => '/authors', 'parent' => null, 'position' => 0],
                ['id' => 6, 'label' => 'Get in touch', 'type' => 'page', 'target' => '/contact', 'parent' => null, 'position' => 1],
            ]],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function advertisements(): array
    {
        return array_map(fn (string $name, int $index): array => [
            'id' => $index + 1, 'name' => $name, 'placement' => ['sidebar', 'in_article', 'footer'][$index],
            'type' => ['image', 'html', 'adsense'][$index], 'content' => 1 === $index ? '<p>Support independent writing.</p>' : '',
            'image' => $this->media()[$index], 'startsAt' => new \DateTimeImmutable('2026-12-01'),
            'endsAt' => new \DateTimeImmutable('2026-12-31'), 'isActive' => 0 === $index, 'priority' => 10 + $index,
        ], ['Winter reading collection', 'Independent journal sponsorship', 'Footer network placement'], range(0, 2));
    }

    /** @return list<array<string, mixed>> */
    public function subscribers(): array
    {
        return array_map(static fn (int $id): array => [
            'id' => $id, 'email' => 'reader'.$id.'@example.test', 'status' => ['active', 'unconfirmed', 'unsubscribed'][$id % 3],
            'createdAt' => new \DateTimeImmutable('2026-10-01 +'.$id.' days'),
            'confirmedAt' => 1 === $id % 3 ? null : new \DateTimeImmutable('2026-10-03 +'.$id.' days'),
            'unsubscribedAt' => 2 === $id % 3 ? new \DateTimeImmutable('2026-11-01') : null,
        ], range(1, 18));
    }

    /** @return list<array<string, mixed>> */
    public function campaigns(): array
    {
        return array_map(static fn (int $index): array => [
            'id' => $index + 1, 'subject' => ['A little room for curiosity', 'Your weekend field guide', 'Notes from the editorial desk'][$index],
            'html' => '<h1>A little room for curiosity</h1><p>Three thoughtful stories for your week.</p>',
            'status' => ['draft', 'scheduled', 'sent'][$index], 'scheduledAt' => 1 === $index ? new \DateTimeImmutable('2026-12-01 09:00') : null,
            'sentAt' => 2 === $index ? new \DateTimeImmutable('2026-11-07 09:00') : null,
            'stats' => ['sent' => 2 === $index ? 142 : 0, 'failed' => 2 === $index ? 2 : 0],
        ], range(0, 2));
    }

    /** Structured preview defaults only. SettingsService is deferred to backend milestones.
     * @return array<string, mixed>
     */
    public function settingsPreview(): array
    {
        return [
            'general' => ['name' => 'Fieldnotes', 'tagline' => 'A journal for the things that matter.', 'description' => 'Independent voices and thoughtful stories.', 'email' => 'hello@example.test', 'contact' => 'An independent editorial studio', 'timezone' => 'UTC', 'dateFormat' => 'M j, Y', 'timeFormat' => 'H:i', 'language' => 'en', 'logo' => $this->media()[0], 'favicon' => null],
            'system' => ['registration' => false, 'comments' => true, 'anonymousComments' => true, 'doubleOptIn' => true, 'uploadLimit' => 10, 'cacheTtl' => 600, 'maintenance' => false, 'customCss' => '', 'customJavaScript' => ''],
            'appearance' => ['primary' => '#1c5b45', 'secondary' => '#bb4527', 'header' => 'editorial', 'footer' => 'columns', 'theme' => 'light', 'sidebar' => 'related', 'footerText' => 'Independent voices. Thoughtful ideas.', 'sections' => ['Featured story', 'Latest articles', 'Popular stories', 'Trending articles', 'Categories', 'Recommended reading', 'Author highlights', 'Newsletter'], 'socialLinks' => ['Instagram' => 'https://instagram.com/', 'LinkedIn' => 'https://linkedin.com/']],
            'seo' => ['title' => 'Fieldnotes — Thoughtful stories', 'description' => 'A journal for curious minds.', 'noindex' => false, 'canonicalUrl' => 'https://fieldnotes.example', 'robots' => "User-agent: *\nDisallow: /admin/\nSitemap: https://fieldnotes.example/sitemap.xml", 'sitemapArticles' => true, 'sitemapPages' => true, 'sitemapTaxonomies' => true, 'gaId' => '', 'gscId' => '', 'redirects' => [['id' => 1, 'sourcePath' => '/journal', 'targetUrl' => '/blog', 'statusCode' => 301, 'hits' => 24]]],
            'smtp' => ['host' => 'smtp.example.test', 'port' => 587, 'encryption' => 'starttls', 'username' => '', 'fromEmail' => 'hello@example.test', 'fromName' => 'Fieldnotes'],
        ];
    }

    /** Fixed daily projections for client-side date filtering, never collected traffic.
     * @return array<string, mixed>
     */
    public function analyticsPreview(): array
    {
        $articles = array_slice($this->articles(), 0, 5);
        $daily = array_map(static fn (int $day): array => [
            'date' => (new \DateTimeImmutable('2026-10-13 +'.$day.' days'))->format('Y-m-d'),
            'views' => 900 + ($day * 137 % 1700), 'engagements' => 40 + ($day * 11 % 100),
        ], range(0, 29));
        $monthly = [];
        foreach ($daily as $day) {
            $month = substr($day['date'], 0, 7);
            $monthly[$month] = ($monthly[$month] ?? 0) + $day['views'];
        }

        return [
            'daily' => $daily, 'monthly' => ['labels' => array_keys($monthly), 'values' => array_values($monthly)],
            'articles' => $articles,
            'categories' => array_slice($this->categories(), 0, 6),
            'tags' => array_slice($this->tags(), 0, 5),
            'authors' => $this->authors(),
        ];
    }

    private function slug(string $value): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($value)) ?? '', '-');
    }
}

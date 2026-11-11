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
        ], $names, array_keys($names));
    }

    /** @return list<array<string, mixed>> */
    public function tags(): array
    {
        $names = ['Creativity', 'Slow living', 'Web design', 'AI', 'Sustainability', 'Architecture', 'Photography', 'Books', 'Travel', 'Community', 'Mindfulness', 'Remote work', 'Typography', 'Open source', 'Food', 'Gardens', 'Craft', 'Cities', 'Learning', 'Writing', 'Research', 'Accessibility', 'Habits', 'Innovation', 'Interviews'];

        return array_map(fn (string $name, int $id): array => ['id' => $id + 1, 'name' => $name, 'slug' => $this->slug($name), 'description' => 'Stories and perspectives about '.strtolower($name).'.', 'articleCount' => 2 + $id % 8], $names, array_keys($names));
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
                'content' => '', 'status' => $status, 'author' => $authors[$id % 6],
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
        return array_map(static fn (string $name, int $id): array => ['id' => $id + 1, 'name' => $name, 'description' => $name.' editorial access', 'permissions' => [], 'userCount' => $id + 1], ['Super Administrator', 'Administrator', 'Editor', 'Author', 'Contributor'], range(0, 4));
    }

    /** @return list<array<string, mixed>> */
    public function users(): array
    {
        return array_map(fn (array $author, int $id): array => array_merge($author, [
            'roles' => [$this->roles()[$id % 5]], 'lastLoginAt' => new \DateTimeImmutable('2026-11-11'),
        ]), $this->authors(), range(0, 5));
    }

    private function slug(string $value): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($value)) ?? '', '-');
    }
}

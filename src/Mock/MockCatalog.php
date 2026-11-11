<?php

declare(strict_types=1);

namespace App\Mock;

/** Read-only preview querying; never persists or implements CMS business rules. */
final readonly class MockCatalog
{
    public function __construct(private MockDataProvider $data)
    {
    }

    /** @return array<string, mixed> */
    public function layout(): array
    {
        return ['categories' => $this->data->categories(), 'tags' => $this->data->tags(), 'authors' => $this->data->authors()];
    }

    /** @param array<string, string> $filters
     * @return list<array<string, mixed>>
     */
    public function articles(array $filters = []): array
    {
        $articles = array_values(array_filter($this->data->articles(), static function (array $article) use ($filters): bool {
            if ('published' !== $article['status']) {
                return false;
            }
            if (isset($filters['q']) && '' !== $filters['q'] && !str_contains(mb_strtolower($article['title'].' '.$article['excerpt']), mb_strtolower($filters['q']))) {
                return false;
            }
            foreach (['category' => 'categories', 'tag' => 'tags'] as $key => $relation) {
                if (!empty($filters[$key]) && !in_array($filters[$key], array_column($article[$relation], 'slug'), true)) {
                    return false;
                }
            }

            return empty($filters['author']) || $article['author']['slug'] === $filters['author'];
        }));
        $sort = $filters['sort'] ?? 'latest';
        usort($articles, static fn (array $a, array $b): int => match ($sort) {
            'popular' => $b['viewCount'] <=> $a['viewCount'],
            'oldest' => $a['publishedAt'] <=> $b['publishedAt'],
            default => $b['publishedAt'] <=> $a['publishedAt'],
        });

        return $articles;
    }

    /** @return array<string, mixed>|null */
    public function article(string $slug): ?array
    {
        foreach ($this->articles() as $article) {
            if ($article['slug'] === $slug) {
                return $article;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    public function collection(string $kind, string $slug): ?array
    {
        $items = match ($kind) {
            'author' => $this->data->authors(),
            'category' => $this->data->categories(),
            'tag' => $this->data->tags(),
            default => [],
        };
        foreach ($items as $item) {
            if ($item['slug'] === $slug) {
                return $item;
            }
        }

        return null;
    }
}

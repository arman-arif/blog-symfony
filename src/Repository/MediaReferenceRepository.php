<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Media;
use Doctrine\DBAL\Connection;

final readonly class MediaReferenceRepository
{
    public function __construct(private Connection $connection)
    {
    }

    /** @return list<string> Includes restorable trash and revision snapshots. */
    public function usages(Media $media): array
    {
        $relations = [
            'article featured image' => ['article', 'featured_image_id'],
            'category image' => ['category', 'image_id'],
            'advertisement image' => ['advertisement', 'image_id'],
            'profile avatar' => ['app_user', 'avatar_id'],
        ];
        $usages = [];
        foreach ($relations as $label => [$table, $column]) {
            if (false !== $this->connection->fetchOne('SELECT id FROM '.$this->connection->quoteSingleIdentifier($table).' WHERE '.$column.' = ? LIMIT 1', [$media->getId()])) {
                $usages[] = $label;
            }
        }

        $texts = [
            'article content' => ['article', 'content'],
            'article revision' => ['article_revision', 'content'],
            'page content' => ['page', 'content'],
            'newsletter content' => ['newsletter_campaign', 'html'],
            'advertisement content' => ['advertisement', 'content'],
            'article SEO image' => ['article', 'seo_og_image_url'],
            'page SEO image' => ['page', 'seo_og_image_url'],
            'category SEO image' => ['category', 'seo_og_image_url'],
            'tag SEO image' => ['tag', 'seo_og_image_url'],
            'website settings (including logo/favicon)' => ['app_setting', 'value'],
        ];
        // Filename narrows candidates even for JSON-escaped or URL-encoded paths.
        $filename = basename($media->getStorageKey());
        $pattern = '%'.strtr($filename, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
        $key = preg_quote($media->getStorageKey(), '~');
        foreach ($texts as $label => [$table, $column]) {
            $column = $this->connection->quoteSingleIdentifier($column);
            $result = $this->connection->executeQuery('SELECT '.$column.' FROM '.$table.' WHERE '.$column." LIKE ? ESCAPE '!'", [$pattern]);
            try {
                while (false !== ($text = $result->fetchOne())) {
                    $decoded = rawurldecode(html_entity_decode(str_replace('\\/', '/', (string) $text), \ENT_QUOTES | \ENT_HTML5, 'UTF-8'));
                    if (1 === preg_match('~(?<![a-zA-Z0-9_.%+-])'.$key.'(?:\.webp)?(?![a-zA-Z0-9_.%+-])~', $decoded)) {
                        $usages[] = $label;
                        break;
                    }
                }
            } finally {
                $result->free();
            }
        }

        return $usages;
    }
}

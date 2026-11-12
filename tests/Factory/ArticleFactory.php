<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Article;
use App\Enum\ArticleStatus;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/** @extends PersistentObjectFactory<Article> */
final class ArticleFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Article::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'title' => self::faker()->unique()->sentence(6),
            'subtitle' => self::faker()->sentence(),
            'excerpt' => self::faker()->sentence(),
            'content' => '<p>'.self::faker()->paragraph().'</p>',
            'status' => ArticleStatus::Draft,
            'author' => UserFactory::new(),
            'allowComments' => true,
        ];
    }
}

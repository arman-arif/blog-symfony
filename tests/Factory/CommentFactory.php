<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Comment;
use App\Enum\CommentStatus;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/** @extends PersistentObjectFactory<Comment> */
final class CommentFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Comment::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'article' => ArticleFactory::new(),
            'guestName' => self::faker()->name(),
            'guestEmail' => self::faker()->safeEmail(),
            'content' => self::faker()->sentence(),
            'status' => CommentStatus::Pending,
        ];
    }
}

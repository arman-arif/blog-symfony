<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\User;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/** @extends PersistentObjectFactory<User> */
final class UserFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return User::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'email' => self::faker()->unique()->safeEmail(),
            'displayName' => self::faker()->name(),
            'jobTitle' => self::faker()->jobTitle(),
            'bio' => self::faker()->sentence(),
            'password' => password_hash('password', \PASSWORD_BCRYPT),
            'isActive' => true,
            'isVerified' => true,
        ];
    }
}

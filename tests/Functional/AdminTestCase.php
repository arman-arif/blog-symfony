<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Role;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/** Admin UI tests run as a real super-administrator entity. */
abstract class AdminTestCase extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    protected static ?User $adminUser = null;

    /** @param array<string, mixed> $options
     * @param array<string, mixed> $server
     */
    protected static function createClient(array $options = [], array $server = []): KernelBrowser
    {
        $client = parent::createClient($options, $server);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $role = (new Role())
            ->setName('Test Super Administrator')
            ->setCode('ROLE_SUPER_ADMIN')
            ->setIsSystem(true);
        $user = (new User())
            ->setEmail('ui-'.bin2hex(random_bytes(6)).'@example.test')
            ->setDisplayName('UI Administrator')
            ->setIsActive(true)
            ->setIsVerified(true)
            ->addAssignedRole($role);
        $user->setPassword(password_hash('test-password', \PASSWORD_BCRYPT, ['cost' => 4]));

        $entityManager->persist($role);
        $entityManager->persist($user);
        $entityManager->flush();

        self::$adminUser = $user;
        $client->loginUser($user);

        return $client;
    }

    /** @param array<string, mixed> $options
     * @param array<string, mixed> $server
     */
    protected static function createRawClient(array $options = [], array $server = []): KernelBrowser
    {
        return parent::createClient($options, $server);
    }

    protected static function authenticatedUser(): User
    {
        if (null === self::$adminUser) {
            throw new \LogicException('Call createClient() first.');
        }

        return self::$adminUser;
    }
}

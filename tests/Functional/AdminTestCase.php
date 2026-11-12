<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Role;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Admin UI tests run as a real super-administrator entity. */
abstract class AdminTestCase extends WebTestCase
{
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

        $client->loginUser($user);

        return $client;
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Repository\UserRepository;
use App\Security\UserChecker;
use App\Service\Settings\DatabaseSettingsService;
use App\Service\Settings\DefaultSettingsService;
use App\Service\Settings\SettingsCatalog;
use App\Tests\Support\InMemorySettingsService;

final class SettingsDefaultsRegressionTest extends FrontTestCase
{
    public function testEveryRuntimeDefaultIsReadableThroughTheProductionService(): void
    {
        self::createClient();
        $this->seed();
        $settings = self::getContainer()->get(DatabaseSettingsService::class);
        foreach (DefaultSettingsService::DEFAULTS as $key => $default) {
            self::assertNotSame('', SettingsCatalog::groupForKey($key));
            self::assertSame($default, $settings->get($key), $key);
            self::assertSame($default, match (true) {
                is_bool($default) => $settings->getBool($key),
                is_int($default) => $settings->getInt($key),
                is_array($default) => $settings->getArray($key),
                default => $settings->getString($key),
            }, $key);
        }

        foreach (self::getContainer()->get(UserRepository::class)->findAll() as $user) {
            if ($user->isActive()) {
                (new UserChecker($settings))->checkPreAuth($user);
            }
        }
    }

    public function testTestSettingsDoNotMaskUnknownProductionKeys(): void
    {
        self::createClient();
        $this->seed();
        $this->expectException(\OutOfBoundsException::class);
        self::getContainer()->get(InMemorySettingsService::class)->getBool('comments.anonynous');
    }

    public function testProductionTypedGetterRejectsWrongTypes(): void
    {
        self::createClient();
        $this->seed();
        $this->expectException(\UnexpectedValueException::class);
        self::getContainer()->get(DatabaseSettingsService::class)->getInt('newsletter.double_opt_in');
    }
}

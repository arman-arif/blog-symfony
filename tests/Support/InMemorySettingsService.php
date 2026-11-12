<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Service\Settings\DatabaseSettingsService;
use App\Service\Settings\DefaultSettingsService;
use App\Service\Settings\SettingsServiceInterface;

/** Mutable defaults for tests that need a setting to differ from the committed default. */
final class InMemorySettingsService implements SettingsServiceInterface
{
    public function __construct(private readonly DatabaseSettingsService $database)
    {
    }

    /** @var array<string, mixed> */
    private static array $overrides = [];

    public static function setOverride(string $key, mixed $value): void
    {
        self::$overrides[$key] = $value;
    }

    public static function resetOverride(): void
    {
        self::$overrides = [];
    }

    public function set(string $key, mixed $value): void
    {
        self::setOverride($key, $value);
    }

    public function getString(string $key): string
    {
        $value = $this->value($key);

        return is_string($value) ? $value : throw new \UnexpectedValueException('Setting is not a string: '.$key);
    }

    public function getBool(string $key): bool
    {
        $value = $this->value($key);

        return is_bool($value) ? $value : throw new \UnexpectedValueException('Setting is not a boolean: '.$key);
    }

    public function getInt(string $key): int
    {
        $value = $this->value($key);

        return is_int($value) ? $value : throw new \UnexpectedValueException('Setting is not an integer: '.$key);
    }

    public function getArray(string $key): array
    {
        $value = $this->value($key);

        return is_array($value) ? $value : throw new \UnexpectedValueException('Setting is not an array: '.$key);
    }

    private function value(string $key): mixed
    {
        if (array_key_exists($key, self::$overrides)) {
            return self::$overrides[$key];
        }

        try {
            return $this->database->get($key);
        } catch (\OutOfBoundsException) {
            return DefaultSettingsService::DEFAULTS[$key] ?? throw new \OutOfBoundsException('Unknown setting: '.$key);
        }
    }
}

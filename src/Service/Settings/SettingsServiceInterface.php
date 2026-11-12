<?php

declare(strict_types=1);

namespace App\Service\Settings;

interface SettingsServiceInterface
{
    public function getString(string $key): string;

    public function getBool(string $key): bool;

    public function getInt(string $key): int;

    /** @return array<array-key, mixed> */
    public function getArray(string $key): array;
}

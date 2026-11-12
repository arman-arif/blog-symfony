<?php

declare(strict_types=1);

namespace App\Security;

use App\Service\Settings\SettingsEncryption;

final readonly class TotpSecretCipher
{
    private const string PREFIX = 'enc:v1:';

    public function __construct(private SettingsEncryption $encryption)
    {
    }

    public function encrypt(string $secret): string
    {
        return self::PREFIX.$this->encryption->encrypt($secret);
    }

    public function decrypt(string $stored): string
    {
        return str_starts_with($stored, self::PREFIX)
            ? $this->encryption->decrypt(substr($stored, strlen(self::PREFIX)))
            : $stored;
    }

    public function isEncrypted(string $stored): bool
    {
        return str_starts_with($stored, self::PREFIX);
    }
}

<?php

declare(strict_types=1);

namespace App\Service\Settings;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class SettingsEncryption
{
    private string $key;

    public function __construct(
        #[Autowire('%kernel.secret%')]
        string $secret,
        #[Autowire('%env(default::SETTINGS_ENCRYPTION_KEY)%')]
        ?string $configuredKey,
    ) {
        $keyMaterial = $configuredKey ?? '';
        $this->key = hash('sha256', '' !== $keyMaterial ? $keyMaterial : $secret, true);
    }

    public function encrypt(string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plain, $nonce, $this->key);

        return base64_encode($nonce.$cipher);
    }

    public function decrypt(string $encoded): string
    {
        $decoded = base64_decode($encoded, true);
        if (false === $decoded || strlen($decoded) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return '';
        }
        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open($cipher, $nonce, $this->key);

        return false === $plain ? '' : $plain;
    }
}

<?php

declare(strict_types=1);

namespace App\Service\Settings;

/** M2 defaults only. M13 supplies the cached, persisted implementation of the same contract. */
final readonly class DefaultSettingsService implements SettingsServiceInterface
{
    public const array DEFAULTS = [
        'general.name' => 'Fieldnotes',
        'general.tagline' => 'Ideas worth keeping.',
        'general.description' => 'Independent stories about thoughtful work, culture and everyday life.',
        'general.timezone' => 'UTC',
        'general.locale' => 'en',
        'general.date_format' => 'M j, Y',
        'general.time_format' => 'H:i',
        'general.contact_email' => '',
        'appearance.theme' => 'system',
        'appearance.social_links' => [],
        'registration.enabled' => false,
        'comments.enabled' => true,
        'comments.anonymous' => false,
        'comments.max_depth' => 3,
        'comments.blocked_words' => [],
        'newsletter.double_opt_in' => true,
        'media.max_upload_bytes' => 10485760,
        'mail.from_address' => '',
        'mail.from_name' => 'Fieldnotes',
        'maintenance.enabled' => false,
        'security.require_admin_2fa' => false,
        'seo.title_suffix' => 'Fieldnotes',
        'seo.description' => 'Independent stories about thoughtful work, culture and everyday life.',
        'seo.robots' => "User-agent: *\nAllow: /\nDisallow: /admin/\n",
        'analytics.retention_days' => 90,
    ];

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
        return self::DEFAULTS[$key] ?? throw new \OutOfBoundsException('Unknown setting: '.$key);
    }
}

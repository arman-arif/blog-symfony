<?php

declare(strict_types=1);

namespace App\Service\Settings;

use Symfony\Component\Intl\Locales;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;

final class SettingsCatalog
{
    /** @var array<string, string> */
    public const GROUPS = [
        'general' => 'General',
        'appearance' => 'Appearance',
        'seo' => 'SEO',
        'smtp' => 'Email / SMTP',
        'code' => 'Custom code',
        'system' => 'System',
    ];

    /** @var array<string, array{group: string, label: string, type: string, help?: string, sensitive?: bool, choices?: array<string, string>}> */
    public const FIELDS = [
        'general.name' => ['group' => 'general', 'label' => 'Website name', 'type' => 'text'],
        'general.tagline' => ['group' => 'general', 'label' => 'Tagline', 'type' => 'text'],
        'general.description' => ['group' => 'general', 'label' => 'Website description', 'type' => 'textarea'],
        'general.contact_email' => ['group' => 'general', 'label' => 'Contact email', 'type' => 'email'],
        'general.timezone' => ['group' => 'general', 'label' => 'Timezone', 'type' => 'text'],
        'general.locale' => ['group' => 'general', 'label' => 'Locale', 'type' => 'text'],
        'general.date_format' => ['group' => 'general', 'label' => 'Date format', 'type' => 'text'],
        'general.time_format' => ['group' => 'general', 'label' => 'Time format', 'type' => 'text'],
        'registration.enabled' => ['group' => 'general', 'label' => 'Allow reader registration', 'type' => 'bool'],
        'comments.enabled' => ['group' => 'general', 'label' => 'Enable comments', 'type' => 'bool'],
        'comments.anonymous' => ['group' => 'general', 'label' => 'Allow guest comments', 'type' => 'bool'],
        'comments.max_depth' => ['group' => 'general', 'label' => 'Maximum comment nesting', 'type' => 'int'],
        'comments.blocked_words' => ['group' => 'general', 'label' => 'Blocked comment words', 'type' => 'lines', 'help' => 'One word or phrase per line.'],
        'comments.auto_approve_trusted' => ['group' => 'general', 'label' => 'Auto-approve trusted readers', 'type' => 'bool'],
        'comments.max_links' => ['group' => 'general', 'label' => 'Maximum comment links', 'type' => 'int'],
        'comments.min_seconds' => ['group' => 'general', 'label' => 'Minimum comment typing time (seconds)', 'type' => 'int'],
        'newsletter.double_opt_in' => ['group' => 'general', 'label' => 'Require newsletter email confirmation', 'type' => 'bool'],
        'maintenance.enabled' => ['group' => 'general', 'label' => 'Maintenance mode', 'type' => 'bool', 'help' => 'Signed-in administrators can still browse while maintenance mode is on.'],
        'maintenance.message' => ['group' => 'general', 'label' => 'Maintenance message', 'type' => 'textarea'],
        'appearance.primary_color' => ['group' => 'appearance', 'label' => 'Primary colour', 'type' => 'color'],
        'appearance.accent_color' => ['group' => 'appearance', 'label' => 'Accent colour', 'type' => 'color'],
        'appearance.theme' => ['group' => 'appearance', 'label' => 'Default theme', 'type' => 'choice', 'choices' => ['system' => 'System', 'light' => 'Light', 'dark' => 'Dark']],
        'appearance.logo' => ['group' => 'appearance', 'label' => 'Logo URL or media path', 'type' => 'text'],
        'appearance.favicon' => ['group' => 'appearance', 'label' => 'Favicon URL or media path', 'type' => 'text'],
        'appearance.header_tagline' => ['group' => 'appearance', 'label' => 'Header announcement', 'type' => 'text'],
        'appearance.footer_text' => ['group' => 'appearance', 'label' => 'Footer introduction', 'type' => 'textarea'],
        'appearance.show_header' => ['group' => 'appearance', 'label' => 'Show public header', 'type' => 'bool'],
        'appearance.show_footer' => ['group' => 'appearance', 'label' => 'Show public footer', 'type' => 'bool'],
        'appearance.instagram_url' => ['group' => 'appearance', 'label' => 'Instagram URL', 'type' => 'url'],
        'appearance.linkedin_url' => ['group' => 'appearance', 'label' => 'LinkedIn URL', 'type' => 'url'],
        'appearance.social_links' => ['group' => 'appearance', 'label' => 'Additional social links', 'type' => 'json', 'help' => 'JSON list of objects with label and https URL, for example [{"label":"Mastodon","url":"https://example.social/"}].'],
        'appearance.homepage_sections' => ['group' => 'appearance', 'label' => 'Homepage section order', 'type' => 'order', 'help' => 'Comma-separated: hero, latest, favorites, categories, stories, recommended, authors.'],
        'appearance.sidebar_widgets' => ['group' => 'appearance', 'label' => 'Article sidebar widgets', 'type' => 'multiple', 'choices' => ['toc' => 'Table of contents', 'about' => 'About the journal', 'ad' => 'Advertisement', 'newsletter' => 'Newsletter']],
        'seo.title_suffix' => ['group' => 'seo', 'label' => 'Title suffix', 'type' => 'text'],
        'seo.description' => ['group' => 'seo', 'label' => 'Default description', 'type' => 'textarea'],
        'seo.robots' => ['group' => 'seo', 'label' => 'robots.txt', 'type' => 'code'],
        'seo.ga_id' => ['group' => 'seo', 'label' => 'GA4 measurement ID', 'type' => 'text', 'help' => 'The GA4 script is only loaded after analytics consent.'],
        'seo.gsc_verification' => ['group' => 'seo', 'label' => 'Search Console verification', 'type' => 'text'],
        'mail.transport' => ['group' => 'smtp', 'label' => 'Transport', 'type' => 'choice', 'choices' => ['env' => 'MAILER_DSN environment', 'smtp' => 'SMTP', 'sendmail' => 'Sendmail']],
        'mail.host' => ['group' => 'smtp', 'label' => 'SMTP host', 'type' => 'text'],
        'mail.port' => ['group' => 'smtp', 'label' => 'SMTP port', 'type' => 'int'],
        'mail.username' => ['group' => 'smtp', 'label' => 'SMTP username', 'type' => 'text'],
        'mail.password' => ['group' => 'smtp', 'label' => 'SMTP password', 'type' => 'password', 'sensitive' => true, 'help' => 'Leave blank to keep the saved password. Stored encrypted at rest.'],
        'mail.encryption' => ['group' => 'smtp', 'label' => 'Encryption', 'type' => 'choice', 'choices' => ['tls' => 'TLS', 'ssl' => 'SSL', '' => 'None']],
        'mail.from_address' => ['group' => 'smtp', 'label' => 'From address', 'type' => 'email'],
        'mail.from_name' => ['group' => 'smtp', 'label' => 'From name', 'type' => 'text'],
        'custom.css' => ['group' => 'code', 'label' => 'Custom CSS', 'type' => 'code'],
        'custom.js' => ['group' => 'code', 'label' => 'Custom JavaScript', 'type' => 'code'],
        'security.require_admin_2fa' => ['group' => 'system', 'label' => 'Require administrator two-factor authentication', 'type' => 'bool', 'help' => 'Enroll all administrators before enabling this; unenrolled administrators will not be able to sign in.'],
        'media.max_upload_bytes' => ['group' => 'system', 'label' => 'Maximum upload size (bytes)', 'type' => 'int', 'help' => 'Cannot exceed the shipped 12 MiB web/PHP request limit. Decoder safety ceilings still apply.'],
        'analytics.retention_days' => ['group' => 'system', 'label' => 'Raw analytics retention (days)', 'type' => 'int', 'help' => 'At least seven days for trending. Daily rollups are retained.'],
    ];

    /** @var array<string, array{int, int}> */
    public const LIMITS = [
        'mail.port' => [1, 65535], 'comments.max_depth' => [0, 10], 'comments.max_links' => [0, 100],
        'comments.min_seconds' => [0, 3600], 'media.max_upload_bytes' => [1024, 12582912],
        'analytics.retention_days' => [7, 3650],
    ];

    /** @return list<Constraint> Shared by forms and service callers; no write occurs before validation. */
    public static function constraints(string $key): array
    {
        $field = self::FIELDS[$key] ?? throw new \OutOfBoundsException('Unknown setting: '.$key);
        $type = $field['type'];
        $constraints = [new Assert\NotNull(), new Assert\Type(match ($type) {
            'bool' => 'bool', 'int' => 'int', 'order', 'multiple', 'json', 'lines' => 'list', default => 'string',
        })];
        if (isset(self::LIMITS[$key])) {
            [$min, $max] = self::LIMITS[$key];
            $constraints[] = new Assert\Range(min: $min, max: $max);
        }
        if ('choice' === $type) {
            $constraints[] = new Assert\Choice(choices: array_keys($field['choices'] ?? []));
        }
        if (in_array($type, ['order', 'multiple'], true)) {
            $choices = 'order' === $type ? ['hero', 'latest', 'favorites', 'categories', 'stories', 'recommended', 'authors'] : array_keys($field['choices'] ?? []);
            $constraints[] = new Assert\Count(max: count($choices));
            $constraints[] = new Assert\Unique();
            $constraints[] = new Assert\All([new Assert\Type('string'), new Assert\Choice(choices: $choices)]);
        } elseif ('lines' === $type) {
            $constraints[] = new Assert\Count(max: 500);
            $constraints[] = new Assert\Unique();
            $constraints[] = new Assert\All([new Assert\NotBlank(), new Assert\Type('string'), new Assert\Length(max: 100)]);
        } elseif ('json' === $type) {
            $constraints[] = new Assert\Count(max: 20);
            $constraints[] = new Assert\All([new Assert\Sequentially([new Assert\Type('array'), new Assert\Collection(fields: [
                'label' => [new Assert\NotBlank(), new Assert\Type('string'), new Assert\Length(max: 100)],
                'url' => [new Assert\NotBlank(), new Assert\Type('string'), new Assert\Url(protocols: ['https']), new Assert\Length(max: 2048)],
            ])])]);
        } elseif ('bool' !== $type && 'int' !== $type) {
            $constraints[] = new Assert\Length(max: in_array($type, ['textarea', 'code', 'password'], true) ? 65535 : 2048);
        }
        if ('email' === $type) {
            $constraints[] = new Assert\Email();
        } elseif ('url' === $type) {
            $constraints[] = new Assert\Url(protocols: ['http', 'https']);
        } elseif ('color' === $type) {
            $constraints[] = new Assert\Regex('/^#[a-fA-F0-9]{6}$/');
        }
        $constraints = array_merge($constraints, match ($key) {
            'general.name' => [new Assert\NotBlank(), new Assert\Length(max: 150)],
            'general.timezone' => [new Assert\NotBlank(), new Assert\Timezone()],
            'general.locale' => [new Assert\NotBlank(), new Assert\Choice(choices: array_keys(Locales::getNames()))],
            'general.date_format' => [new Assert\Choice(choices: ['M j, Y', 'F j, Y', 'Y-m-d', 'd/m/Y', 'm/d/Y'])],
            'general.time_format' => [new Assert\Choice(choices: ['H:i', 'H:i:s', 'g:i a', 'h:i A'])],
            'seo.ga_id' => [new Assert\Regex('/^G-[A-Z0-9]+$/')],
            'appearance.logo', 'appearance.favicon' => [new Assert\Regex('#^(?:https?://[^\s]+|/(?!/)[^\s]*)$#')],
            default => [],
        });

        return [new Assert\Sequentially($constraints)];
    }

    public static function formName(string $key): string
    {
        return str_replace('.', '_', $key);
    }

    /** @return array<string, array{group: string, label: string, type: string, help?: string, sensitive?: bool, choices?: array<string, string>}> */
    public static function fieldsForGroup(string $group): array
    {
        return array_filter(self::FIELDS, static fn (array $field): bool => $field['group'] === $group);
    }

    public static function groupForKey(string $key): string
    {
        if (isset(self::FIELDS[$key])) {
            return self::FIELDS[$key]['group'];
        }
        // Runtime settings are valid even when they do not yet have an admin control.
        // Keep unknown keys strict: accepting arbitrary prefixes would hide typos.
        if (!array_key_exists($key, DefaultSettingsService::DEFAULTS)) {
            throw new \OutOfBoundsException('Unknown setting: '.$key);
        }

        return match (strstr($key, '.', true)) {
            'appearance' => 'appearance',
            'security', 'analytics', 'media' => 'system',
            default => 'general',
        };
    }

    public static function isSensitive(string $key): bool
    {
        return (bool) (self::FIELDS[$key]['sensitive'] ?? false);
    }
}

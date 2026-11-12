<?php

declare(strict_types=1);

namespace App\Service\Settings;

final class SettingsCatalog
{
    /** @var array<string, string> */
    public const GROUPS = [
        'general' => 'General',
        'appearance' => 'Appearance',
        'seo' => 'SEO',
        'smtp' => 'Email / SMTP',
        'code' => 'Custom code',
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
    ];

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
        return self::FIELDS[$key]['group'] ?? throw new \OutOfBoundsException('Unknown setting: '.$key);
    }

    public static function isSensitive(string $key): bool
    {
        return (bool) (self::FIELDS[$key]['sensitive'] ?? false);
    }
}

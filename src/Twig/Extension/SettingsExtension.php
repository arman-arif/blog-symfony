<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Service\Settings\SettingsServiceInterface;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFunction;

final class SettingsExtension extends AbstractExtension
{
    public function __construct(private readonly SettingsServiceInterface $settings)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('setting', $this->setting(...)),
            new TwigFunction('appearance_css', $this->appearanceCss(...), ['is_safe' => ['html']]),
        ];
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        try {
            return $this->settings->getString($key);
        } catch (\UnexpectedValueException) {
            try {
                return $this->settings->getBool($key);
            } catch (\UnexpectedValueException) {
                try {
                    return $this->settings->getInt($key);
                } catch (\UnexpectedValueException) {
                    try {
                        return $this->settings->getArray($key);
                    } catch (\UnexpectedValueException) {
                        return $default;
                    }
                }
            }
        }
    }

    public function appearanceCss(): Markup
    {
        $primary = $this->rgb($this->string('appearance.primary_color', '#1c5b45'));
        $accent = $this->rgb($this->string('appearance.accent_color', '#bb4527'));

        return new Markup(':root{--color-primary:'.$primary.';--color-secondary:'.$accent.'}', 'UTF-8');
    }

    private function string(string $key, string $default): string
    {
        try {
            return $this->settings->getString($key) ?: $default;
        } catch (\Throwable) {
            return $default;
        }
    }

    private function rgb(string $hex): string
    {
        if (!preg_match('/^#?([0-9a-f]{6})$/i', $hex, $matches)) {
            return '28 91 69';
        }
        $value = hexdec($matches[1]);

        return (($value >> 16) & 255).' '.(($value >> 8) & 255).' '.($value & 255);
    }
}

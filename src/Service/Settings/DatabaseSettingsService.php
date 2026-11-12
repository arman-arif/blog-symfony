<?php

declare(strict_types=1);

namespace App\Service\Settings;

use App\Entity\Setting;
use App\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final readonly class DatabaseSettingsService implements SettingsServiceInterface
{
    public function __construct(
        private SettingRepository $settings,
        private EntityManagerInterface $entityManager,
        private SettingsEncryption $encryption,
        #[Autowire(service: 'cache.content')]
        private TagAwareCacheInterface $cache,
    ) {
    }

    public function getString(string $key): string
    {
        $value = $this->get($key);

        return is_string($value) ? $value : throw new \UnexpectedValueException('Setting is not a string: '.$key);
    }

    public function getBool(string $key): bool
    {
        $value = $this->get($key);

        return is_bool($value) ? $value : throw new \UnexpectedValueException('Setting is not a boolean: '.$key);
    }

    public function getInt(string $key): int
    {
        $value = $this->get($key);

        return is_int($value) ? $value : throw new \UnexpectedValueException('Setting is not an integer: '.$key);
    }

    public function getArray(string $key): array
    {
        $value = $this->get($key);

        return is_array($value) ? $value : throw new \UnexpectedValueException('Setting is not an array: '.$key);
    }

    public function get(string $key): mixed
    {
        SettingsCatalog::groupForKey($key);
        $raw = $this->raw()[$key] ?? null;
        if (null === $raw) {
            return DefaultSettingsService::DEFAULTS[$key] ?? null;
        }
        $value = $raw['value'];
        if ($raw['sensitive']) {
            return is_string($value) ? $this->encryption->decrypt($value) : '';
        }

        return $value;
    }

    /** @return array<string, mixed> */
    public function valuesForGroup(string $group): array
    {
        $values = [];
        foreach (SettingsCatalog::fieldsForGroup($group) as $key => $field) {
            $value = $this->get($key);
            if ('order' === $field['type']) {
                $value = implode(', ', is_array($value) ? $value : []);
            }
            if ('password' === $field['type']) {
                $value = '';
            }
            $values[SettingsCatalog::formName($key)] = $value;
        }

        return $values;
    }

    /** @param array<string, mixed> $values */
    public function saveGroup(string $group, array $values): void
    {
        foreach (SettingsCatalog::fieldsForGroup($group) as $key => $field) {
            $formName = SettingsCatalog::formName($key);
            if (!array_key_exists($formName, $values)) {
                continue;
            }
            $value = $values[$formName];
            if ('order' === $field['type']) {
                $value = is_array($value) ? array_values($value) : array_values(array_filter(array_map(trim(...), explode(',', (string) $value))));
                $allowed = ['hero', 'latest', 'favorites', 'categories', 'stories', 'recommended', 'authors'];
                $value = array_values(array_filter($value, static fn (string $section): bool => in_array($section, $allowed, true)));
            }
            if ('multiple' === $field['type']) {
                $value = is_array($value) ? array_values($value) : [];
            }
            if (($field['sensitive'] ?? false) && '' === $value) {
                continue;
            }

            $setting = $this->settings->findOneByKey($key) ?? (new Setting())->setKey($key);
            $setting->setGroupName($group)->setIsSensitive((bool) ($field['sensitive'] ?? false));
            $setting->setValue(['value' => ($field['sensitive'] ?? false) ? $this->encryption->encrypt((string) $value) : $value]);
            $this->entityManager->persist($setting);
        }
        $this->entityManager->flush();
        $this->cache->invalidateTags(['settings']);
    }

    /** @return array<string, array{value: mixed, sensitive: bool}> */
    private function raw(): array
    {
        return $this->cache->get('settings.raw.v2', function (ItemInterface $item): array {
            $item->expiresAfter(3600);
            $item->tag(['settings']);
            $rows = [];
            foreach ($this->settings->findAll() as $setting) {
                $stored = $setting->getValue();
                $rows[$setting->getKey()] = [
                    'value' => $stored['value'] ?? null,
                    'sensitive' => $setting->isSensitive(),
                ];
            }

            return $rows;
        });
    }
}

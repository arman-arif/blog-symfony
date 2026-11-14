<?php

declare(strict_types=1);

namespace App\Service\Settings;

use App\Entity\Setting;
use App\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
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
        private ValidatorInterface $validator,
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
                $value = implode(', ', is_array($value) ? array_map(static fn (mixed $part): string => is_scalar($part) ? (string) $part : '', $value) : []);
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
        if (!array_key_exists($group, SettingsCatalog::GROUPS)) {
            throw new \OutOfBoundsException('Unknown settings group.');
        }
        $fields = SettingsCatalog::fieldsForGroup($group);
        $known = array_map(SettingsCatalog::formName(...), array_keys($fields));
        if ([] !== array_diff(array_keys($values), $known)) {
            throw new \InvalidArgumentException('Unknown setting field.');
        }
        $pending = [];
        foreach (SettingsCatalog::fieldsForGroup($group) as $key => $field) {
            $formName = SettingsCatalog::formName($key);
            if (!array_key_exists($formName, $values)) {
                continue;
            }
            $value = $values[$formName];
            if ('order' === $field['type'] && is_string($value)) {
                $value = array_values(array_filter(array_map(trim(...), explode(',', $value)), static fn (string $part): bool => '' !== $part));
            }
            if (($field['sensitive'] ?? false) && '' === $value) {
                continue;
            }
            $violations = $this->validator->validate($value, SettingsCatalog::constraints($key));
            if (count($violations) > 0) {
                throw new ValidationFailedException($value, $violations);
            }
            $pending[$key] = $value;
        }
        $this->entityManager->wrapInTransaction(function () use ($pending, $group): void {
            foreach ($pending as $key => $value) {
                $sensitive = SettingsCatalog::isSensitive($key);
                if ($sensitive && !is_string($value)) {
                    throw new \LogicException('A sensitive setting must be a validated string.');
                }
                $setting = $this->settings->findOneByKey($key) ?? (new Setting())->setKey($key);
                $setting->setGroupName($group)->setIsSensitive($sensitive);
                $setting->setValue(['value' => $sensitive ? $this->encryption->encrypt($value) : $value]);
                $this->entityManager->persist($setting);
            }
        });
        $this->cache->invalidateTags(['settings']);
    }

    /** @return array<string, array{value: mixed, sensitive: bool}> */
    private function raw(): array
    {
        return $this->cache->get('settings.raw.v2', function (ItemInterface $item): array {
            $item->expiresAfter(3600);
            $item->tag(['settings']);
            $rows = [];
            foreach ($this->settings->runtimeValues() as $setting) {
                $rows[$setting['key']] = [
                    'value' => $setting['value']['value'] ?? null,
                    'sensitive' => $setting['isSensitive'],
                ];
            }

            return $rows;
        });
    }
}

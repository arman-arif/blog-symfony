<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\IdentifiableTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Repository\SettingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SettingRepository::class)]
#[ORM\Table(name: 'app_setting')]
class Setting
{
    use IdentifiableTrait;
    use TimestampableTrait;

    #[ORM\Column(name: 'setting_key', type: Types::STRING, length: 150, unique: true)]
    private string $key;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $value = [];

    #[ORM\Column(name: 'setting_group', type: Types::STRING, length: 60)]
    private string $groupName = 'general';

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isSensitive = false;

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): self
    {
        $this->key = $key;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getValue(): array
    {
        return $this->value;
    }

    /** @param array<string, mixed> $value */
    public function setValue(array $value): self
    {
        $this->value = $value;

        return $this;
    }

    public function getGroupName(): string
    {
        return $this->groupName;
    }

    public function setGroupName(string $groupName): self
    {
        $this->groupName = $groupName;

        return $this;
    }

    public function isSensitive(): bool
    {
        return $this->isSensitive;
    }

    public function setIsSensitive(bool $isSensitive): self
    {
        $this->isSensitive = $isSensitive;

        return $this;
    }
}

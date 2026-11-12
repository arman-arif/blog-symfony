<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\IdentifiableTrait;
use App\Entity\Traits\SoftDeleteableTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
#[Gedmo\SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: false)]
class User implements UserInterface, PasswordAuthenticatedUserInterface, TwoFactorInterface
{
    use IdentifiableTrait;
    use SoftDeleteableTrait;
    use TimestampableTrait;

    #[ORM\Column(type: Types::STRING, length: 180, unique: true)]
    private string $email;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $password = '';

    #[ORM\Column(type: Types::STRING, length: 150)]
    private string $displayName;

    #[Gedmo\Slug(fields: ['displayName'])]
    #[ORM\Column(type: Types::STRING, length: 180, unique: true)]
    private string $slug;

    #[ORM\Column(type: Types::STRING, length: 150, nullable: true)]
    private ?string $jobTitle = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $bio = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isActive = true;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isVerified = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $totpSecret = null;

    #[ORM\ManyToOne(targetEntity: Media::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Media $avatar = null;

    /** @var Collection<int, Role> */
    #[ORM\ManyToMany(targetEntity: Role::class, inversedBy: 'users')]
    #[ORM\JoinTable(name: 'user_role')]
    private Collection $assignedRoles;

    public function __construct()
    {
        $this->assignedRoles = new ArrayCollection();
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): self
    {
        $this->displayName = $displayName;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getJobTitle(): ?string
    {
        return $this->jobTitle;
    }

    public function setJobTitle(?string $jobTitle): self
    {
        $this->jobTitle = $jobTitle;

        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): self
    {
        $this->bio = $bio;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): self
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeImmutable $lastLoginAt): self
    {
        $this->lastLoginAt = $lastLoginAt;

        return $this;
    }

    public function getTotpSecret(): ?string
    {
        return $this->totpSecret;
    }

    public function setTotpSecret(?string $totpSecret): self
    {
        $this->totpSecret = $totpSecret;

        return $this;
    }

    public function isTotpAuthenticationEnabled(): bool
    {
        return null !== $this->totpSecret;
    }

    public function getTotpAuthenticationUsername(): ?string
    {
        return $this->email;
    }

    public function getTotpAuthenticationConfiguration(): ?TotpConfigurationInterface
    {
        if (null === $this->totpSecret) {
            return null;
        }

        return new TotpConfiguration($this->totpSecret, TotpConfiguration::ALGORITHM_SHA1, 30, 6);
    }

    public function getAvatar(): ?Media
    {
        return $this->avatar;
    }

    public function setAvatar(?Media $avatar): self
    {
        $this->avatar = $avatar;

        return $this;
    }

    /** @return Collection<int, Role> */
    public function getAssignedRoles(): Collection
    {
        return $this->assignedRoles;
    }

    public function addAssignedRole(Role $role): self
    {
        if (!$this->assignedRoles->contains($role)) {
            $this->assignedRoles->add($role);
        }

        return $this;
    }

    public function removeAssignedRole(Role $role): self
    {
        $this->assignedRoles->removeElement($role);

        return $this;
    }

    public function hasRoleCode(string $code): bool
    {
        foreach ($this->assignedRoles as $role) {
            if ($role->getCode() === $code) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        $roles = ['ROLE_USER'];
        foreach ($this->assignedRoles as $role) {
            $roles[] = $role->getCode();
        }

        return array_values(array_unique($roles));
    }

    public function eraseCredentials(): void
    {
    }

    public function __toString(): string
    {
        return $this->displayName;
    }
}

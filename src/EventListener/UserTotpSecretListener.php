<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Security\TotpSecretCipher;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;

#[AsDoctrineListener(event: 'postLoad')]
#[AsDoctrineListener(event: 'prePersist')]
#[AsDoctrineListener(event: 'onFlush')]
#[AsDoctrineListener(event: 'postFlush')]
final readonly class UserTotpSecretListener
{
    public function __construct(private TotpSecretCipher $cipher)
    {
    }

    public function postLoad(PostLoadEventArgs $args): void
    {
        $user = $args->getObject();
        if (!$user instanceof User || null === $user->getTotpSecret()) {
            return;
        }
        $user->setTotpSecret($this->cipher->decrypt($user->getTotpSecret()));
        $args->getObjectManager()->getUnitOfWork()->setOriginalEntityProperty(spl_object_id($user), 'totpSecret', $user->getTotpSecret());
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $this->encrypt($args->getObject());
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $entityManager = $args->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();
        $metadata = $entityManager->getClassMetadata(User::class);
        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof User) {
                continue;
            }
            $current = $entity->getTotpSecret();
            if (null === $current || $this->cipher->isEncrypted($current)) {
                continue;
            }
            $original = $unitOfWork->getOriginalEntityData($entity)['totpSecret'] ?? null;
            if (is_string($original) && $this->cipher->isEncrypted($original) && $this->cipher->decrypt($original) === $current) {
                $entity->setTotpSecret($original);
            } else {
                $entity->setTotpSecret($this->cipher->encrypt($current));
            }
            $unitOfWork->recomputeSingleEntityChangeSet($metadata, $entity);
        }
    }

    private function encrypt(object $entity): void
    {
        if (!$entity instanceof User || null === $entity->getTotpSecret() || $this->cipher->isEncrypted($entity->getTotpSecret())) {
            return;
        }
        $entity->setTotpSecret($this->cipher->encrypt($entity->getTotpSecret()));
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        $unitOfWork = $args->getObjectManager()->getUnitOfWork();
        foreach ($unitOfWork->getIdentityMap()[User::class] ?? [] as $user) {
            if (null !== $user->getTotpSecret() && $this->cipher->isEncrypted($user->getTotpSecret())) {
                $user->setTotpSecret($this->cipher->decrypt($user->getTotpSecret()));
                // Track the in-memory plaintext, not the storage representation, for dirty checking.
                $unitOfWork->setOriginalEntityProperty(spl_object_id($user), 'totpSecret', $user->getTotpSecret());
            }
        }
    }
}

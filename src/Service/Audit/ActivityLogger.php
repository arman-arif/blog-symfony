<?php

declare(strict_types=1);

namespace App\Service\Audit;

use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class ActivityLogger
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private RequestStack $requestStack,
        #[Autowire(service: 'monolog.logger.audit')]
        private LoggerInterface $auditLogger,
    ) {
    }

    /** @param array<string, mixed> $context */
    public function log(string $action, ?string $subjectType = null, ?int $subjectId = null, array $context = []): void
    {
        $user = $this->security->getUser();
        $entry = (new ActivityLog())
            ->setAction($action)
            ->setSubjectType($subjectType)
            ->setSubjectId($subjectId)
            ->setContext($context)
            ->setIp($this->requestStack->getCurrentRequest()?->getClientIp());

        if ($user instanceof User) {
            $entry->setUser($user);
        }

        $this->entityManager->persist($entry);
        $this->entityManager->flush();
        $this->auditLogger->info($action, ['subject_type' => $subjectType, 'subject_id' => $subjectId, 'context' => $context]);
    }
}

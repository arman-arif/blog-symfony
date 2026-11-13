<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Article;
use App\Entity\Media;
use App\Entity\User;
use App\Enum\ArticleStatus;
use App\Form\ArticleFormType;
use App\Repository\ArticleRepository;
use App\Repository\ArticleRevisionRepository;
use App\Repository\CategoryRepository;
use App\Repository\MediaRepository;
use App\Repository\UserRepository;
use App\Security\ArticleVoter;
use App\Service\Article\ArticleService;
use App\Service\Audit\ActivityLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/articles', name: 'admin_article_')]
#[IsGranted('article.create')]
final class ArticleController extends AbstractController
{
    public function __construct(
        private readonly ArticleRepository $articles,
        private readonly ArticleRevisionRepository $revisions,
        private readonly ArticleService $articleService,
        private readonly ActivityLogger $activityLogger,
        private readonly MediaRepository $media,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, CategoryRepository $categories, UserRepository $users): Response
    {
        $filters = [
            'q' => trim($request->query->getString('q')),
            'status' => $request->query->getString('status'),
            'author' => $request->query->getInt('author') ?: null,
            'category' => $request->query->getInt('category') ?: null,
        ];
        $trash = 'trash' === $filters['status'];
        if ($trash) {
            $filters['status'] = '';
        }

        $activeStatus = $trash ? 'trash' : $filters['status'];
        $page = max(1, $request->query->getInt('page', 1));
        $authorScope = $this->isGranted('article.edit.any') ? null : $this->currentUser()->getId();
        $result = $this->articles->findForAdmin(array_filter($filters, static fn (mixed $value): bool => null !== $value && '' !== $value), $page, 10, $trash, $authorScope);

        return $this->render('admin/article/index.html.twig', [
            'articles' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'filters' => $filters,
            'activeStatus' => $activeStatus,
            'trash' => $trash,
            'categories' => $categories->findTree(),
            'authors' => null === $authorScope ? $users->findBy([], ['displayName' => 'ASC']) : [$this->currentUser()],
            'statuses' => ArticleStatus::cases(),
        ]);
    }

    #[Route('/new', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request, UserRepository $users): Response
    {
        $article = (new Article())->setAuthor($this->currentUser());
        $form = $this->createForm(ArticleFormType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->assertAuthorAssignable($article);
                $target = $article->getStatus();
                $this->assertTransitionAllowed($target);
                $this->assertScheduleComplete($article, $target);
                $article->setStatus(ArticleStatus::Draft);
                $this->articleService->create($article, $this->currentUser(), $target, $article->getAuthor());
                $this->activityLogger->log('article.created', 'Article', $article->getId(), ['summary' => 'Created “'.$article->getTitle().'”']);
                $this->addFlash('success', 'Article created. You can keep editing it below.');

                return $this->redirectToRoute('admin_article_edit', ['id' => $article->getId()]);
            } catch (\DomainException|AccessDeniedException $exception) {
                $form->addError(new \Symfony\Component\Form\FormError($exception->getMessage()));
            }
        }

        return $this->render('admin/article/manage.html.twig', [
            'form' => $form,
            'article' => $article,
            'authors' => $users->findBy([], ['displayName' => 'ASC']),
            'media' => $this->mediaChoices(),
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, UserRepository $users): Response
    {
        $article = $this->findArticle($id);
        $this->denyAccessUnlessGranted(ArticleVoter::EDIT, $article);
        $previousSlug = $article->getSlug();
        $details = clone $article;
        $form = $this->createForm(ArticleFormType::class, $details);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->assertAuthorAssignable($details);
                $target = $details->getStatus();
                $this->assertScheduleComplete($details, $target);
                $this->articleService->update($article, $this->currentUser(), $target, true, $previousSlug, $details);
                $this->activityLogger->log('article.updated', 'Article', $article->getId(), ['summary' => 'Updated “'.$article->getTitle().'”']);
                $this->addFlash('success', 'Article saved.');

                return $this->redirectToRoute('admin_article_edit', ['id' => $article->getId()]);
            } catch (\DomainException|AccessDeniedException $exception) {
                $form->addError(new \Symfony\Component\Form\FormError($exception->getMessage()));
            }
        }

        return $this->render('admin/article/manage.html.twig', [
            'form' => $form,
            'article' => $details,
            'authors' => $users->findBy([], ['displayName' => 'ASC']),
            'media' => $this->mediaChoices(),
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/{id}/preview', name: 'preview', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function preview(int $id): Response
    {
        $article = $this->findArticle($id);
        $this->denyAccessUnlessGranted(ArticleVoter::EDIT, $article);

        return $this->render('admin/article/preview.html.twig', ['article' => $article]);
    }

    #[Route('/{id}/revisions', name: 'revisions', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function revisions(int $id): Response
    {
        $article = $this->findArticle($id);
        $this->denyAccessUnlessGranted(ArticleVoter::EDIT, $article);

        return $this->render('admin/article/revisions.html.twig', [
            'article' => $article,
            'revisions' => $this->revisions->findForArticle($article),
        ]);
    }

    #[Route('/{id}/revisions/{revision}/compare', name: 'compare', requirements: ['id' => '\d+', 'revision' => '\d+'], methods: ['GET'])]
    public function compare(int $id, int $revision): Response
    {
        $article = $this->findArticle($id);
        $this->denyAccessUnlessGranted(ArticleVoter::EDIT, $article);
        $snapshot = $this->revisions->findOneForArticle($article, $revision) ?? throw $this->createNotFoundException();

        return $this->render('admin/article/compare.html.twig', ['article' => $article, 'revision' => $snapshot]);
    }

    #[Route('/{id}/revisions/{revision}/restore', name: 'restore_revision', requirements: ['id' => '\d+', 'revision' => '\d+'], methods: ['POST'])]
    public function restoreRevision(int $id, int $revision, Request $request): Response
    {
        $article = $this->findArticle($id);
        $this->denyAccessUnlessGranted(ArticleVoter::EDIT, $article);
        $this->assertCsrf('restore_revision', $request);
        $snapshot = $this->revisions->findOneForArticle($article, $revision) ?? throw $this->createNotFoundException();
        $this->articleService->restoreRevision($article, $snapshot, $this->currentUser());
        $this->activityLogger->log('article.revision_restored', 'Article', $article->getId(), ['summary' => 'Restored revision #'.$revision]);
        $this->addFlash('success', 'Revision restored.');

        return $this->redirectToRoute('admin_article_edit', ['id' => $article->getId()]);
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        $article = $this->findArticle($id);
        $this->denyAccessUnlessGranted(ArticleVoter::DELETE, $article);
        $this->assertCsrf('article_delete', $request);
        $title = $article->getTitle();
        $this->articleService->delete($article);
        $this->activityLogger->log('article.deleted', 'Article', $id, ['summary' => 'Moved “'.$title.'” to trash']);
        $this->addFlash('success', 'Article moved to trash.');

        return $this->redirectToRoute('admin_article_index');
    }

    #[Route('/{id}/restore', name: 'restore', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function restore(int $id, Request $request): Response
    {
        $article = $this->articles->findIncludingDeleted($id) ?? throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted(ArticleVoter::DELETE, $article);
        $this->assertCsrf('article_restore', $request);
        $this->articleService->restore($article);
        $this->activityLogger->log('article.restored', 'Article', $id, ['summary' => 'Restored “'.$article->getTitle().'”']);
        $this->addFlash('success', 'Article restored.');

        return $this->redirectToRoute('admin_article_index', ['status' => 'trash']);
    }

    #[Route('/{id}/duplicate', name: 'duplicate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function duplicate(int $id, Request $request): Response
    {
        $article = $this->findArticle($id);
        $this->denyAccessUnlessGranted(ArticleVoter::EDIT, $article);
        $this->assertCsrf('article_duplicate', $request);
        $copy = $this->articleService->duplicate($article, $this->currentUser());
        $this->activityLogger->log('article.duplicated', 'Article', $copy->getId(), ['summary' => 'Duplicated “'.$article->getTitle().'”']);
        $this->addFlash('success', 'Draft copy created.');

        return $this->redirectToRoute('admin_article_edit', ['id' => $copy->getId()]);
    }

    #[Route('/{id}/autosave', name: 'autosave', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function autosave(
        int $id,
        Request $request,
        #[Autowire(service: 'limiter.article_autosave')] RateLimiterFactory $limiter,
    ): JsonResponse {
        $article = $this->findArticle($id);
        $this->denyAccessUnlessGranted(ArticleVoter::EDIT, $article);

        $token = $request->headers->get('X-CSRF-Token') ?? $request->request->getString('_token');
        if (!$this->isCsrfTokenValid('article_autosave', $token)) {
            return new JsonResponse(['error' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN);
        }

        if (!$limiter->create($request->getClientIp() ?? 'anonymous')->consume()->isAccepted()) {
            return new JsonResponse(['error' => 'Too many autosaves. Try again shortly.'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload) || !is_string($payload['title'] ?? null) || !is_string($payload['content'] ?? null)) {
            return new JsonResponse(['error' => 'A title and content are required.'], Response::HTTP_BAD_REQUEST);
        }

        $revision = $this->articleService->autosave($article, $payload['title'], $payload['content'], $this->currentUser());

        return new JsonResponse([
            'status' => 'saved',
            'revision' => $revision->getId(),
            'savedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function mediaChoices(): array
    {
        return array_map(static fn (Media $media): array => [
            'id' => $media->getId(), 'url' => $media->getStorageKey(), 'alt' => $media->getAlt(),
            'caption' => $media->getCaption(), 'originalName' => $media->getOriginalName(),
            'width' => $media->getWidth() ?? 1200, 'height' => $media->getHeight() ?? 800,
        ], $this->media->findBy([], ['createdAt' => 'DESC']));
    }

    private function findArticle(int $id): Article
    {
        return $this->articles->find($id) ?? throw $this->createNotFoundException();
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException();
        }

        return $user;
    }

    private function assertAuthorAssignable(Article $article): void
    {
        if ($article->getAuthor()->getId() !== $this->currentUser()->getId() && !$this->isGranted('article.edit.any')) {
            throw new AccessDeniedException('You cannot assign this article to another author.');
        }
    }

    private function assertTransitionAllowed(ArticleStatus $target): void
    {
        if (ArticleStatus::Scheduled === $target && !$this->isGranted('article.schedule')) {
            throw new AccessDeniedException('You cannot schedule articles.');
        }
        if (ArticleStatus::Published === $target && !$this->isGranted('article.publish')) {
            throw new AccessDeniedException('You cannot publish articles.');
        }
        if (ArticleStatus::PendingReview === $target && !$this->isGranted('article.submit')) {
            throw new AccessDeniedException('You cannot submit articles for review.');
        }
    }

    private function assertScheduleComplete(Article $article, ArticleStatus $target): void
    {
        if (ArticleStatus::Scheduled === $target && null === $article->getScheduledAt()) {
            throw new \DomainException('Choose a date and time before scheduling this article.');
        }
    }

    private function assertCsrf(string $tokenId, Request $request): void
    {
        if (!$this->isCsrfTokenValid($tokenId, (string) $request->request->get('_token'))) {
            throw new AccessDeniedException('Invalid CSRF token.');
        }
    }
}

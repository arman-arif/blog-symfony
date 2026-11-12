<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\User;
use App\Enum\CommentStatus;
use App\Form\CommentFormType;
use App\Repository\CommentRepository;
use App\Service\Article\PublicCatalog;
use App\Service\Comment\CommentService;
use App\Service\Comment\CommentSubmission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

final class CommentController extends AbstractController
{
    public function __construct(private readonly CommentRepository $comments)
    {
    }

    #[Route('/blog/{slug}/comments', name: 'front_comment_create', requirements: ['slug' => '[a-z0-9-]+'], methods: ['POST'])]
    public function create(
        string $slug,
        Request $request,
        PublicCatalog $catalog,
        CommentService $commentService,
        #[Autowire(service: 'limiter.comment_submit')] RateLimiterFactory $limiter,
    ): Response {
        $article = $catalog->article($slug) ?? throw $this->createNotFoundException();
        $submission = new CommentSubmission();
        $form = $this->createForm(CommentFormType::class, $submission);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('danger', 'Please check the comment form and try again.');

            return $this->redirectToRoute('front_article', ['slug' => $slug, '_fragment' => 'comments']);
        }

        $user = $this->getUser();
        $user = $user instanceof User ? $user : null;
        if (null === $user && ('' === trim((string) $submission->guestName) || '' === trim((string) $submission->guestEmail))) {
            $this->addFlash('danger', 'A name and email address are required for guest comments.');

            return $this->redirectToRoute('front_article', ['slug' => $slug, '_fragment' => 'comments']);
        }

        if (!$limiter->create($request->getClientIp() ?? 'anonymous')->consume()->isAccepted()) {
            $this->addFlash('danger', 'Too many comments from this connection. Please try again later.');

            return $this->redirectToRoute('front_article', ['slug' => $slug, '_fragment' => 'comments']);
        }

        try {
            $comment = $commentService->submit($article, $submission, $user, $request->getClientIp() ?? '', $request->headers->get('User-Agent'));
        } catch (\DomainException $exception) {
            $this->addFlash('danger', $exception->getMessage());

            return $this->redirectToRoute('front_article', ['slug' => $slug, '_fragment' => 'comments']);
        }

        $this->addFlash('success', 'Your comment has been submitted'.(CommentStatus::Pending === $comment->getStatus() ? ' and awaits moderation.' : '.'));

        return $this->redirectToRoute('front_article', ['slug' => $slug, '_fragment' => 'comments']);
    }

    #[Route('/blog/{slug}/comments/{id}/report', name: 'front_comment_report', requirements: ['slug' => '[a-z0-9-]+', 'id' => '\d+'], methods: ['POST'])]
    public function report(
        string $slug,
        int $id,
        Request $request,
        PublicCatalog $catalog,
        CommentService $commentService,
        #[Autowire(service: 'limiter.comment_report')] RateLimiterFactory $limiter,
    ): Response {
        $article = $catalog->article($slug) ?? throw $this->createNotFoundException();
        $comment = $this->comments->find($id);
        if (null === $comment || $comment->getArticle()->getId() !== $article->getId() || CommentStatus::Approved !== $comment->getStatus()) {
            throw $this->createNotFoundException();
        }

        if ($this->isCsrfTokenValid('report_comment', (string) $request->request->get('_token'))) {
            if (!$limiter->create($request->getClientIp() ?? 'anonymous')->consume()->isAccepted()) {
                $this->addFlash('warning', 'You have already reported several comments. Please try again later.');
            } else {
                $commentService->report($comment);
                $this->addFlash('success', 'Thanks — this comment has been flagged for review.');
            }
        }

        return $this->redirectToRoute('front_article', ['slug' => $slug, '_fragment' => 'comments']);
    }
}

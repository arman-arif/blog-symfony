<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Comment;
use App\Entity\User;
use App\Enum\CommentStatus;
use App\Form\AdminCommentType;
use App\Repository\CommentRepository;
use App\Security\CommentVoter;
use App\Service\Audit\ActivityLogger;
use App\Service\Comment\CommentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/comments', name: 'admin_comment_')]
#[IsGranted('comment.moderate')]
final class CommentController extends AbstractController
{
    public function __construct(
        private readonly CommentRepository $comments,
        private readonly CommentService $commentService,
        private readonly ActivityLogger $activityLogger,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $filters = ['status' => $request->query->getString('status'), 'q' => trim($request->query->getString('q'))];
        $restrict = $this->isGranted('article.edit.any') ? null : $this->currentUser()->getId();
        $result = $this->comments->findForAdmin($filters, max(1, $request->query->getInt('page', 1)), 20, $restrict);

        return $this->render('admin/comment/index.html.twig', [
            'comments' => $result['items'], 'total' => $result['total'], 'pages' => $result['pages'],
            'page' => max(1, $request->query->getInt('page', 1)), 'filters' => $filters,
            'statuses' => CommentStatus::cases(),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $comment = $this->findComment($id);
        $this->denyAccessUnlessGranted(CommentVoter::MODERATE, $comment);
        $form = $this->createForm(AdminCommentType::class, $comment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->activityLogger->log('comment.updated', 'Comment', $comment->getId(), ['summary' => 'Edited a comment']);
            $this->addFlash('success', 'Comment saved.');

            return $this->redirectToRoute('admin_comment_index');
        }

        return $this->render('admin/comment/edit.html.twig', ['comment' => $comment, 'form' => $form], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/{id}/{status}', name: 'moderate', requirements: ['id' => '\d+', 'status' => 'approved|rejected|spam|pending'], methods: ['POST'])]
    public function moderate(int $id, string $status, Request $request): Response
    {
        $comment = $this->findComment($id);
        $this->denyAccessUnlessGranted(CommentVoter::MODERATE, $comment);
        if (!$this->isCsrfTokenValid('comment_moderate', (string) $request->request->get('_token'))) {
            throw new AccessDeniedException('Invalid CSRF token.');
        }

        $target = CommentStatus::from($status);
        $this->commentService->moderate($comment, $target);
        $this->activityLogger->log('comment.'.$target->value, 'Comment', $comment->getId(), ['summary' => 'Marked a comment as '.$target->value]);
        $this->addFlash('success', 'Comment marked as '.$target->value.'.');

        return $this->redirectToRoute('admin_comment_index');
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        $comment = $this->findComment($id);
        $this->denyAccessUnlessGranted(CommentVoter::MODERATE, $comment);
        if (!$this->isCsrfTokenValid('comment_delete', (string) $request->request->get('_token'))) {
            throw new AccessDeniedException('Invalid CSRF token.');
        }
        $this->commentService->delete($comment);
        $this->activityLogger->log('comment.deleted', 'Comment', $id, ['summary' => 'Deleted a comment']);
        $this->addFlash('success', 'Comment moved to trash.');

        return $this->redirectToRoute('admin_comment_index');
    }

    private function findComment(int $id): Comment
    {
        return $this->comments->find($id) ?? throw $this->createNotFoundException();
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException();
        }

        return $user;
    }
}

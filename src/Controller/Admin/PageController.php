<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Page;
use App\Entity\User;
use App\Form\PageFormType;
use App\Repository\PageRepository;
use App\Service\Audit\ActivityLogger;
use App\Service\Page\PageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/pages', name: 'admin_page_')]
#[IsGranted('page.manage')]
final class PageController extends AbstractController
{
    public function __construct(
        private readonly PageRepository $pages,
        private readonly PageService $pageService,
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->render('admin/page/index.html.twig', [
            'pages' => $this->pages->findBy([], ['updatedAt' => 'DESC']),
            'status' => $request->query->getString('status'),
        ]);
    }

    #[Route('/new', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        return $this->form($request, new Page());
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        return $this->form($request, $this->pages->find($id) ?? throw $this->createNotFoundException());
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        $page = $this->pages->find($id) ?? throw $this->createNotFoundException();
        if (!$this->isCsrfTokenValid('page_delete', (string) $request->request->get('_token'))) {
            throw new AccessDeniedException('Invalid CSRF token.');
        }
        $this->pageService->delete($page);
        $this->activityLogger->log('page.deleted', 'Page', $id, ['summary' => 'Deleted page “'.$page->getTitle().'”']);
        $this->addFlash('success', 'Page deleted.');

        return $this->redirectToRoute('admin_page_index');
    }

    private function form(Request $request, Page $page): Response
    {
        $details = clone $page;
        $form = $this->createForm(PageFormType::class, $details);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            if (!$user instanceof User) {
                throw new AccessDeniedException();
            }
            $this->pageService->save($page, $details, $user);
            $this->activityLogger->log('page.saved', 'Page', $page->getId(), ['summary' => 'Saved page “'.$page->getTitle().'”']);
            $this->addFlash('success', 'Page saved.');

            return $this->redirectToRoute('admin_page_index');
        }

        return $this->render('admin/page/form.html.twig', ['page' => $details, 'form' => $form], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}

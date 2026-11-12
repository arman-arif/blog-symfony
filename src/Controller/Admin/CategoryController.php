<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Form\CategoryFormType;
use App\Repository\CategoryRepository;
use App\Service\Article\TaxonomyService;
use App\Service\Audit\ActivityLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/categories', name: 'admin_category_')]
#[IsGranted('category.manage')]
final class CategoryController extends AbstractController
{
    public function __construct(
        private readonly CategoryRepository $categories,
        private readonly TaxonomyService $taxonomy,
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/category/index.html.twig', [
            'categories' => $this->categories->findTree(),
        ]);
    }

    #[Route('/new', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $category = new Category();
        $form = $this->createForm(CategoryFormType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->taxonomy->saveCategory($category);
            $this->activityLogger->log('category.created', 'Category', $category->getId(), ['summary' => 'Created category “'.$category->getName().'”']);
            $this->addFlash('success', 'Category created.');

            return $this->redirectToRoute('admin_category_index');
        }

        return $this->render('admin/category/manage.html.twig', ['form' => $form, 'category' => $category], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $category = $this->categories->find($id) ?? throw $this->createNotFoundException();
        $form = $this->createForm(CategoryFormType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($category->getParent() === $category) {
                $form->addError(new \Symfony\Component\Form\FormError('A category cannot be its own parent.'));
            } else {
                $this->taxonomy->saveCategory($category);
                $this->activityLogger->log('category.updated', 'Category', $category->getId(), ['summary' => 'Updated category “'.$category->getName().'”']);
                $this->addFlash('success', 'Category saved.');

                return $this->redirectToRoute('admin_category_index');
            }
        }

        return $this->render('admin/category/manage.html.twig', ['form' => $form, 'category' => $category], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        $category = $this->categories->find($id) ?? throw $this->createNotFoundException();
        if (!$this->isCsrfTokenValid('category_delete', (string) $request->request->get('_token'))) {
            throw new AccessDeniedException('Invalid CSRF token.');
        }

        try {
            $this->taxonomy->deleteCategory($category);
            $this->activityLogger->log('category.deleted', 'Category', $id, ['summary' => 'Deleted category “'.$category->getName().'”']);
            $this->addFlash('success', 'Category deleted.');
        } catch (\DomainException $exception) {
            $this->addFlash('danger', $exception->getMessage());
        }

        return $this->redirectToRoute('admin_category_index');
    }
}

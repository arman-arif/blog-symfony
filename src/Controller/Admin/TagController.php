<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Tag;
use App\Form\TagFormType;
use App\Repository\TagRepository;
use App\Service\Article\TaxonomyService;
use App\Service\Audit\ActivityLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/tags', name: 'admin_tag_')]
#[IsGranted('tag.manage')]
final class TagController extends AbstractController
{
    public function __construct(
        private readonly TagRepository $tags,
        private readonly TaxonomyService $taxonomy,
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $query = trim($request->query->getString('q'));

        return $this->render('admin/tag/index.html.twig', [
            'tags' => '' === $query ? $this->tags->findBy([], ['name' => 'ASC']) : $this->tags->search($query, 100),
            'query' => $query,
        ]);
    }

    #[Route('/autocomplete', name: 'autocomplete', methods: ['GET'])]
    public function autocomplete(Request $request): JsonResponse
    {
        $query = trim($request->query->getString('q'));
        $tags = '' === $query ? $this->tags->findBy([], ['name' => 'ASC'], 20) : $this->tags->search($query);

        return new JsonResponse(array_map(static fn (Tag $tag): array => ['id' => $tag->getId(), 'name' => $tag->getName()], $tags));
    }

    #[Route('/quick-create', name: 'quick_create', methods: ['POST'])]
    public function quickCreate(Request $request): JsonResponse
    {
        $token = $request->headers->get('X-CSRF-Token') ?? $request->request->getString('_token');
        if (!$this->isCsrfTokenValid('tag_quick_create', $token)) {
            return new JsonResponse(['error' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN);
        }

        $payload = json_decode($request->getContent(), true);
        $name = is_array($payload) && is_string($payload['name'] ?? null) ? trim($payload['name']) : '';
        if ('' === $name || mb_strlen($name) > 100) {
            return new JsonResponse(['error' => 'A tag name of up to 100 characters is required.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $tag = $this->taxonomy->findOrCreateTag($name);
        } catch (\DomainException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(['id' => $tag->getId(), 'name' => $tag->getName(), 'slug' => $tag->getSlug()], Response::HTTP_CREATED);
    }

    #[Route('/new', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $tag = new Tag();
        $form = $this->createForm(TagFormType::class, $tag);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->taxonomy->saveTag($tag);
            $this->activityLogger->log('tag.created', 'Tag', $tag->getId(), ['summary' => 'Created tag “'.$tag->getName().'”']);
            $this->addFlash('success', 'Tag created.');

            return $this->redirectToRoute('admin_tag_index');
        }

        return $this->render('admin/tag/manage.html.twig', ['form' => $form, 'tag' => $tag], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $tag = $this->tags->find($id) ?? throw $this->createNotFoundException();
        $form = $this->createForm(TagFormType::class, $tag);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->taxonomy->saveTag($tag);
            $this->activityLogger->log('tag.updated', 'Tag', $tag->getId(), ['summary' => 'Updated tag “'.$tag->getName().'”']);
            $this->addFlash('success', 'Tag saved.');

            return $this->redirectToRoute('admin_tag_index');
        }

        return $this->render('admin/tag/manage.html.twig', ['form' => $form, 'tag' => $tag], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        $tag = $this->tags->find($id) ?? throw $this->createNotFoundException();
        if (!$this->isCsrfTokenValid('tag_delete', (string) $request->request->get('_token'))) {
            throw new AccessDeniedException('Invalid CSRF token.');
        }

        $name = $tag->getName();
        $this->taxonomy->deleteTag($tag);
        $this->activityLogger->log('tag.deleted', 'Tag', $id, ['summary' => 'Deleted tag “'.$name.'”']);
        $this->addFlash('success', 'Tag deleted.');

        return $this->redirectToRoute('admin_tag_index');
    }
}

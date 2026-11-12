<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Mock\MockDataProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/articles', name: 'admin_article_')]
#[IsGranted('article.create')]
final class ArticleController extends AbstractController
{
    public function __construct(private readonly MockDataProvider $data)
    {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->render('admin/article/index.html.twig', [
            'currentUser' => $this->data->users()[0], 'articles' => array_merge($this->data->articles(), $this->data->trashedArticles()),
            'categories' => $this->data->categories(), 'authors' => $this->data->authors(),
            'state' => $request->query->getString('state'),
        ]);
    }

    #[Route('/new', name: 'create', methods: ['GET'])]
    public function create(Request $request): Response
    {
        return $this->render('admin/article/form.html.twig', $this->formData($this->data->newArticle(), $request->query->getString('state')));
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function edit(int $id, Request $request): Response
    {
        return $this->render('admin/article/form.html.twig', $this->formData($this->findArticle($id), $request->query->getString('state')));
    }

    #[Route('/{id}/preview', name: 'preview', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function preview(int $id, Request $request): Response
    {
        return $this->render('admin/article/preview.html.twig', [
            'currentUser' => $this->data->users()[0], 'article' => $this->findArticle($id), 'state' => $request->query->getString('state'),
        ]);
    }

    #[Route('/{id}/revisions', name: 'revisions', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function revisions(int $id, Request $request): Response
    {
        return $this->render('admin/article/revisions.html.twig', [
            'currentUser' => $this->data->users()[0], 'article' => $this->findArticle($id), 'revisions' => $this->data->revisions($id), 'state' => $request->query->getString('state'),
        ]);
    }

    #[Route('/{id}/revisions/{revision}/compare', name: 'compare', requirements: ['id' => '\d+', 'revision' => '\d+'], methods: ['GET'])]
    public function compare(int $id, int $revision, Request $request): Response
    {
        $article = $this->findArticle($id);
        foreach ($this->data->revisions($id) as $snapshot) {
            if ($snapshot['id'] === $revision) {
                return $this->render('admin/article/compare.html.twig', [
                    'currentUser' => $this->data->users()[0], 'article' => $article, 'revision' => $snapshot, 'state' => $request->query->getString('state'),
                ]);
            }
        }

        throw $this->createNotFoundException();
    }

    /** @return array<string, mixed> */
    private function findArticle(int $id): array
    {
        return $this->data->adminArticle($id) ?? throw $this->createNotFoundException();
    }

    /** @param array<string, mixed> $article
     * @return array<string, mixed>
     */
    private function formData(array $article, string $state): array
    {
        return [
            'currentUser' => $this->data->users()[0], 'article' => $article, 'state' => $state,
            'authors' => $this->data->authors(), 'categories' => $this->data->categories(),
            'tags' => $this->data->tags(), 'media' => $this->data->media(),
        ];
    }
}

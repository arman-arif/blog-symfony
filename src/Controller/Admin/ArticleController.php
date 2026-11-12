<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Mock\AdminPreview;
use App\Mock\MockDataProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/articles', name: 'admin_article_')]
final class ArticleController extends AbstractController
{
    public function __construct(private readonly AdminPreview $preview, private readonly MockDataProvider $data)
    {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->preview->requireEnabled();

        return $this->render('admin/article/index.html.twig', [
            'currentUser' => $this->data->users()[0], 'articles' => $this->data->articles(),
            'categories' => $this->data->categories(), 'authors' => $this->data->authors(),
            'state' => $request->query->getString('state'),
        ]);
    }
}

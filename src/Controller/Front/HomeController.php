<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Mock\MockCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'front_home', methods: ['GET'])]
    public function index(MockCatalog $catalog): Response
    {
        return $this->render('front/home/index.html.twig', array_merge($catalog->layout(), [
            'articles' => $catalog->articles(), 'popular' => $catalog->articles(['sort' => 'popular']),
        ]));
    }
}

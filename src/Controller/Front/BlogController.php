<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\User;
use App\Form\CommentFormType;
use App\Message\RecordArticleViewMessage;
use App\Service\Analytics\VisitorHash;
use App\Service\Article\EngagementService;
use App\Service\Article\PublicCatalog;
use App\Service\Comment\CommentService;
use App\Service\Comment\CommentSubmission;
use App\Service\Settings\SettingsServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class BlogController extends AbstractController
{
    public function __construct(
        private readonly CommentService $comments,
        private readonly EngagementService $engagement,
        private readonly SettingsServiceInterface $settings,
        private readonly MessageBusInterface $bus,
        private readonly VisitorHash $visitorHash,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route('/blog', name: 'front_blog', methods: ['GET'])]
    #[Route('/search', name: 'front_search', methods: ['GET'])]
    public function index(Request $request, PublicCatalog $catalog): Response
    {
        $filters = [];
        foreach (['q', 'category', 'tag', 'author', 'sort'] as $key) {
            $filters[$key] = $request->query->getString($key);
        }
        $result = $catalog->articles($filters, $request->query->getInt('page', 1), 9);
        $isSearch = 'front_search' === $request->attributes->get('_route');

        return $this->render($isSearch ? 'front/search/index.html.twig' : 'front/blog/index.html.twig', array_merge($catalog->layout(), [
            'articles' => $result['items'], 'total' => $result['total'],
            'page' => $result['page'], 'pages' => $result['pages'], 'filters' => $filters,
            'listingRoute' => $isSearch ? 'front_search' : 'front_blog',
        ]));
    }

    #[Route('/blog/{slug}', name: 'front_article', methods: ['GET'])]
    public function show(string $slug, PublicCatalog $catalog, Request $request): Response
    {
        $article = $catalog->article($slug) ?? throw $this->createNotFoundException();
        $user = $this->getUser();
        $user = $user instanceof User ? $user : null;

        $submission = new CommentSubmission();
        $submission->startedAt = time();
        $commentForm = $this->createForm(CommentFormType::class, $submission, [
            'action' => $this->generateUrl('front_comment_create', ['slug' => $slug]),
        ]);

        $this->bus->dispatch(new RecordArticleViewMessage(
            (int) $article->getId(),
            $this->visitorHash->fromRequest($request),
            $this->truncate($request->headers->get('Referer')),
            $this->truncate($request->headers->get('User-Agent')),
        ));

        return $this->render('front/article/index.html.twig', array_merge($catalog->layout(), [
            'article' => $article,
            'threads' => $this->comments->threads($article),
            'commentCount' => $this->comments->approvedCount($article),
            'commentForm' => $commentForm,
            'canComment' => $this->settings->getBool('comments.enabled') && $article->isAllowComments() && (null !== $user || $this->settings->getBool('comments.anonymous')),
            'commentsEnabled' => $this->settings->getBool('comments.enabled'),
            'isLiked' => null !== $user && $this->engagement->isLiked($article, $user),
            'isBookmarked' => null !== $user && $this->engagement->isBookmarked($article, $user),
            'engagementToken' => $this->csrfTokenManager->getToken('engagement')->getValue(),
            'maxCommentDepth' => $this->settings->getInt('comments.max_depth'),
            'related' => $catalog->related($article),
            'previous' => $catalog->previous($article),
            'next' => $catalog->next($article),
        ]));
    }

    private function truncate(?string $value): ?string
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return mb_substr($value, 0, 2000);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\ArticleRevision;
use App\Tests\Factory\ArticleFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\TerminableInterface;

final class ArticleEditorTest extends AdminTestCase
{
    public function testEditorHasCompleteToolbarAndRealFormFields(): void
    {
        $client = self::createClient();
        $article = ArticleFactory::createOne([
            'author' => self::authenticatedUser(),
            'title' => 'An editor test story',
            'content' => '<p>'.str_repeat('Editor content. ', 10).'</p>',
        ]);

        $client->request('GET', '/admin/articles/'.$article->getId().'/edit');
        file_put_contents('/tmp/article-edit.html', (string) $client->getResponse()->getContent());
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-component~="editor"]');
        foreach (['bold', 'italic', 'underline', 'blockquote', 'code-block'] as $format) {
            self::assertSelectorExists('[data-editor-format="'.$format.'"]');
        }
        self::assertSelectorExists('[data-editor-list="bullet"]');
        self::assertSelectorExists('[data-editor-list="ordered"]');
        foreach (['link', 'image', 'table', 'video', 'divider', 'undo', 'redo'] as $action) {
            self::assertSelectorExists('[data-editor-action="'.$action.'"]');
        }
        self::assertSelectorExists('#article-title', 'Body: '.substr((string) $client->getResponse()->getContent(), 0, 200));
        self::assertSelectorExists('#article-slug');
        self::assertSelectorExists('#article-status');
        self::assertSelectorExists('#article-visibility');
        self::assertSelectorExists('#article-scheduled-at');
        self::assertSelectorExists('#article-author');
        self::assertSelectorExists('#article-categories');
        self::assertSelectorExists('#article-tags');
        self::assertSelectorExists('#article-featured-image');
        self::assertSelectorExists('#article-allow-comments');
        self::assertSelectorExists('#article-seo-title[maxlength="70"]');
        self::assertSelectorExists('#article-seo-description[maxlength="160"]');
        self::assertSelectorExists('#article-focus-keyword');
        self::assertSelectorExists('#article-canonical');
        self::assertSelectorExists('#article-noindex');
        self::assertSelectorExists('[data-autosave-url]');
        self::assertSelectorExists('[data-autosave-token]');
        self::assertSelectorExists('textarea[data-editor-output][name="article_form[content]"]');
    }

    public function testValidationRejectsEmptyContent(): void
    {
        $client = self::createClient();
        $article = ArticleFactory::createOne(['author' => self::authenticatedUser()]);

        $crawler = $client->request('GET', '/admin/articles/'.$article->getId().'/edit');
        $form = $crawler->selectButton('Save article')->form();
        $form['article_form[title]'] = '';
        $form['article_form[content]'] = '';
        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('.field-error');
        self::assertSelectorTextContains('main', 'This value should not be blank');
    }

    public function testPreviewRevisionsAndCompareUseRealContent(): void
    {
        $client = self::createClient();
        $article = ArticleFactory::createOne([
            'author' => self::authenticatedUser(),
            'title' => 'A publish-ready story',
            'content' => '<p>'.str_repeat('Published content. ', 10).'</p>',
        ]);
        $revision = (new ArticleRevision())
            ->setArticle($article)
            ->setTitle('Earlier title')
            ->setContent('<p>Earlier content snapshot.</p>')
            ->setCreatedBy(self::authenticatedUser());
        self::getContainer()->get(EntityManagerInterface::class)->persist($revision);
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        $client->request('GET', '/admin/articles/'.$article->getId().'/preview');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'A publish-ready story');

        $client->request('GET', '/admin/articles/'.$article->getId().'/revisions');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Earlier title');

        $client->request('GET', '/admin/articles/'.$article->getId().'/revisions/'.$revision->getId().'/compare');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Earlier content snapshot');
    }

    public function testEditorRoutesRequireAuthenticationInProduction(): void
    {
        $kernel = self::bootKernel(['environment' => 'prod', 'debug' => false]);
        self::assertInstanceOf(TerminableInterface::class, $kernel);
        foreach (['/admin/articles/new', '/admin/articles/31/edit', '/admin/articles/31/preview'] as $path) {
            $request = Request::create($path);
            $response = $kernel->handle($request);
            self::assertSame(302, $response->getStatusCode());
            self::assertStringContainsString('/login', (string) $response->headers->get('Location'));
            $kernel->terminate($request, $response);
        }
    }
}

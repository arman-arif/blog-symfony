<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Advertisement;
use App\Entity\ArticleRevision;
use App\Entity\Media;
use App\Entity\NewsletterCampaign;
use App\Entity\Page;
use App\Entity\Setting;
use App\Repository\MediaRepository;
use App\Service\Media\MediaDeletionService;
use App\Service\Media\MediaStorageInterface;
use App\Service\Media\MediaUploader;
use App\Service\Media\MediaUsageChecker;
use App\Tests\Factory\ArticleFactory;
use App\Tests\Factory\MediaFactory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class MediaSafetyRegressionTest extends AdminTestCase
{
    /** @var list<string> */
    private array $paths = [];
    /** @var list<string> */
    private array $keys = [];

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            @unlink($path);
        }
        foreach ($this->keys as $key) {
            @unlink('public/uploads/media/'.$key);
            foreach (['thumbnail', 'medium', 'large', 'webp'] as $filter) {
                @unlink('public/uploads/media/cache/'.$filter.'/'.$key);
                @unlink('public/uploads/media/cache/'.$filter.'/'.$key.'.webp');
            }
        }
        parent::tearDown();
    }

    /** @return iterable<string, array{string, string}> */
    public static function references(): iterable
    {
        yield 'inline original' => ['article', 'article content'];
        yield 'inline derivative' => ['derivative', 'article content'];
        yield 'page' => ['page', 'page content'];
        yield 'revision' => ['revision', 'article revision'];
        yield 'logo' => ['logo', 'logo/favicon'];
        yield 'favicon' => ['favicon', 'logo/favicon'];
        yield 'newsletter' => ['newsletter', 'newsletter content'];
        yield 'advertisement' => ['advertisement', 'advertisement content'];
        yield 'SEO image' => ['seo', 'article SEO image'];
        yield 'restorable featured image' => ['trash', 'article featured image'];
    }

    #[DataProvider('references')]
    public function testDeletionRejectsInlineSettingsAndRestorableReferences(string $kind, string $label): void
    {
        $client = self::createClient();
        $media = $this->media();
        $key = $media->getStorageKey();
        $id = $media->getId();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $url = '/uploads/media/'.$key;
        if ('article' === $kind || 'derivative' === $kind) {
            ArticleFactory::createOne(['content' => '<img src="'.('derivative' === $kind ? '/media/cache/resolve/medium/'.$key.'?v=1' : $url).'">']);
        } elseif ('page' === $kind) {
            $em->persist((new Page())->setTitle('Referenced page')->setContent('<img src="'.$url.'">'));
        } elseif ('revision' === $kind) {
            $article = ArticleFactory::createOne();
            $em->persist((new ArticleRevision())->setArticle($article)->setTitle('Old inline image')->setContent('<img src="'.$url.'">'));
        } elseif ('logo' === $kind || 'favicon' === $kind) {
            $em->persist((new Setting())->setKey('appearance.'.$kind)->setValue(['value' => 'logo' === $kind ? $key : 'https://cdn.example.test'.$url]));
        } elseif ('newsletter' === $kind) {
            $em->persist((new NewsletterCampaign())->setSubject('Inline newsletter')->setHtml('<img src="'.$url.'">'));
        } elseif ('advertisement' === $kind) {
            $em->persist((new Advertisement())->setName('Inline creative')->setContent('<img src="'.$url.'">'));
        } elseif ('seo' === $kind) {
            ArticleFactory::createOne()->getSeo()->setOgImageUrl($url);
        } else {
            ArticleFactory::createOne(['featuredImage' => $media, 'deletedAt' => new \DateTimeImmutable()]);
        }
        $em->flush();
        $crawler = $client->request('GET', '/admin/media/'.$id.'/edit');
        $token = $crawler->filter('form[action$="/delete"] input[name="_token"]')->attr('value');
        $client->request('POST', '/admin/media/'.$id.'/delete', ['_token' => $token]);
        self::assertResponseRedirects('/admin/media/'.$id.'/edit');
        $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', $label);
        self::assertNotNull(self::getContainer()->get(MediaRepository::class)->find($id));
        self::assertFileExists('public/uploads/media/'.$key);
    }

    public function testSimilarFilenameDoesNotBlockDeletion(): void
    {
        self::createClient();
        $media = $this->media();
        ArticleFactory::createOne(['content' => '<img src="/uploads/media/'.$media->getStorageKey().'.extra"><img src="/uploads/media/other/'.basename($media->getStorageKey()).'">']);
        self::assertTrue(self::getContainer()->get(MediaDeletionService::class)->delete($media));
        self::assertFileDoesNotExist('public/uploads/media/'.$media->getStorageKey());
    }

    public function testDatabaseFailureCannotDeleteOriginalOrDerivativeFiles(): void
    {
        self::createClient();
        $media = $this->media();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $em->getConnection();
        $id = $media->getId();
        $storage = $this->createMock(MediaStorageInterface::class);
        $storage->expects(self::never())->method('delete');
        $service = $this->deletion($storage);
        $em->getEventManager()->addEventListener(['onFlush'], new class {
            public function onFlush(OnFlushEventArgs $args): void
            {
                throw new \RuntimeException('Injected deletion database failure');
            }
        });
        try {
            $service->delete($media);
            self::fail('Expected a database failure.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Injected deletion database failure', $exception->getMessage());
        }
        self::assertNull($connection->fetchOne('SELECT deleted_at FROM media WHERE id = ?', [$id]));
        self::assertFileExists('public/uploads/media/'.$media->getStorageKey());
    }

    public function testCleanupFailuresRetainADurableRetryAndCommandIsIdempotent(): void
    {
        self::createClient();
        $media = $this->media();
        $id = $media->getId();
        $storage = $this->createMock(MediaStorageInterface::class);
        $storage->expects(self::once())->method('delete')->willThrowException(new \RuntimeException('Injected storage failure'));
        self::assertFalse($this->deletion($storage)->delete($media));
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $repository = self::getContainer()->get(MediaRepository::class);
        self::assertNull($repository->find($id));
        self::assertSame([['id' => $id, 'storageKey' => $media->getStorageKey()]], $repository->pendingFileCleanup(0));
        self::assertFileExists('public/uploads/media/'.$media->getStorageKey());

        $tester = new CommandTester(self::getContainer()->get(\App\Command\MediaCleanupCommand::class));
        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('Cleaned 1 media files; 0', $tester->getDisplay());
        self::assertFileDoesNotExist('public/uploads/media/'.$media->getStorageKey());
        self::assertSame([], $repository->pendingFileCleanup(0));
        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('Cleaned 0 media files; 0', $tester->getDisplay());
    }

    public function testSuccessfulDeletionRemovesAllGeneratedDerivatives(): void
    {
        $client = self::createClient();
        $media = self::getContainer()->get(MediaUploader::class)->upload($this->png(), self::authenticatedUser());
        $this->keys[] = $media->getStorageKey();
        $id = $media->getId();
        foreach (['thumbnail', 'medium', 'large', 'webp'] as $filter) {
            $client->request('GET', '/media/cache/resolve/'.$filter.'/'.$media->getStorageKey());
            self::assertResponseStatusCodeSame(302);
            self::assertFileExists('public/uploads/media/cache/'.$filter.'/'.$media->getStorageKey());
        }
        $media = self::getContainer()->get(MediaRepository::class)->find($id);
        self::assertTrue(self::getContainer()->get(MediaDeletionService::class)->delete($media));
        foreach (['thumbnail', 'medium', 'large', 'webp'] as $filter) {
            self::assertFileDoesNotExist('public/uploads/media/cache/'.$filter.'/'.$media->getStorageKey());
        }
    }

    /** @return iterable<string, array{int, int, string}> */
    public static function unsafeDimensions(): iterable
    {
        yield 'dimension' => [8193, 1, 'dimension limit'];
        yield 'pixels' => [5000, 5000, 'megapixel limit'];
        yield 'memory' => [4000, 4000, 'memory limit'];
    }

    #[DataProvider('unsafeDimensions')]
    public function testHeaderLimitsRejectImagesBeforeRasterDecoding(int $width, int $height, string $message): void
    {
        self::createClient();
        $file = $this->png($width, $height);
        self::assertSame([$width, $height], array_slice(getimagesize($file->getPathname()), 0, 2));
        try {
            self::getContainer()->get(MediaUploader::class)->upload($file, self::authenticatedUser());
            self::fail('The unsafe raster header should be rejected before decoding.');
        } catch (\DomainException $exception) {
            self::assertStringContainsString($message, $exception->getMessage());
        }
        self::assertCount(0, self::getContainer()->get(MediaRepository::class)->findAll());
    }

    public function testDecodingAlsoRespectsTheCurrentPhpMemoryBudget(): void
    {
        self::createClient();
        $limit = (string) ini_get('memory_limit');
        try {
            ini_set('memory_limit', (string) (memory_get_usage(true) + 20_971_520));
            $file = $this->png(800, 800);
            try {
                self::getContainer()->get(MediaUploader::class)->upload($file, self::authenticatedUser());
                self::fail('Expected a memory guard rejection.');
            } catch (\DomainException $exception) {
                self::assertStringContainsString('memory limit', $exception->getMessage());
            }
        } finally {
            ini_set('memory_limit', $limit);
        }
    }

    public function testUploadPersistenceFailureCleansUpTheStoredBytes(): void
    {
        self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $em->getConnection();
        $listener = new class {
            public ?string $key = null;

            public function postPersist(PostPersistEventArgs $args): void
            {
                if ($args->getObject() instanceof Media) {
                    $this->key = $args->getObject()->getStorageKey();
                    throw new \RuntimeException('Injected upload persistence failure');
                }
            }
        };
        $em->getEventManager()->addEventListener(['postPersist'], $listener);
        try {
            self::getContainer()->get(MediaUploader::class)->upload($this->png(), self::authenticatedUser());
            self::fail('Expected upload failure.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Injected upload persistence failure', $exception->getMessage());
        }
        $key = $listener->key;
        self::assertNotNull($key);
        $this->keys[] = $key;
        self::assertFileDoesNotExist('public/uploads/media/'.$key);
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM media'));
    }

    private function deletion(MediaStorageInterface $storage): MediaDeletionService
    {
        return new MediaDeletionService(
            self::getContainer()->get(EntityManagerInterface::class),
            self::getContainer()->get(MediaRepository::class),
            self::getContainer()->get(MediaUsageChecker::class),
            $storage,
            new NullLogger(),
        );
    }

    private function media(): Media
    {
        $media = MediaFactory::createOne(['uploadedBy' => self::authenticatedUser()]);
        $this->keys[] = $media->getStorageKey();
        self::getContainer()->get(MediaStorageInterface::class)->write($media->getStorageKey(), 'test bytes');

        return $media;
    }

    private function png(int $width = 1, int $height = 1): UploadedFile
    {
        $path = tempnam('/tmp/opencode', 'media-regression-');
        $this->paths[] = $path;
        $image = imagecreatetruecolor(1, 1);
        imagepng($image, $path);
        unset($image);
        $bytes = file_get_contents($path);
        $header = 'IHDR'.pack('NN', $width, $height).substr($bytes, 24, 5);
        $bytes = substr($bytes, 0, 12).$header.pack('N', crc32($header)).substr($bytes, 33);
        file_put_contents($path, $bytes);

        return new UploadedFile($path, 'probe.png', 'image/png', test: true);
    }
}

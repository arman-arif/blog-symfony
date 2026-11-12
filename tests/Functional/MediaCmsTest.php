<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Media;
use App\Repository\MediaRepository;
use App\Tests\Factory\ArticleFactory;
use App\Tests\Support\InMemorySettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DomCrawler\Field\FileFormField;
use Symfony\Component\DomCrawler\Form;

final class MediaCmsTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        @mkdir(sys_get_temp_dir().'/fieldnotes-media-test', 0777, true);
    }

    protected function tearDown(): void
    {
        InMemorySettingsService::resetOverride();
        foreach (glob(sys_get_temp_dir().'/fieldnotes-media-test/*') ?: [] as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    public function testUploadStoresRandomizedNameMetadataAndVariants(): void
    {
        $client = self::createClient();
        $path = $this->png('photo.png', 64, 48);

        $crawler = $client->request('GET', '/admin/media');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Upload file')->form();
        $this->attachFile($form, $path);
        $form['media_upload[alt]'] = 'A small test image';
        $form['media_upload[caption]'] = 'Uploaded in a test.';
        $client->submit($form);

        self::assertResponseStatusCodeSame(302);
        self::assertStringContainsString('/admin/media/', (string) $client->getResponse()->headers->get('Location'));

        $repository = self::getContainer()->get(MediaRepository::class);
        $media = $repository->findOneBy(['originalName' => 'photo.png']);
        self::assertNotNull($media);
        self::assertSame('image/png', $media->getMimeType());
        self::assertSame(64, $media->getWidth());
        self::assertSame(48, $media->getHeight());
        self::assertSame('A small test image', $media->getAlt());
        self::assertSame('Uploaded in a test.', $media->getCaption());
        self::assertMatchesRegularExpression('#^\d{4}/\d{2}/[a-f0-9]{32}\.png$#', $media->getStorageKey());
        self::assertFileExists('public/uploads/media/'.$media->getStorageKey());
        self::assertArrayHasKey('thumbnail', $media->getVariants());
        self::assertArrayHasKey('webp', $media->getVariants());

        $client->request('GET', '/media/cache/resolve/thumbnail/'.$media->getStorageKey());
        self::assertResponseStatusCodeSame(302);
        self::assertStringContainsString('/uploads/media/cache/thumbnail/', (string) $client->getResponse()->headers->get('Location'));
        self::assertFileExists('public/uploads/media/cache/thumbnail/'.$media->getStorageKey());

        $this->cleanup($media);
    }

    public function testDisguisedAndExecutableUploadsAreRejected(): void
    {
        $client = self::createClient();

        $textPath = sys_get_temp_dir().'/fieldnotes-media-test/fake.jpg';
        file_put_contents($textPath, 'This is not an image.');
        $crawler = $client->request('GET', '/admin/media');
        $form = $crawler->selectButton('Upload file')->form();
        $this->attachFile($form, $textPath);
        $client->submit($form);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.field-error', 'extension does not match');

        $png = $this->png('shell.php.png', 10, 10);
        $crawler = $client->request('GET', '/admin/media');
        $form = $crawler->selectButton('Upload file')->form();
        $this->attachFile($form, $png);
        $client->submit($form);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.field-error', 'blocked extension');

        self::assertCount(0, self::getContainer()->get(MediaRepository::class)->findAll());
    }

    public function testOversizedUploadsAreRejected(): void
    {
        $client = self::createClient();
        InMemorySettingsService::setOverride('media.max_upload_bytes', 64);
        $path = $this->png('large.png', 200, 200);

        $crawler = $client->request('GET', '/admin/media');
        $form = $crawler->selectButton('Upload file')->form();
        $this->attachFile($form, $path);
        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.field-error', 'upload limit');
    }

    public function testMetadataEditingAndUsageProtectedDeletion(): void
    {
        $client = self::createClient();
        $media = $this->upload($client, $this->png('protected.png', 30, 30));

        $crawler = $client->request('GET', '/admin/media/'.$media->getId().'/edit');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Save details')->form();
        $form['media_edit[alt]'] = 'Updated alternative text';
        $form['media_edit[caption]'] = 'Updated caption';
        $client->submit($form);
        self::assertResponseStatusCodeSame(302);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $reloaded = self::getContainer()->get(MediaRepository::class)->find($media->getId());
        self::assertSame('Updated alternative text', $reloaded?->getAlt());

        $article = ArticleFactory::createOne(['author' => self::authenticatedUser(), 'featuredImage' => $reloaded]);
        $token = $this->deleteToken($client, '/admin/media/'.$media->getId().'/edit');
        $client->request('POST', '/admin/media/'.$media->getId().'/delete', ['_token' => $token]);
        self::assertResponseStatusCodeSame(302);
        $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'article featured image');
        self::assertNotNull(self::getContainer()->get(MediaRepository::class)->find($media->getId()));

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $articleReloaded = self::getContainer()->get(\App\Repository\ArticleRepository::class)->find($article->getId());
        self::assertNotNull($articleReloaded);
        $articleReloaded->setFeaturedImage(null);
        $entityManager->flush();
        $client->request('POST', '/admin/media/'.$media->getId().'/delete', ['_token' => $token]);
        self::assertResponseStatusCodeSame(302);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertNull(self::getContainer()->get(MediaRepository::class)->find($media->getId()));
        self::assertFileDoesNotExist('public/uploads/media/'.$media->getStorageKey());
    }

    public function testPickerApiReturnsImageMetadata(): void
    {
        $client = self::createClient();
        $media = $this->upload($client, $this->png('picker.png', 80, 60));

        $client->request('GET', '/admin/media/picker?q=picker');
        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($payload);
        self::assertNotEmpty($payload['items']);
        self::assertSame($media->getId(), $payload['items'][0]['id']);
        self::assertSame('picker.png', $payload['items'][0]['name']);
        self::assertArrayHasKey('thumbnail', $payload['items'][0]);

        $this->cleanup($media);
    }

    private function upload(object $client, string $path): Media
    {
        $crawler = $client->request('GET', '/admin/media');
        $form = $crawler->selectButton('Upload file')->form();
        $this->attachFile($form, $path);
        $client->submit($form);
        self::assertResponseStatusCodeSame(302);

        $repository = self::getContainer()->get(MediaRepository::class);
        $items = $repository->findBy([], ['id' => 'DESC'], 1);
        self::assertNotEmpty($items);

        return $items[0];
    }

    private function attachFile(Form $form, string $path): void
    {
        $field = $form['media_upload[file]'];
        self::assertInstanceOf(FileFormField::class, $field);
        $field->upload($path);
    }

    private function png(string $name, int $width, int $height): string
    {
        $path = sys_get_temp_dir().'/fieldnotes-media-test/'.$name;
        $image = imagecreatetruecolor($width, $height);
        self::assertNotFalse($image);
        imagefill($image, 0, 0, imagecolorallocate($image, 28, 91, 69));
        imagepng($image, $path);

        return $path;
    }

    private function deleteToken(object $client, string $path): string
    {
        $crawler = $client->request('GET', $path);
        $token = $crawler->filter('form[action$="/delete"] input[name="_token"]')->first()->attr('value');

        return (string) $token;
    }

    private function cleanup(Media $media): void
    {
        @unlink('public/uploads/media/'.$media->getStorageKey());
    }
}

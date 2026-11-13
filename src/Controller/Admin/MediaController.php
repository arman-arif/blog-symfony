<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Media;
use App\Entity\User;
use App\Form\MediaEditType;
use App\Form\MediaUploadType;
use App\Repository\MediaRepository;
use App\Service\Audit\ActivityLogger;
use App\Service\Media\MediaDeletionService;
use App\Service\Media\MediaStorageInterface;
use App\Service\Media\MediaUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/media', name: 'admin_media_')]
#[IsGranted('media.upload')]
final class MediaController extends AbstractController
{
    public function __construct(
        private readonly MediaRepository $media,
        private readonly MediaUploader $uploader,
        private readonly MediaStorageInterface $storage,
        private readonly MediaDeletionService $deletion,
        private readonly ActivityLogger $activityLogger,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $uploadForm = $this->createForm(MediaUploadType::class);
        $uploadForm->handleRequest($request);

        if ($uploadForm->isSubmitted() && $uploadForm->isValid()) {
            $file = $uploadForm->get('file')->getData();
            if (null === $file) {
                $uploadForm->addError(new \Symfony\Component\Form\FormError('Choose a file to upload.'));
            } else {
                try {
                    $media = $this->uploader->upload($file, $this->currentUser());
                    $alt = trim((string) $uploadForm->get('alt')->getData());
                    $caption = trim((string) $uploadForm->get('caption')->getData());
                    if ('' !== $alt) {
                        $media->setAlt($alt);
                    }
                    if ('' !== $caption) {
                        $media->setCaption($caption);
                    }
                    $this->entityManager->flush();
                    $this->activityLogger->log('media.uploaded', 'Media', $media->getId(), ['summary' => 'Uploaded “'.$media->getOriginalName().'”']);
                    $this->addFlash('success', 'File uploaded.');

                    return $this->redirectToRoute('admin_media_edit', ['id' => $media->getId()]);
                } catch (\DomainException $exception) {
                    $uploadForm->addError(new \Symfony\Component\Form\FormError($exception->getMessage()));
                }
            }
        }

        return $this->renderMediaIndex($request, $uploadForm);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $media = $this->findMedia($id);
        $this->assertCanManage($media);
        $form = $this->createForm(MediaEditType::class, $media);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->activityLogger->log('media.updated', 'Media', $media->getId(), ['summary' => 'Updated metadata for “'.$media->getOriginalName().'”']);
            $this->addFlash('success', 'Media details saved.');

            return $this->redirectToRoute('admin_media_edit', ['id' => $media->getId()]);
        }

        return $this->render('admin/media/edit.html.twig', ['media' => $media, 'form' => $form], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        $media = $this->findMedia($id);
        $this->assertCanManage($media);
        if (!$this->isCsrfTokenValid('media_delete', (string) $request->request->get('_token'))) {
            throw new AccessDeniedException('Invalid CSRF token.');
        }

        $name = $media->getOriginalName();
        try {
            $cleaned = $this->deletion->delete($media);
        } catch (\DomainException $exception) {
            $this->addFlash('danger', $exception->getMessage());

            return $this->redirectToRoute('admin_media_edit', ['id' => $media->getId()]);
        }

        $this->activityLogger->log('media.deleted', 'Media', $id, ['summary' => 'Deleted “'.$name.'”']);
        $this->addFlash($cleaned ? 'success' : 'warning', $cleaned ? 'Media deleted.' : 'Media deleted from the library. File cleanup failed and must be retried with app:media:cleanup.');

        return $this->redirectToRoute('admin_media_index');
    }

    #[Route('/picker', name: 'picker', methods: ['GET'])]
    public function picker(Request $request): JsonResponse
    {
        $items = $this->media->findForPicker(trim($request->query->getString('q')));
        $payload = array_map(fn (Media $media): array => [
            'id' => $media->getId(),
            'name' => $media->getOriginalName(),
            'url' => $this->mediaUrl($media),
            'thumbnail' => $this->mediaUrl($media, 'thumbnail'),
            'alt' => $media->getAlt(),
            'caption' => $media->getCaption(),
            'width' => $media->getWidth(),
            'height' => $media->getHeight(),
            'mimeType' => $media->getMimeType(),
        ], $items);

        return new JsonResponse(['items' => $payload]);
    }

    /** @param \Symfony\Component\Form\FormInterface<mixed> $uploadForm */
    private function renderMediaIndex(Request $request, \Symfony\Component\Form\FormInterface $uploadForm): Response
    {
        $filters = [
            'q' => trim($request->query->getString('q')),
            'type' => $request->query->getString('type'),
            'date' => $request->query->getString('date'),
        ];
        $page = max(1, $request->query->getInt('page', 1));
        $result = $this->media->findForAdmin($filters, $page, 24);

        return $this->render('admin/media/index.html.twig', [
            'mediaItems' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'filters' => $filters,
            'uploadForm' => $uploadForm,
        ], new Response(status: $uploadForm->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    private function findMedia(int $id): Media
    {
        return $this->media->find($id) ?? throw $this->createNotFoundException();
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException();
        }

        return $user;
    }

    private function assertCanManage(Media $media): void
    {
        if ($this->isGranted('media.manage.any')) {
            return;
        }

        if ($media->getUploadedBy()?->getId() !== $this->currentUser()->getId()) {
            throw new AccessDeniedException('You can only manage media you uploaded.');
        }
    }

    private function mediaUrl(Media $media, string $variant = 'original'): string
    {
        return $this->storage->url($media->getStorageKey(), $variant);
    }
}

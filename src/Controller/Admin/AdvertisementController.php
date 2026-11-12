<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Advertisement;
use App\Enum\AdvertisementPlacement;
use App\Form\AdvertisementFormType;
use App\Repository\AdvertisementRepository;
use App\Service\Audit\ActivityLogger;
use App\Service\Monetization\AdvertisementService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/advertisements', name: 'admin_ad_')]
#[IsGranted('ad.manage')]
final class AdvertisementController extends AbstractController
{
    public function __construct(
        private readonly AdvertisementRepository $ads,
        private readonly AdvertisementService $adService,
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $placement = $request->query->getString('placement');
        $criteria = [];
        if ('' !== $placement) {
            $criteria['placement'] = AdvertisementPlacement::from($placement);
        }
        if ($request->query->has('active')) {
            $criteria['isActive'] = $request->query->getBoolean('active');
        }

        return $this->render('admin/ad/index.html.twig', [
            'ads' => $this->ads->findBy($criteria, ['priority' => 'DESC', 'createdAt' => 'DESC']),
            'placement' => $placement,
            'active' => $request->query->get('active'),
            'placements' => AdvertisementPlacement::cases(),
        ]);
    }

    #[Route('/new', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        return $this->form($request, new Advertisement());
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        return $this->form($request, $this->ads->find($id) ?? throw $this->createNotFoundException());
    }

    #[Route('/{id}/toggle', name: 'toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggle(int $id, Request $request): Response
    {
        $ad = $this->ads->find($id) ?? throw $this->createNotFoundException();
        if (!$this->isCsrfTokenValid('ad_toggle', (string) $request->request->get('_token'))) {
            throw new AccessDeniedException('Invalid CSRF token.');
        }
        $this->adService->toggle($ad);
        $this->activityLogger->log('advertisement.toggled', 'Advertisement', $id, ['summary' => ($ad->isActive() ? 'Enabled' : 'Disabled').' “'.$ad->getName().'”']);

        return $this->redirectToRoute('admin_ad_index');
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        $ad = $this->ads->find($id) ?? throw $this->createNotFoundException();
        if (!$this->isCsrfTokenValid('ad_delete', (string) $request->request->get('_token'))) {
            throw new AccessDeniedException('Invalid CSRF token.');
        }
        $name = $ad->getName();
        $this->adService->delete($ad);
        $this->activityLogger->log('advertisement.deleted', 'Advertisement', $id, ['summary' => 'Deleted “'.$name.'”']);
        $this->addFlash('success', 'Advertisement deleted.');

        return $this->redirectToRoute('admin_ad_index');
    }

    private function form(Request $request, Advertisement $advertisement): Response
    {
        $form = $this->createForm(AdvertisementFormType::class, $advertisement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->adService->save($advertisement);
            $this->activityLogger->log('advertisement.saved', 'Advertisement', $advertisement->getId(), ['summary' => 'Saved “'.$advertisement->getName().'”']);
            $this->addFlash('success', 'Advertisement saved.');

            return $this->redirectToRoute('admin_ad_index');
        }

        return $this->render('admin/ad/form.html.twig', ['ad' => $advertisement, 'form' => $form], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}

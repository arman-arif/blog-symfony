<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Dto\MenuReorderPayload;
use App\Entity\Menu;
use App\Entity\MenuItem;
use App\Enum\MenuLocation;
use App\Form\MenuFormType;
use App\Form\MenuItemFormType;
use App\Repository\MenuItemRepository;
use App\Repository\MenuRepository;
use App\Service\Audit\ActivityLogger;
use App\Service\Menu\MenuService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/menus', name: 'admin_menu_')]
#[IsGranted('menu.manage')]
final class MenuController extends AbstractController
{
    public function __construct(
        private readonly MenuRepository $menus,
        private readonly MenuItemRepository $menuItems,
        private readonly MenuService $menuService,
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/menu/index.html.twig', ['menus' => $this->menus->findBy([], ['location' => 'ASC'])]);
    }

    #[Route('/new', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        return $this->edit($request, null);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, ?int $id = null): Response
    {
        $menu = null === $id ? (new Menu())->setName('')->setLocation(MenuLocation::Header) : ($this->menus->find($id) ?? throw $this->createNotFoundException());
        $details = clone $menu;
        $form = $this->createForm(MenuFormType::class, $details);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->menuService->save($menu, $details);
            } catch (\DomainException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }
        }
        if ($form->isSubmitted() && $form->isValid()) {
            $this->activityLogger->log('menu.saved', 'Menu', $menu->getId(), ['summary' => 'Saved menu “'.$menu->getName().'”']);
            $this->addFlash('success', 'Menu saved.');

            return $this->redirectToRoute('admin_menu_edit', ['id' => $menu->getId()]);
        }

        $item = new MenuItem();
        $itemForm = $this->createForm(MenuItemFormType::class, $item, ['menu' => $menu]);

        return $this->render('admin/menu/form.html.twig', [
            'menu' => $menu, 'form' => $form, 'itemForm' => $itemForm,
        ], new Response(status: $form->isSubmitted() && !$form->isValid() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/items/{itemId}/edit', name: 'item_edit', requirements: ['itemId' => '\d+'], methods: ['GET', 'POST'])]
    public function editItem(int $itemId, Request $request): Response
    {
        $item = $this->menuItems->find($itemId) ?? throw $this->createNotFoundException();
        $details = clone $item;
        $form = $this->createForm(MenuItemFormType::class, $details, ['menu' => $item->getMenu()]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->menuService->saveItem($item, $details);
            } catch (\DomainException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }
        }
        if ($form->isSubmitted() && $form->isValid()) {
            $this->addFlash('success', 'Menu item saved.');

            return $this->redirectToRoute('admin_menu_edit', ['id' => $item->getMenu()->getId()]);
        }

        return $this->render('admin/menu/item.html.twig', ['item' => $details, 'form' => $form], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/{id}/items', name: 'item_create', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function addItem(int $id, Request $request): Response
    {
        $menu = $this->menus->find($id) ?? throw $this->createNotFoundException();
        $item = new MenuItem();
        $form = $this->createForm(MenuItemFormType::class, $item, ['menu' => $menu]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->menuService->addItem($menu, $item);
            $this->addFlash('success', 'Menu item added.');
        } else {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('danger', $error->getMessage());
            }
        }

        return $this->redirectToRoute('admin_menu_edit', ['id' => $menu->getId()]);
    }

    #[Route('/items/{itemId}/delete', name: 'item_delete', requirements: ['itemId' => '\d+'], methods: ['POST'])]
    public function deleteItem(int $itemId, Request $request): Response
    {
        $item = $this->menuItems->find($itemId) ?? throw $this->createNotFoundException();
        if (!$this->isCsrfTokenValid('menu_item_delete', (string) $request->request->get('_token'))) {
            throw new AccessDeniedException('Invalid CSRF token.');
        }
        $menuId = $item->getMenu()->getId();
        $this->menuService->removeItem($item);
        $this->addFlash('success', 'Menu item removed.');

        return $this->redirectToRoute('admin_menu_edit', ['id' => $menuId]);
    }

    #[Route('/{id}/reorder', name: 'reorder', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function reorder(int $id, Request $request, #[MapRequestPayload(acceptFormat: 'json')] MenuReorderPayload $payload): JsonResponse
    {
        $menu = $this->menus->find($id) ?? throw $this->createNotFoundException();
        if (!$this->isCsrfTokenValid('menu_reorder', (string) ($payload->_token ?? $request->headers->get('X-CSRF-Token')))) {
            return new JsonResponse(['error' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN);
        }
        try {
            $this->menuService->reorder($menu, $payload->items);
        } catch (\DomainException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(['ok' => true]);
    }
}

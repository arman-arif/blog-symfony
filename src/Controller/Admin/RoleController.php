<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Role;
use App\Form\RoleFormType;
use App\Repository\PermissionRepository;
use App\Repository\RoleRepository;
use App\Service\Audit\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class RoleController extends AbstractController
{
    public function __construct(
        private readonly RoleRepository $roles,
        private readonly PermissionRepository $permissions,
        private readonly EntityManagerInterface $entityManager,
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    #[Route('/admin/roles', name: 'admin_role_index', methods: ['GET'])]
    #[IsGranted('role.manage')]
    public function index(): Response
    {
        return $this->render('admin/role/index.html.twig', ['roles' => $this->roles->findBy([], ['name' => 'ASC'])]);
    }

    #[Route('/admin/roles/new', name: 'admin_role_create', methods: ['GET', 'POST'])]
    #[IsGranted('role.manage')]
    public function create(Request $request): Response
    {
        return $this->form($request, new Role());
    }

    #[Route('/admin/roles/{id}/edit', name: 'admin_role_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('role.manage')]
    public function edit(int $id, Request $request): Response
    {
        return $this->form($request, $this->roles->find($id) ?? throw $this->createNotFoundException());
    }

    #[Route('/admin/permissions', name: 'admin_permission_index', methods: ['GET', 'POST'])]
    #[IsGranted('permission.manage')]
    public function permissions(Request $request): Response
    {
        $roles = $this->roles->findBy([], ['name' => 'ASC']);
        $permissions = $this->permissions->findBy([], ['groupName' => 'ASC', 'label' => 'ASC']);
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('permission_matrix', (string) $request->request->get('_token'))) {
                throw new AccessDeniedException('Invalid CSRF token.');
            }
            $submitted = $request->request->all()['permissions'] ?? [];
            foreach ($roles as $role) {
                $codes = (array) ($submitted[$role->getId()] ?? []);
                $role->getPermissions()->clear();
                foreach ($permissions as $permission) {
                    if ('ROLE_SUPER_ADMIN' === $role->getCode() || in_array($permission->getCode(), $codes, true)) {
                        $role->addPermission($permission);
                    }
                }
            }
            $this->entityManager->flush();
            $this->activityLogger->log('permissions.updated', 'Permission', null, ['summary' => 'Updated the role permission matrix']);
            $this->addFlash('success', 'Permission matrix updated.');

            return $this->redirectToRoute('admin_permission_index');
        }

        $groups = [];
        foreach ($permissions as $permission) {
            $groups[$permission->getGroupName()][] = $permission;
        }

        return $this->render('admin/role/permissions.html.twig', [
            'roles' => $roles, 'groups' => $groups, 'permissionCount' => count($permissions),
        ]);
    }

    private function form(Request $request, Role $role): Response
    {
        $form = $this->createForm(RoleFormType::class, $role);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ('ROLE_SUPER_ADMIN' === $role->getCode()) {
                $role->getPermissions()->clear();
                foreach ($this->permissions->findAll() as $permission) {
                    $role->addPermission($permission);
                }
            }
            $this->entityManager->persist($role);
            $this->entityManager->flush();
            $this->activityLogger->log('role.saved', 'Role', $role->getId(), ['summary' => 'Saved role “'.$role->getName().'”']);
            $this->addFlash('success', 'Role saved.');

            return $this->redirectToRoute('admin_role_index');
        }

        return $this->render('admin/role/form.html.twig', ['role' => $role, 'form' => $form], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}

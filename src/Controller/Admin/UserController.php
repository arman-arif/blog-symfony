<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\AdminUserFormType;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use App\Security\UserVoter;
use App\Service\Audit\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users', name: 'admin_user_')]
#[IsGranted('user.manage')]
final class UserController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly RoleRepository $roles,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $filters = [
            'q' => $request->query->getString('q'),
            'role' => $request->query->getString('role'),
            'active' => $request->query->getString('active'),
        ];
        $page = max(1, $request->query->getInt('page', 1));

        return $this->render('admin/user/index.html.twig', [
            'users' => $this->users->paginateAdmin($filters, $page),
            'filters' => $filters,
            'page' => $page,
            'roles' => $this->roles->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        return $this->form($request, new User(), true);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $user = $this->users->find($id) ?? throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted(UserVoter::MANAGE, $user);

        return $this->form($request, $user, false);
    }

    private function form(Request $request, User $user, bool $creating): Response
    {
        $wasSuperAdmin = $user->hasRoleCode('ROLE_SUPER_ADMIN');
        $form = $this->createForm(AdminUserFormType::class, $user, ['creating' => $creating]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $hasErrors = false;
            $existing = $this->users->findOneByEmail($user->getEmail());
            if (null !== $existing && $existing->getId() !== $user->getId()) {
                $form->get('email')->addError(new FormError('Another account already uses this email.'));
                $hasErrors = true;
            }
            $requestData = $request->request->all('admin_user_form');
            $submittedRoles = is_array($requestData['assignedRoles'] ?? null) ? array_map(strval(...), $requestData['assignedRoles']) : [];
            $nowSuperAdmin = false;
            foreach ($user->getAssignedRoles() as $role) {
                $nowSuperAdmin = $nowSuperAdmin || (in_array((string) $role->getId(), $submittedRoles, true) && 'ROLE_SUPER_ADMIN' === $role->getCode());
            }
            if ($wasSuperAdmin && !$nowSuperAdmin && $this->users->countSuperAdmins() <= 1) {
                $form->get('assignedRoles')->addError(new FormError('The last Super Administrator cannot lose that role.'));
                $hasErrors = true;
            }

            $plainPassword = (string) $form->get('temporaryPassword')->getData();
            if ('' !== $plainPassword && strlen($plainPassword) < 10) {
                $form->get('temporaryPassword')->addError(new FormError('Use at least 10 characters.'));
                $hasErrors = true;
            }
            if (!$hasErrors) {
                if ('' !== $plainPassword) {
                    $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
                }
                if ($creating) {
                    $user->setIsVerified(true);
                }
                $user->getAssignedRoles()->clear();
                foreach ($this->roles->findBy(['id' => $submittedRoles]) as $role) {
                    $user->addAssignedRole($role);
                }
                $this->entityManager->persist($user);
                $this->entityManager->flush();
                $this->activityLogger->log($creating ? 'user.created' : 'user.updated', 'User', $user->getId(), ['summary' => ($creating ? 'Created' : 'Updated').' user “'.$user->getDisplayName().'”']);
                $this->addFlash('success', $creating ? 'User created.' : 'User updated.');

                return $this->redirectToRoute('admin_user_index');
            }
        }

        return $this->render('admin/user/form.html.twig', ['user' => $user, 'form' => $form, 'creating' => $creating], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}

<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Role;
use App\Entity\User;
use App\Form\AdminUserFormType;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use App\Security\RoleAssignmentPolicy;
use App\Security\UserVoter;
use App\Service\Audit\ActivityLogger;
use App\Service\User\UserManagementService;
use Doctrine\Common\Collections\Collection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Exception\ValidationFailedException;

#[Route('/admin/users', name: 'admin_user_')]
#[IsGranted('user.manage')]
final class UserController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly RoleRepository $roles,
        private readonly RoleAssignmentPolicy $rolePolicy,
        private readonly UserManagementService $userManagement,
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
        $actor = $this->getUser();
        \assert($actor instanceof User);
        $assignableRoles = array_values(array_filter($this->roles->findAll(), fn ($role): bool => $this->rolePolicy->canAssign($actor, $role)));
        // Invalid input must not dirty the managed account (including the session user).
        $details = $creating ? $user : clone $user;
        $form = $this->createForm(AdminUserFormType::class, $details, ['creating' => $creating, 'assignable_roles' => $assignableRoles]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $hasErrors = false;
            $existing = $this->users->findOneByEmail($details->getEmail());
            if (null !== $existing && $existing->getId() !== $user->getId()) {
                $form->get('email')->addError(new FormError('Another account already uses this email.'));
                $hasErrors = true;
            }
            $plainPassword = (string) $form->get('temporaryPassword')->getData();
            if (!$hasErrors) {
                try {
                    /** @var Collection<int, Role> $assignedRoles */
                    $assignedRoles = $form->get('assignedRoles')->getData();
                    $this->userManagement->save($user, $actor, array_values($assignedRoles->toArray()), $plainPassword, $creating, $details);
                } catch (ValidationFailedException $exception) {
                    foreach ($exception->getViolations() as $violation) {
                        $form->get('temporaryPassword')->addError(new FormError((string) $violation->getMessage()));
                    }

                    return $this->render('admin/user/form.html.twig', ['user' => $user, 'form' => $form, 'creating' => $creating], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
                } catch (\DomainException $exception) {
                    $form->addError(new FormError($exception->getMessage()));

                    return $this->render('admin/user/form.html.twig', ['user' => $user, 'form' => $form, 'creating' => $creating], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
                }
                $this->activityLogger->log($creating ? 'user.created' : 'user.updated', 'User', $user->getId(), ['summary' => ($creating ? 'Created' : 'Updated').' user “'.$user->getDisplayName().'”']);
                $this->addFlash('success', $creating ? 'User created.' : 'User updated.');

                return $this->redirectToRoute('admin_user_index');
            }
        }

        return $this->render('admin/user/form.html.twig', ['user' => $user, 'form' => $form, 'creating' => $creating], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}

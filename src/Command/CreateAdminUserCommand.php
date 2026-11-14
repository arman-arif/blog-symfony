<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use App\Service\Audit\ActivityLogger;
use App\Validator\StrongPassword;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(name: 'app:user:create-admin', description: 'Create the first verified Super Administrator without demo fixtures.')]
final class CreateAdminUserCommand extends Command
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly RoleRepository $roles,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
        private readonly ActivityLogger $activityLogger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('email', null, InputOption::VALUE_REQUIRED)
            ->addOption('name', null, InputOption::VALUE_REQUIRED)
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Prefer the hidden prompt or an environment-provided value in automation.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = trim((string) ($input->getOption('email') ?: $io->ask('Email address')));
        $name = trim((string) ($input->getOption('name') ?: $io->ask('Display name')));
        $password = (string) $input->getOption('password');
        if ('' === $password) {
            $question = (new Question('Password (min 12 characters, hidden)'))->setHidden(true)->setHiddenFallback(false);
            $password = (string) $io->askQuestion($question);
        }

        if (false === filter_var($email, \FILTER_VALIDATE_EMAIL) || '' === $name) {
            $io->error('Provide a valid email address and display name.');

            return Command::FAILURE;
        }
        if (null !== $this->users->findOneByEmail($email)) {
            $io->error('An account with that email already exists.');

            return Command::FAILURE;
        }
        $violations = $this->validator->validate($password, [new Assert\NotBlank(), new StrongPassword()]);
        if (count($violations) > 0) {
            $io->error((string) $violations->get(0)->getMessage());

            return Command::FAILURE;
        }
        $role = $this->roles->findOneByCode('ROLE_SUPER_ADMIN');
        if (null === $role) {
            $io->error('ROLE_SUPER_ADMIN is missing. Run app:security:sync-permissions first.');

            return Command::FAILURE;
        }

        $user = (new User())->setEmail($email)->setDisplayName($name)->setIsActive(true)->setIsVerified(true)->addAssignedRole($role);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->activityLogger->log('user.created', 'User', $user->getId(), ['summary' => 'Created the bootstrap Super Administrator']);

        $io->success('Created Super Administrator '.$email.'. Remove any temporary credentials from shell history.');

        return Command::SUCCESS;
    }
}

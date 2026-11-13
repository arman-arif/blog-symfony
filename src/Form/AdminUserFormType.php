<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Role;
use App\Entity\User;
use App\Validator\StrongPassword;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/** @extends AbstractType<User> */
final class AdminUserFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('displayName', TextType::class, ['attr' => ['id' => 'user-name'], 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 150)]])
            ->add('email', EmailType::class, ['attr' => ['id' => 'user-email'], 'constraints' => [new Assert\NotBlank(), new Assert\Email(), new Assert\Length(max: 180)]])
            ->add('jobTitle', TextType::class, ['required' => false, 'constraints' => [new Assert\Length(max: 150)]])
            ->add('bio', TextareaType::class, ['required' => false, 'attr' => ['rows' => 4], 'constraints' => [new Assert\Length(max: 2000)]])
            ->add('assignedRoles', EntityType::class, [
                'class' => Role::class,
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => true,
                'by_reference' => false,
                'mapped' => false,
                'choices' => $options['assignable_roles'],
                'data' => $builder->getData()?->getAssignedRoles()->toArray() ?? [],
                'label' => 'Roles',
            ])
            ->add('isActive', CheckboxType::class, ['required' => false])
            ->add('isVerified', CheckboxType::class, ['required' => false])
            ->add('temporaryPassword', PasswordType::class, [
                'mapped' => false,
                'required' => (bool) $options['creating'],
                'label' => $options['creating'] ? 'Temporary password' : 'New password (leave blank to keep current)',
                'always_empty' => true,
                'attr' => ['autocomplete' => 'new-password'],
                'constraints' => array_merge($options['creating'] ? [new Assert\NotBlank()] : [], [new Assert\Length(min: 12, max: 4096), new StrongPassword()]),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class, 'creating' => false, 'assignable_roles' => []]);
        $resolver->setAllowedTypes('creating', 'bool');
        $resolver->setAllowedTypes('assignable_roles', 'array');
    }
}

<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Permission;
use App\Entity\Role;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/** @extends AbstractType<Role> */
final class RoleFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['attr' => ['id' => 'role-name'], 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 100)]])
            ->add('code', TextType::class, ['attr' => ['id' => 'role-code'], 'constraints' => [new Assert\NotBlank(), new Assert\Regex(pattern: '/^ROLE_[A-Z0-9_]+$/', message: 'Use a code like ROLE_EDITOR.'), new Assert\Length(max: 100)]])
            ->add('description', TextareaType::class, ['required' => false, 'attr' => ['rows' => 3], 'constraints' => [new Assert\Length(max: 1000)]])
            ->add('permissions', EntityType::class, [
                'class' => Permission::class,
                'choice_label' => 'label',
                'multiple' => true,
                'expanded' => true,
                'by_reference' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Role::class]);
    }
}

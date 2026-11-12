<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/** @extends AbstractType<User> */
final class ProfileFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('displayName', TextType::class, [
                'label' => 'Display name',
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 150)],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email address',
                'constraints' => [new Assert\NotBlank(), new Assert\Email(), new Assert\Length(max: 180)],
            ])
            ->add('jobTitle', TextType::class, [
                'label' => 'Role or job title',
                'required' => false,
                'constraints' => [new Assert\Length(max: 150)],
            ])
            ->add('bio', TextareaType::class, [
                'label' => 'Biography',
                'required' => false,
                'attr' => ['rows' => 5],
                'constraints' => [new Assert\Length(max: 2000)],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}

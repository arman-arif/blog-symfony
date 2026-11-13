<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints as Assert;

/** @extends AbstractType<array<string, string>> */
final class TwoFactorDisableFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Current password',
                'constraints' => [new Assert\NotBlank(), new UserPassword()],
                'attr' => ['autocomplete' => 'current-password'],
            ])
            ->add('code', TextType::class, [
                'label' => 'Authenticator code',
                'constraints' => [new Assert\NotBlank(), new Assert\Regex('/^[0-9]{6}$/')],
                'attr' => ['inputmode' => 'numeric', 'autocomplete' => 'one-time-code', 'maxlength' => 6],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null, 'csrf_token_id' => '2fa_disable']);
    }
}

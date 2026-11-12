<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Menu;
use App\Enum\MenuLocation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/** @extends AbstractType<Menu> */
final class MenuFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['attr' => ['id' => 'menu-name'], 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 150)]])
            ->add('location', EnumType::class, [
                'class' => MenuLocation::class,
                'choice_label' => static fn (MenuLocation $location): string => ucfirst($location->value),
                'attr' => ['id' => 'menu-location'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Menu::class]);
    }
}

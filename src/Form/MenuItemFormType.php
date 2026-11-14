<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Menu;
use App\Entity\MenuItem;
use App\Enum\MenuItemType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/** @extends AbstractType<MenuItem> */
final class MenuItemFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, ['empty_data' => '', 'attr' => ['id' => 'menu-item-label'], 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 150)]])
            ->add('type', EnumType::class, [
                'class' => MenuItemType::class,
                'choice_label' => static fn (MenuItemType $type): string => ucfirst($type->value),
                'attr' => ['id' => 'menu-item-type'],
            ])
            ->add('target', TextType::class, [
                'label' => 'Target path or slug',
                'empty_data' => '',
                'attr' => ['id' => 'menu-item-target'],
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 2048)],
                'help' => 'Use /path for internal links, https:// for external links, or a slug for page/category/article targets.',
            ])
            ->add('parent', EntityType::class, [
                'class' => MenuItem::class,
                'choices' => $options['menu']->getItems(),
                'choice_label' => 'label',
                'required' => false,
                'placeholder' => 'Top level',
                'attr' => ['id' => 'menu-item-parent'],
            ])
            ->add('position', IntegerType::class, ['constraints' => [new Assert\NotNull(), new Assert\Range(min: 0, max: 999)], 'attr' => ['id' => 'menu-item-position', 'min' => 0, 'max' => 999]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('menu')->setAllowedTypes('menu', Menu::class);
        $resolver->setDefaults(['data_class' => MenuItem::class]);
    }
}

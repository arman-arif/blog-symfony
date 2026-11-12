<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Page;
use App\Enum\PageStatus;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/** @extends AbstractType<Page> */
final class PageFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['attr' => ['id' => 'page-title'], 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 255)]])
            ->add('content', TextareaType::class, ['attr' => ['id' => 'page-content', 'rows' => 14], 'constraints' => [new Assert\NotBlank()]])
            ->add('status', EnumType::class, [
                'class' => PageStatus::class,
                'choice_label' => static fn (PageStatus $status): string => ucfirst($status->value),
                'attr' => ['id' => 'page-status'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Page::class]);
    }
}

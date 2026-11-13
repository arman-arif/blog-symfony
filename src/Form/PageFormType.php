<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Page;
use App\Enum\PageStatus;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
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
            ->add('title', TextType::class, ['empty_data' => '', 'attr' => ['id' => 'page-title'], 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 255)]])
            ->add('slug', TextType::class, [
                'required' => false,
                'empty_data' => '',
                'help' => 'Leave blank to generate from the title. Changing a slug changes its public URL.',
                'attr' => ['id' => 'page-slug', 'maxlength' => 280],
                'constraints' => [new Assert\Length(max: 280), new Assert\Regex('/^$|^[a-z0-9]+(?:-[a-z0-9]+)*$/')],
            ])
            ->add('content', TextareaType::class, ['empty_data' => '', 'attr' => ['id' => 'page-content', 'rows' => 14], 'constraints' => [new Assert\NotBlank()]])
            ->add('status', EnumType::class, [
                'class' => PageStatus::class,
                'choice_label' => static fn (PageStatus $status): string => ucfirst($status->value),
                'attr' => ['id' => 'page-status'],
            ])
            ->add('publishedAt', DateTimeType::class, [
                'label' => 'Publication time (UTC)',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'model_timezone' => 'UTC',
                'view_timezone' => 'UTC',
                'help' => 'Published pages remain hidden until this time. Leave blank to publish now.',
            ])
            ->add('seo', SeoMetadataType::class, ['label' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Page::class]);
    }
}

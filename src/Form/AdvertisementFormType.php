<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Advertisement;
use App\Entity\Media;
use App\Enum\AdvertisementPlacement;
use App\Enum\AdvertisementType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/** @extends AbstractType<Advertisement> */
final class AdvertisementFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'attr' => ['id' => 'ad-name', 'maxlength' => 150],
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 150)],
            ])
            ->add('type', EnumType::class, [
                'class' => AdvertisementType::class,
                'choice_label' => static fn (AdvertisementType $type): string => ucfirst($type->value),
                'attr' => ['id' => 'ad-type'],
            ])
            ->add('placement', EnumType::class, [
                'class' => AdvertisementPlacement::class,
                'choice_label' => static fn (AdvertisementPlacement $placement): string => ucwords(str_replace('_', ' ', $placement->value)),
                'attr' => ['id' => 'ad-placement'],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Creative HTML / network code',
                'required' => false,
                'attr' => ['id' => 'ad-content', 'rows' => 8, 'maxlength' => 10000],
                'constraints' => [new Assert\Length(max: 10000)],
            ])
            ->add('image', EntityType::class, [
                'class' => Media::class,
                'choice_label' => 'originalName',
                'required' => false,
                'placeholder' => 'No image',
                'attr' => ['id' => 'ad-image'],
            ])
            ->add('targetUrl', UrlType::class, [
                'label' => 'Destination URL',
                'required' => false,
                'default_protocol' => 'https',
                'attr' => ['id' => 'ad-target-url'],
            ])
            ->add('afterParagraph', IntegerType::class, [
                'label' => 'Insert after paragraph',
                'required' => false,
                'attr' => ['id' => 'ad-after-paragraph', 'min' => 1, 'max' => 50],
                'constraints' => [new Assert\Positive(), new Assert\LessThanOrEqual(50)],
            ])
            ->add('startsAt', DateTimeType::class, [
                'required' => false,
                'widget' => 'single_text',
                'html5' => true,
                'attr' => ['id' => 'ad-start'],
            ])
            ->add('endsAt', DateTimeType::class, [
                'required' => false,
                'widget' => 'single_text',
                'html5' => true,
                'attr' => ['id' => 'ad-end'],
                'constraints' => [new Assert\GreaterThanOrEqual(propertyPath: 'startsAt', message: 'The end time must be after the start time.')],
            ])
            ->add('priority', IntegerType::class, [
                'attr' => ['id' => 'ad-priority', 'min' => 0, 'max' => 100],
                'constraints' => [new Assert\Range(min: 0, max: 100)],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Enable placement',
                'required' => false,
                'attr' => ['id' => 'ad-active'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Advertisement::class]);
    }
}

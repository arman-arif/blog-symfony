<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Tag;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/** @extends AbstractType<Tag> */
final class TagFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'empty_data' => '',
                'attr' => ['id' => 'content-name', 'maxlength' => 100],
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 100)],
            ])
            ->add('slug', TextType::class, [
                'required' => false,
                'empty_data' => '',
                'attr' => ['id' => 'content-slug', 'maxlength' => 120, 'pattern' => '[a-z0-9]+(-[a-z0-9]+)*', 'data-slug-source' => 'content-name'],
                'constraints' => [new Assert\Regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'Use lowercase letters, numbers and single hyphens.')],
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'attr' => ['id' => 'content-description', 'rows' => 4],
                'constraints' => [new Assert\Length(max: 2000)],
            ])
            ->add('seo', SeoMetadataType::class, ['label' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Tag::class]);
    }
}

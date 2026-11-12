<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\SeoMetadata;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/** @extends AbstractType<SeoMetadata> */
final class SeoMetadataType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'SEO title',
                'required' => false,
                'attr' => ['id' => 'article-seo-title', 'maxlength' => 70],
                'constraints' => [new Assert\Length(max: 70)],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Meta description',
                'required' => false,
                'attr' => ['id' => 'article-seo-description', 'maxlength' => 160, 'rows' => 3],
                'constraints' => [new Assert\Length(max: 160)],
            ])
            ->add('focusKeyword', TextType::class, [
                'label' => 'Focus keyword',
                'required' => false,
                'attr' => ['id' => 'article-focus-keyword', 'maxlength' => 100],
            ])
            ->add('canonicalUrl', UrlType::class, [
                'label' => 'Canonical URL',
                'required' => false,
                'default_protocol' => 'https',
                'attr' => ['id' => 'article-canonical', 'placeholder' => 'https://example.com/story'],
            ])
            ->add('noindex', CheckboxType::class, [
                'label' => 'Ask search engines not to index this content',
                'required' => false,
                'attr' => ['id' => 'article-noindex'],
            ])
            ->add('ogTitle', TextType::class, [
                'label' => 'Open Graph title',
                'required' => false,
                'attr' => ['maxlength' => 255],
            ])
            ->add('ogDescription', TextareaType::class, [
                'label' => 'Open Graph description',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('ogImageUrl', UrlType::class, [
                'label' => 'Open Graph image URL',
                'required' => false,
                'default_protocol' => 'https',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => SeoMetadata::class]);
    }
}

<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/** @extends AbstractType<array<string, mixed>> */
final class MediaUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('file', FileType::class, [
                'label' => 'Choose a file',
                'attr' => ['id' => 'media-file', 'accept' => 'image/jpeg,image/png,image/webp,image/gif,application/pdf,text/plain,text/csv,.docx'],
                'constraints' => [new Assert\NotNull(message: 'Choose a file to upload.')],
            ])
            ->add('alt', TextType::class, [
                'label' => 'Alternative text',
                'required' => false,
                'attr' => ['id' => 'media-alt', 'maxlength' => 500],
                'constraints' => [new Assert\Length(max: 500)],
            ])
            ->add('caption', TextareaType::class, [
                'label' => 'Caption',
                'required' => false,
                'attr' => ['id' => 'media-caption', 'rows' => 2, 'maxlength' => 500],
                'constraints' => [new Assert\Length(max: 500)],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}

<?php

declare(strict_types=1);

namespace App\Form;

use App\Service\Comment\CommentSubmission;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/** @extends AbstractType<CommentSubmission> */
final class CommentFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('content', TextareaType::class, [
                'label' => 'Your thoughts',
                'attr' => ['id' => 'comment-content', 'minlength' => 2, 'maxlength' => 5000, 'rows' => 5],
                'constraints' => [new Assert\NotBlank(), new Assert\Length(min: 2, max: 5000)],
            ])
            ->add('guestName', TextType::class, [
                'label' => 'Your name',
                'required' => false,
                'attr' => ['id' => 'comment-name', 'maxlength' => 80, 'autocomplete' => 'name'],
                'constraints' => [new Assert\Length(max: 80)],
            ])
            ->add('guestEmail', EmailType::class, [
                'label' => 'Email (never displayed)',
                'required' => false,
                'attr' => ['id' => 'comment-email', 'maxlength' => 180, 'autocomplete' => 'email'],
                'constraints' => [new Assert\Email(), new Assert\Length(max: 180)],
            ])
            ->add('parentId', HiddenType::class, ['required' => false])
            ->add('startedAt', HiddenType::class)
            ->add('website', TextType::class, [
                'label' => 'Leave this field empty',
                'required' => false,
                'attr' => ['id' => 'comment-website', 'tabindex' => '-1', 'autocomplete' => 'off'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => CommentSubmission::class]);
    }
}

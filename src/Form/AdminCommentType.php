<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Comment;
use App\Enum\CommentStatus;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/** @extends AbstractType<Comment> */
final class AdminCommentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('content', TextareaType::class, [
                'attr' => ['id' => 'comment-content', 'rows' => 6, 'maxlength' => 5000],
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 5000)],
            ])
            ->add('status', EnumType::class, [
                'class' => CommentStatus::class,
                'choice_label' => static fn (CommentStatus $status): string => ucfirst($status->value),
                'attr' => ['id' => 'comment-status'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Comment::class]);
    }
}

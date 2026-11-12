<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Article;
use App\Entity\Category;
use App\Entity\Media;
use App\Entity\Tag;
use App\Entity\User;
use App\Enum\ArticleStatus;
use App\Enum\ArticleVisibility;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/** @extends AbstractType<Article> */
final class ArticleFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'empty_data' => '',
                'attr' => ['id' => 'article-title', 'minlength' => 5, 'maxlength' => 255],
                'constraints' => [new Assert\NotBlank(), new Assert\Length(min: 5, max: 255)],
            ])
            ->add('slug', TextType::class, [
                'required' => false,
                'empty_data' => '',
                'attr' => ['id' => 'article-slug', 'maxlength' => 280, 'pattern' => '[a-z0-9]+(-[a-z0-9]+)*'],
                'constraints' => [new Assert\Regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'Use lowercase letters, numbers and single hyphens.')],
            ])
            ->add('subtitle', TextType::class, [
                'required' => false,
                'attr' => ['id' => 'article-subtitle', 'maxlength' => 500],
                'constraints' => [new Assert\Length(max: 500)],
            ])
            ->add('excerpt', TextareaType::class, [
                'required' => false,
                'attr' => ['id' => 'article-excerpt', 'maxlength' => 400, 'rows' => 3],
                'constraints' => [new Assert\Length(max: 400)],
            ])
            ->add('content', TextareaType::class, [
                'required' => false,
                'empty_data' => '',
                'constraints' => [new Assert\NotBlank(message: 'Write at least a short article body.'), new Assert\Length(min: 20)],
            ])
            ->add('status', EnumType::class, [
                'class' => ArticleStatus::class,
                'choice_label' => static fn (ArticleStatus $status): string => ucfirst(str_replace('_', ' ', $status->value)),
                'attr' => ['id' => 'article-status'],
            ])
            ->add('visibility', EnumType::class, [
                'class' => ArticleVisibility::class,
                'choice_label' => static fn (ArticleVisibility $visibility): string => ucfirst($visibility->value),
                'attr' => ['id' => 'article-visibility'],
            ])
            ->add('scheduledAt', DateTimeType::class, [
                'required' => false,
                'widget' => 'single_text',
                'html5' => true,
                'attr' => ['id' => 'article-scheduled-at'],
            ])
            ->add('author', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'displayName',
                'query_builder' => static fn (\App\Repository\UserRepository $users) => $users->createQueryBuilder('user')->andWhere('user.isActive = true')->orderBy('user.displayName', 'ASC'),
                'attr' => ['id' => 'article-author'],
            ])
            ->add('categories', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'name',
                'multiple' => true,
                'required' => false,
                'by_reference' => false,
                'query_builder' => static fn (\App\Repository\CategoryRepository $categories) => $categories->createQueryBuilder('category')->orderBy('category.lft', 'ASC'),
                'attr' => ['id' => 'article-categories', 'size' => 8],
            ])
            ->add('tags', EntityType::class, [
                'class' => Tag::class,
                'choice_label' => 'name',
                'multiple' => true,
                'required' => false,
                'by_reference' => false,
                'query_builder' => static fn (\App\Repository\TagRepository $tags) => $tags->createQueryBuilder('tag')->orderBy('tag.name', 'ASC'),
                'attr' => ['id' => 'article-tags', 'size' => 8],
            ])
            ->add('featuredImage', EntityType::class, [
                'class' => Media::class,
                'choice_label' => 'originalName',
                'required' => false,
                'placeholder' => 'No featured image',
                'query_builder' => static fn (\App\Repository\MediaRepository $media) => $media->createQueryBuilder('media')->orderBy('media.createdAt', 'DESC'),
                'attr' => ['id' => 'article-featured-image'],
            ])
            ->add('allowComments', CheckboxType::class, [
                'required' => false,
                'attr' => ['id' => 'article-allow-comments'],
            ])
            ->add('seo', SeoMetadataType::class, ['label' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Article::class]);
    }
}

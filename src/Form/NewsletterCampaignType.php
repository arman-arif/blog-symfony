<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\NewsletterCampaign;
use App\Enum\NewsletterCampaignStatus;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/** @extends AbstractType<NewsletterCampaign> */
final class NewsletterCampaignType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('subject', TextType::class, [
                'attr' => ['id' => 'campaign-subject', 'maxlength' => 255],
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 255)],
            ])
            ->add('html', TextareaType::class, [
                'required' => false,
                'empty_data' => '',
                'constraints' => [new Assert\NotBlank(message: 'Write the campaign content.'), new Assert\Length(min: 10)],
            ])
            ->add('status', EnumType::class, [
                'class' => NewsletterCampaignStatus::class,
                'choice_filter' => static fn (?NewsletterCampaignStatus $status): bool => in_array($status, [NewsletterCampaignStatus::Draft, NewsletterCampaignStatus::Scheduled], true),
                'choice_label' => static fn (NewsletterCampaignStatus $status): string => ucfirst($status->value),
                'attr' => ['id' => 'campaign-status'],
            ])
            ->add('scheduledAt', DateTimeType::class, [
                'required' => false,
                'widget' => 'single_text',
                'html5' => true,
                'attr' => ['id' => 'campaign-schedule'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => NewsletterCampaign::class]);
    }
}

<?php

declare(strict_types=1);

namespace App\Form;

use App\Service\Settings\SettingsCatalog;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<array<string, mixed>> */
final class SettingsFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach (SettingsCatalog::fieldsForGroup($options['group']) as $key => $field) {
            $name = SettingsCatalog::formName($key);
            $type = match ($field['type']) {
                'bool' => CheckboxType::class,
                'choice' => ChoiceType::class,
                'multiple' => ChoiceType::class,
                'int' => IntegerType::class,
                'textarea', 'json', 'lines' => TextareaType::class,
                'code' => TextareaType::class,
                'email' => EmailType::class,
                'url' => UrlType::class,
                'color' => ColorType::class,
                'password' => PasswordType::class,
                default => TextType::class,
            };
            $config = [
                'label' => $field['label'],
                'required' => false,
                'help' => $field['help'] ?? null,
                'constraints' => SettingsCatalog::constraints($key),
                'attr' => array_filter([
                    'id' => 'multiple' === $field['type'] ? null : 'settings-'.$name,
                    'rows' => in_array($field['type'], ['textarea', 'code'], true) ? ('code' === $field['type'] ? 8 : 3) : null,
                    'spellcheck' => 'code' === $field['type'] ? 'false' : null,
                    'autocomplete' => 'password' === $field['type'] ? 'new-password' : null,
                    'min' => SettingsCatalog::LIMITS[$key][0] ?? null,
                    'max' => SettingsCatalog::LIMITS[$key][1] ?? null,
                ]),
            ];
            if ('choice' === $field['type'] || 'multiple' === $field['type']) {
                $config['choices'] = array_flip($field['choices'] ?? []);
                $config['expanded'] = 'multiple' === $field['type'];
                $config['multiple'] = 'multiple' === $field['type'];
            }
            if ('password' === $field['type']) {
                $config['always_empty'] = true;
            }
            if (!in_array($field['type'], ['bool', 'int', 'multiple'], true)) {
                $config['empty_data'] = '';
            }
            $builder->add($name, $type, $config);

            if ('order' === $field['type']) {
                $builder->get($name)->addModelTransformer(new CallbackTransformer(
                    static fn (mixed $value): string => is_array($value) ? implode(', ', $value) : (string) $value,
                    static fn (mixed $value): array => array_values(array_filter(array_map(trim(...), explode(',', (string) $value)))),
                ));
            }
            if ('lines' === $field['type']) {
                $builder->get($name)->addModelTransformer(new CallbackTransformer(
                    static fn (mixed $value): string => is_array($value) ? implode("\n", $value) : '',
                    static fn (mixed $value): array => array_values(array_filter(array_map(trim(...), explode("\n", (string) $value)), static fn (string $line): bool => '' !== $line)),
                ));
            }
            if ('json' === $field['type']) {
                $builder->get($name)->addModelTransformer(new CallbackTransformer(
                    static fn (mixed $value): string => json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    static function (mixed $value): array {
                        try {
                            $decoded = json_decode('' === $value ? '[]' : (string) $value, true, flags: JSON_THROW_ON_ERROR);
                        } catch (\JsonException $exception) {
                            throw new TransformationFailedException('Invalid JSON.', previous: $exception);
                        }
                        if (!is_array($decoded) || !array_is_list($decoded)) {
                            throw new TransformationFailedException('Expected a JSON list.');
                        }

                        return $decoded;
                    },
                ));
            }
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('group')->setAllowedValues('group', array_keys(SettingsCatalog::GROUPS));
        $resolver->setDefaults(['data_class' => null, 'csrf_protection' => true]);
    }
}

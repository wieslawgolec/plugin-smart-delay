<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSmartDelayBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Optional configuration form for the Smart Delay campaign action.
 * Allows operators to set a fallback hour and minimum interaction threshold.
 */
class SmartDelayType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('fallback_hour', ChoiceType::class, [
            'label'   => 'mautic.smartdelay.form.fallback_hour',
            'choices' => array_combine(
                array_map(static fn (int $h): string => sprintf('%02d:00', $h), range(0, 23)),
                range(0, 23)
            ),
            'data'        => 9,
            'required'    => false,
            'attr'        => ['class' => 'form-control'],
            'placeholder' => false,
        ]);

        $builder->add('min_interactions', IntegerType::class, [
            'label'      => 'mautic.smartdelay.form.min_interactions',
            'data'       => 3,
            'required'   => false,
            'attr'       => [
                'class' => 'form-control',
                'min'   => 1,
                'max'   => 100,
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'label' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'smartdelay';
    }
}

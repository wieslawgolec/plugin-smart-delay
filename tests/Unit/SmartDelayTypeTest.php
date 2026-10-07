<?php

declare(strict_types=1);

namespace MauticPlugin\MauticSmartDelayBundle\Tests\Unit;

use MauticPlugin\MauticSmartDelayBundle\Form\Type\SmartDelayType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SmartDelayTypeTest extends TestCase
{
    public function testBuildFormAddsExpectedFields(): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);

        $calls = [];
        $builder->expects($this->exactly(2))
            ->method('add')
            ->willReturnCallback(function (string $name, string $type, array $options = []) use (&$calls, $builder) {
                $calls[] = ['name' => $name, 'type' => $type, 'options' => $options];

                return $builder;
            });

        $formType = new SmartDelayType();
        $formType->buildForm($builder, []);

        $this->assertSame('fallback_hour', $calls[0]['name']);
        $this->assertSame(ChoiceType::class, $calls[0]['type']);
        $this->assertSame(9, $calls[0]['options']['data']);

        $this->assertSame('min_interactions', $calls[1]['name']);
        $this->assertSame(IntegerType::class, $calls[1]['type']);
        $this->assertSame(3, $calls[1]['options']['data']);
    }

    public function testGetBlockPrefix(): void
    {
        $formType = new SmartDelayType();
        $this->assertSame('smartdelay', $formType->getBlockPrefix());
    }

    public function testConfigureOptions(): void
    {
        $resolver = new OptionsResolver();
        $formType = new SmartDelayType();
        $formType->configureOptions($resolver);

        $options = $resolver->resolve([]);
        $this->assertFalse($options['label']);
    }
}

<?php

declare(strict_types=1);

namespace Elcuro\SyliusPaymentFeePlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class CalculatorChoiceType extends AbstractType
{
    /** @param array<string, string> $calculators */
    public function __construct(
        private array $calculators,
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'choices' => array_flip($this->calculators),
        ]);
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'sylius_payment_calculator_choice';
    }
}

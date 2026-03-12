<?php

declare(strict_types=1);

namespace Elcuro\SyliusPaymentFeePlugin\Form\Type\Calculator;

use Sylius\Bundle\MoneyBundle\Form\Type\MoneyType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

final class CountryRateConfigurationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currency', ChoiceType::class, [
                'label' => 'elcuro_sylius_payment_fee.form.calculator.country_rate.currency',
                'choices' => $options['currency_choices'],
            ])
            ->add('amount', MoneyType::class, [
                'label' => 'elcuro_sylius_payment_fee.form.calculator.country_rate.amount',
                'constraints' => [
                    new NotBlank(['groups' => ['sylius']]),
                    new Type(type: 'integer', groups: ['sylius']),
                ],
                'currency' => false,
                'empty_data' => '0',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);

        $resolver
            ->setRequired('currency_choices')
            ->setAllowedTypes('currency_choices', 'array')
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'elcuro_payment_calculator_country_rate';
    }
}

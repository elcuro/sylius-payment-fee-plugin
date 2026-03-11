<?php

declare(strict_types=1);

namespace Elcuro\SyliusPaymentFeePlugin\Form\Extension;

use Elcuro\SyliusPaymentFeePlugin\Calculator\CalculatorInterface;
use Elcuro\SyliusPaymentFeePlugin\Model\PaymentMethodWithFeeInterface;
use Sylius\Bundle\PaymentBundle\Form\Type\PaymentMethodChoiceType;
use Sylius\Component\Registry\ServiceRegistryInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

final class PaymentMethodChoiceTypeExtension extends AbstractTypeExtension
{
    public function __construct(
        private ServiceRegistryInterface $calculatorRegistry,
    ) {
    }

    public static function getExtendedTypes(): iterable
    {
        return [PaymentMethodChoiceType::class];
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        if (!isset($options['subject'])) {
            return;
        }

        /** @var \Sylius\Component\Payment\Model\PaymentInterface $subject */
        $subject = $options['subject'];
        $paymentCosts = [];

        foreach ($view->vars['choices'] as $choiceView) {
            $method = $choiceView->data;

            if (!$method instanceof PaymentMethodWithFeeInterface) {
                $paymentCosts[$choiceView->value] = 0;

                continue;
            }

            if ($method->getCalculator() === null) {
                $paymentCosts[$choiceView->value] = 0;

                continue;
            }

            /** @var CalculatorInterface $calculator */
            $calculator = $this->calculatorRegistry->get($method->getCalculator());
            $paymentCosts[$choiceView->value] = $calculator->calculate($subject, $method->getCalculatorConfiguration());
        }

        $view->vars['payment_costs'] = $paymentCosts;
    }
}

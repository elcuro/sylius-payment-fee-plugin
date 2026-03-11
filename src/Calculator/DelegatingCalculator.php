<?php

declare(strict_types=1);

namespace Elcuro\SyliusPaymentFeePlugin\Calculator;

use Elcuro\SyliusPaymentFeePlugin\Model\PaymentMethodWithFeeInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Registry\ServiceRegistryInterface;

final class DelegatingCalculator implements DelegatingCalculatorInterface
{
    public function __construct(
        private ServiceRegistryInterface $registry,
    ) {
    }

    public function calculate(PaymentInterface $subject): ?int
    {
        $method = $subject->getMethod();
        if ($method === null) {
            throw new UndefinedPaymentMethodException('Cannot calculate charge for payment without a defined payment method.');
        }

        if (!$method instanceof PaymentMethodWithFeeInterface) {
            return 0;
        }

        if ($method->getCalculator() === null) {
            return 0;
        }

        /** @var CalculatorInterface $calculator */
        $calculator = $this->registry->get($method->getCalculator());

        return $calculator->calculate($subject, $method->getCalculatorConfiguration());
    }
}

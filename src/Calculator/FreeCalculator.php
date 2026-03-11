<?php

declare(strict_types=1);

namespace Elcuro\SyliusPaymentFeePlugin\Calculator;

use Sylius\Component\Payment\Model\PaymentInterface;

final class FreeCalculator implements CalculatorInterface
{
    public function calculate(PaymentInterface $subject, array $configuration): ?int
    {
        return null;
    }

    public function getType(): string
    {
        return 'free';
    }
}

<?php

declare(strict_types=1);

namespace Elcuro\SyliusPaymentFeePlugin\Calculator;

use Sylius\Component\Payment\Model\PaymentInterface;

interface CalculatorInterface
{
    /** @param array<string, mixed> $configuration */
    public function calculate(PaymentInterface $subject, array $configuration): ?int;

    public function getType(): string;
}

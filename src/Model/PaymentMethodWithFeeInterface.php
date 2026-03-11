<?php

declare(strict_types=1);

namespace Elcuro\SyliusPaymentFeePlugin\Model;

use Sylius\Component\Payment\Model\PaymentMethodInterface;
use Sylius\Component\Taxation\Model\TaxableInterface;
use Sylius\Component\Taxation\Model\TaxCategoryInterface;

interface PaymentMethodWithFeeInterface extends PaymentMethodInterface, TaxableInterface
{
    public function getCalculator(): ?string;

    public function setCalculator(?string $calculator): void;

    /** @return array<string, mixed> */
    public function getCalculatorConfiguration(): array;

    /** @param array<string, mixed> $calculatorConfiguration */
    public function setCalculatorConfiguration(array $calculatorConfiguration): void;

    public function setTaxCategory(?TaxCategoryInterface $category): void;
}

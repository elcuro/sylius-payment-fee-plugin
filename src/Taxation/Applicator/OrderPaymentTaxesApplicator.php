<?php

declare(strict_types=1);

namespace Elcuro\SyliusPaymentFeePlugin\Taxation\Applicator;

use Elcuro\SyliusPaymentFeePlugin\Model\AdjustmentInterface;
use Elcuro\SyliusPaymentFeePlugin\Model\PaymentMethodWithFeeInterface;
use Sylius\Component\Addressing\Model\ZoneInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Taxation\Applicator\OrderTaxesApplicatorInterface;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Sylius\Component\Taxation\Calculator\CalculatorInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;

final class OrderPaymentTaxesApplicator implements OrderTaxesApplicatorInterface
{
    /** @param AdjustmentFactoryInterface<\Sylius\Component\Order\Model\AdjustmentInterface> $adjustmentFactory */
    public function __construct(
        private CalculatorInterface $calculator,
        private AdjustmentFactoryInterface $adjustmentFactory,
        private TaxRateResolverInterface $taxRateResolver,
    ) {
    }

    public function apply(OrderInterface $order, ZoneInterface $zone): void
    {
        $paymentTotal = $this->getPaymentFeeTotal($order);

        if (0 === $paymentTotal) {
            return;
        }

        $paymentMethod = $this->getPaymentMethod($order);
        if ($paymentMethod === null) {
            return;
        }

        $taxRate = $this->taxRateResolver->resolve($paymentMethod, ['zone' => $zone]);
        if (null === $taxRate) {
            return;
        }

        $taxAmount = $this->calculator->calculate($paymentTotal, $taxRate);
        if (0.00 === $taxAmount) {
            return;
        }

        $order->addAdjustment($this->adjustmentFactory->createWithData(
            AdjustmentInterface::TAX_ADJUSTMENT,
            $taxRate->getLabel() ?? 'Payment fee tax',
            (int) $taxAmount,
            $taxRate->isIncludedInPrice(),
            [
                'paymentMethodCode' => $paymentMethod->getCode(),
                'paymentMethodName' => $paymentMethod->getName(),
                'taxRateCode' => $taxRate->getCode(),
                'taxRateName' => $taxRate->getName(),
                'taxRateAmount' => $taxRate->getAmount(),
            ],
        ));
    }

    private function getPaymentFeeTotal(OrderInterface $order): int
    {
        return $order->getAdjustmentsTotal(AdjustmentInterface::PAYMENT_ADJUSTMENT);
    }

    private function getPaymentMethod(OrderInterface $order): ?PaymentMethodWithFeeInterface
    {
        $payment = $order->getPayments()->first();
        if (false === $payment) {
            return null;
        }

        $method = $payment->getMethod();
        if (!$method instanceof PaymentMethodWithFeeInterface) {
            return null;
        }

        return $method;
    }
}

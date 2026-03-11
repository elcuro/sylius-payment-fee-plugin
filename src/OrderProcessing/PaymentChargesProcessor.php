<?php

declare(strict_types=1);

namespace Elcuro\SyliusPaymentFeePlugin\OrderProcessing;

use Elcuro\SyliusPaymentFeePlugin\Calculator\DelegatingCalculatorInterface;
use Elcuro\SyliusPaymentFeePlugin\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Webmozart\Assert\Assert;

final class PaymentChargesProcessor implements OrderProcessorInterface
{
    /** @param FactoryInterface<AdjustmentInterface> $adjustmentFactory */
    public function __construct(
        private FactoryInterface $adjustmentFactory,
        private DelegatingCalculatorInterface $calculator,
    ) {
    }

    public function process(BaseOrderInterface $order): void
    {
        Assert::isInstanceOf($order, OrderInterface::class);

        if (!$order->canBeProcessed()) {
            return;
        }

        $order->removeAdjustments(AdjustmentInterface::PAYMENT_ADJUSTMENT);

        foreach ($order->getPayments() as $payment) {
            $paymentCharge = $this->calculator->calculate($payment);

            if ($paymentCharge === null || $paymentCharge === 0) {
                continue;
            }

            /** @var \Sylius\Component\Order\Model\AdjustmentInterface $adjustment */
            $adjustment = $this->adjustmentFactory->createNew();
            $adjustment->setType(AdjustmentInterface::PAYMENT_ADJUSTMENT);
            $adjustment->setAmount($paymentCharge);
            $adjustment->setLabel($payment->getMethod()?->getName());
            $adjustment->setNeutral(false);
            $adjustment->setDetails([
                'paymentMethodCode' => $payment->getMethod()?->getCode(),
                'paymentMethodName' => $payment->getMethod()?->getName(),
            ]);

            $order->addAdjustment($adjustment);
        }
    }
}

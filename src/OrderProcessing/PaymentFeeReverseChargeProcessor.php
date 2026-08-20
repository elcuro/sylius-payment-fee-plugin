<?php

declare(strict_types=1);

namespace Elcuro\SyliusPaymentFeePlugin\OrderProcessing;

use Elcuro\SyliusPaymentFeePlugin\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Order\Model\AdjustmentInterface as BaseAdjustmentInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;

/**
 * Removes VAT from the payment fee when the order is reverse charged.
 *
 * The fee tax has no adjustable of its own — OrderPaymentTaxesApplicator puts it straight on the order —
 * so a reverse-charge processor that walks item units and shipments never reaches it and the fee VAT
 * survives in Order::getTaxTotal(). Cancelling it with a negative order-level TAX adjustment is no fix
 * either: Order::getShippingTotal() adds every non-neutral order-level TAX adjustment to the shipping
 * line, which would move the deduction onto the shipping row. So the fee's tax adjustments are dropped
 * outright and the tax they carried is taken off the fee itself.
 *
 * Reverse charge is recognised by the `reverseCharge` detail flag that the VAT plugin writes onto the tax
 * adjustments it cancels (the same convention elcuro/sylius-superfaktura-plugin reads). Without such a
 * plugin no adjustment ever carries the flag and this processor stays inert.
 */
final class PaymentFeeReverseChargeProcessor implements OrderProcessorInterface
{
    public function process(BaseOrderInterface $order): void
    {
        if (!$order instanceof OrderInterface) {
            return;
        }

        if (!$order->canBeProcessed()) {
            return;
        }

        $taxAdjustments = $this->getPaymentFeeTaxAdjustments($order);
        if ([] === $taxAdjustments || !$this->isReverseCharged($order)) {
            return;
        }

        $includedTax = 0;
        foreach ($taxAdjustments as $taxAdjustment) {
            if ($taxAdjustment->isNeutral()) {
                $includedTax += $taxAdjustment->getAmount();
            }

            $order->removeAdjustment($taxAdjustment);
        }

        $this->deductFromFee($order, $includedTax);
    }

    /** @return list<BaseAdjustmentInterface> */
    private function getPaymentFeeTaxAdjustments(OrderInterface $order): array
    {
        $taxAdjustments = [];

        foreach ($order->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT) as $taxAdjustment) {
            if (isset($taxAdjustment->getDetails()['paymentMethodCode'])) {
                $taxAdjustments[] = $taxAdjustment;
            }
        }

        return $taxAdjustments;
    }

    private function isReverseCharged(OrderInterface $order): bool
    {
        // Shipment adjustments are attached to the order as well, so this covers items and shipping alike.
        foreach ($order->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT) as $taxAdjustment) {
            if ($this->isReverseChargeAdjustment($taxAdjustment)) {
                return true;
            }
        }

        foreach ($order->getItems() as $item) {
            foreach ($item->getUnits() as $unit) {
                foreach ($unit->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT) as $taxAdjustment) {
                    if ($this->isReverseChargeAdjustment($taxAdjustment)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function isReverseChargeAdjustment(BaseAdjustmentInterface $adjustment): bool
    {
        return true === ($adjustment->getDetails()['reverseCharge'] ?? false);
    }

    private function deductFromFee(OrderInterface $order, int $includedTax): void
    {
        if (0 === $includedTax) {
            return;
        }

        /** @var list<BaseAdjustmentInterface> $feeAdjustments */
        $feeAdjustments = $order->getAdjustments(AdjustmentInterface::PAYMENT_ADJUSTMENT)->getValues();

        $feeTotal = 0;
        foreach ($feeAdjustments as $feeAdjustment) {
            $feeTotal += $feeAdjustment->getAmount();
        }

        if (0 === $feeTotal) {
            return;
        }

        $lastIndex = array_key_last($feeAdjustments);
        $remainingTax = $includedTax;

        foreach ($feeAdjustments as $index => $feeAdjustment) {
            $share = $index === $lastIndex
                ? $remainingTax
                : intdiv($includedTax * $feeAdjustment->getAmount(), $feeTotal);

            $feeAdjustment->setAmount($feeAdjustment->getAmount() - $share);
            $remainingTax -= $share;
        }
    }
}

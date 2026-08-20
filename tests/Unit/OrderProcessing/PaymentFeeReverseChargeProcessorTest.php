<?php

declare(strict_types=1);

namespace Tests\Elcuro\SyliusPaymentFeePlugin\Unit\OrderProcessing;

use Elcuro\SyliusPaymentFeePlugin\Model\AdjustmentInterface;
use Elcuro\SyliusPaymentFeePlugin\OrderProcessing\PaymentFeeReverseChargeProcessor;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Adjustment;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\OrderItem;
use Sylius\Component\Core\Model\OrderItemUnit;
use Sylius\Component\Core\Model\Shipment;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;

final class PaymentFeeReverseChargeProcessorTest extends TestCase
{
    private PaymentFeeReverseChargeProcessor $processor;

    protected function setUp(): void
    {
        $this->processor = new PaymentFeeReverseChargeProcessor();
    }

    public function testItDropsTheFeeTaxAndNetsTheFeeWhenTheOrderIsReverseCharged(): void
    {
        $order = $this->createOrder(reverseCharged: true);

        $this->processor->process($order);

        self::assertSame(4132, $order->getAdjustmentsTotal(AdjustmentInterface::PAYMENT_ADJUSTMENT));
        self::assertSame(0, $order->getTaxTotal());
    }

    public function testItKeepsTheShippingTotalOutOfTheDeduction(): void
    {
        $order = $this->createOrder(reverseCharged: true);
        $shippingTotalBefore = $order->getShippingTotal();

        $this->processor->process($order);

        self::assertSame($shippingTotalBefore, $order->getShippingTotal());
    }

    public function testItLowersTheOrderTotalByTheFeeTax(): void
    {
        $order = $this->createOrder(reverseCharged: true);
        $totalBefore = $order->getTotal();

        $this->processor->process($order);

        self::assertSame($totalBefore - 868, $order->getTotal());
    }

    public function testItLeavesTheFeeAloneWhenTheOrderIsNotReverseCharged(): void
    {
        $order = $this->createOrder(reverseCharged: false);

        $this->processor->process($order);

        self::assertSame(5000, $order->getAdjustmentsTotal(AdjustmentInterface::PAYMENT_ADJUSTMENT));
        self::assertSame(868, $this->feeTaxTotal($order));
    }

    public function testItDetectsReverseChargeFromItemUnitsAlone(): void
    {
        $order = $this->createOrder(reverseCharged: false);

        $item = new OrderItem();
        $item->setUnitPrice(10000);
        $order->addItem($item);
        $unit = new OrderItemUnit($item);
        $unit->addAdjustment($this->createAdjustment(AdjustmentInterface::TAX_ADJUSTMENT, 'VAT 21%', 2100, true));
        $unit->addAdjustment($this->createAdjustment(
            AdjustmentInterface::TAX_ADJUSTMENT,
            'VAT 21%',
            -2100,
            false,
            ['reverseCharge' => true],
        ));

        $this->processor->process($order);

        self::assertSame(4132, $order->getAdjustmentsTotal(AdjustmentInterface::PAYMENT_ADJUSTMENT));
        self::assertSame(0, $this->feeTaxTotal($order));
    }

    public function testItDropsAnExcludedInPriceFeeTaxWithoutTouchingTheFee(): void
    {
        $order = $this->createOrder(reverseCharged: true, feeTaxIncludedInPrice: false);

        $this->processor->process($order);

        // The fee was already net, the tax was on top of it — dropping the adjustment is the whole deduction.
        self::assertSame(5000, $order->getAdjustmentsTotal(AdjustmentInterface::PAYMENT_ADJUSTMENT));
        self::assertSame(0, $order->getTaxTotal());
    }

    public function testItIsIdempotent(): void
    {
        $order = $this->createOrder(reverseCharged: true);

        $this->processor->process($order);
        $totalAfterFirstRun = $order->getTotal();
        $this->processor->process($order);

        self::assertSame($totalAfterFirstRun, $order->getTotal());
        self::assertSame(4132, $order->getAdjustmentsTotal(AdjustmentInterface::PAYMENT_ADJUSTMENT));
    }

    public function testItSplitsTheDeductionOverSeveralFeeAdjustments(): void
    {
        $order = $this->createOrder(reverseCharged: true);
        $order->addAdjustment($this->createAdjustment(AdjustmentInterface::PAYMENT_ADJUSTMENT, 'Dobírka', 5000, false));

        $this->processor->process($order);

        self::assertSame(9132, $order->getAdjustmentsTotal(AdjustmentInterface::PAYMENT_ADJUSTMENT));
    }

    public function testItDoesNothingWhenTheOrderCannotBeProcessed(): void
    {
        $order = $this->createOrder(reverseCharged: true);
        $order->setState(BaseOrderInterface::STATE_FULFILLED);

        $this->processor->process($order);

        self::assertSame(5000, $order->getAdjustmentsTotal(AdjustmentInterface::PAYMENT_ADJUSTMENT));
        self::assertSame(868, $this->feeTaxTotal($order));
    }

    public function testItIgnoresOrdersOutsideTheCoreModel(): void
    {
        $order = $this->createMock(BaseOrderInterface::class);
        $order->expects(self::never())->method('getAdjustments');

        $this->processor->process($order);
    }

    /** Mirrors a reverse-charged checkout: 50.00 fee with 8.68 VAT included, plus a taxed shipment. */
    private function createOrder(bool $reverseCharged, bool $feeTaxIncludedInPrice = true): Order
    {
        $order = new Order();

        $order->addAdjustment($this->createAdjustment(AdjustmentInterface::SHIPPING_ADJUSTMENT, 'Kurýr', 14800, false));
        $order->addAdjustment($this->createAdjustment(AdjustmentInterface::PAYMENT_ADJUSTMENT, 'Dobírka', 5000, false));
        $order->addAdjustment($this->createAdjustment(
            AdjustmentInterface::TAX_ADJUSTMENT,
            'VAT 21%',
            $feeTaxIncludedInPrice ? 868 : 1050,
            $feeTaxIncludedInPrice,
            ['paymentMethodCode' => 'cash_on_delivery', 'taxRateCode' => 'vat_21'],
        ));

        // Shipment adjustments end up in the order's collection too — that is where the reverse-charge
        // marker this processor looks for comes from.
        $shipment = new Shipment();
        $order->addShipment($shipment);
        $shipment->addAdjustment($this->createAdjustment(AdjustmentInterface::TAX_ADJUSTMENT, 'VAT 21%', 2569, true));

        if ($reverseCharged) {
            $shipment->addAdjustment($this->createAdjustment(
                AdjustmentInterface::TAX_ADJUSTMENT,
                'VAT 21%',
                -2569,
                false,
                ['reverseCharge' => true],
            ));
        }

        return $order;
    }

    private function feeTaxTotal(Order $order): int
    {
        $total = 0;

        foreach ($order->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT) as $adjustment) {
            if (isset($adjustment->getDetails()['paymentMethodCode'])) {
                $total += $adjustment->getAmount();
            }
        }

        return $total;
    }

    /** @param array<string, mixed> $details */
    private function createAdjustment(string $type, string $label, int $amount, bool $neutral, array $details = []): Adjustment
    {
        $adjustment = new Adjustment();
        $adjustment->setType($type);
        $adjustment->setLabel($label);
        $adjustment->setAmount($amount);
        $adjustment->setNeutral($neutral);
        $adjustment->setDetails($details);

        return $adjustment;
    }
}

<?php

declare(strict_types=1);

namespace Tests\Elcuro\SyliusPaymentFeePlugin\Unit\OrderProcessing;

use Doctrine\Common\Collections\ArrayCollection;
use Elcuro\SyliusPaymentFeePlugin\Calculator\DelegatingCalculatorInterface;
use Elcuro\SyliusPaymentFeePlugin\Model\AdjustmentInterface;
use Elcuro\SyliusPaymentFeePlugin\OrderProcessing\PaymentChargesProcessor;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Order\Model\AdjustmentInterface as BaseAdjustmentInterface;
use Sylius\Resource\Factory\FactoryInterface;

final class PaymentChargesProcessorTest extends TestCase
{
    private FactoryInterface&MockObject $adjustmentFactory;

    private DelegatingCalculatorInterface&MockObject $calculator;

    private PaymentChargesProcessor $processor;

    protected function setUp(): void
    {
        $this->adjustmentFactory = $this->createMock(FactoryInterface::class);
        $this->calculator = $this->createMock(DelegatingCalculatorInterface::class);

        $this->processor = new PaymentChargesProcessor(
            $this->adjustmentFactory,
            $this->calculator,
        );
    }

    public function testItAddsPaymentFeeToOrderTotal(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getName')->willReturn('Cash on Delivery');
        $paymentMethod->method('getCode')->willReturn('cash_on_delivery');

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getMethod')->willReturn($paymentMethod);

        $order = $this->createMock(OrderInterface::class);
        $order->method('canBeProcessed')->willReturn(true);
        $order->method('getPayments')->willReturn(new ArrayCollection([$payment]));

        $adjustment = $this->createMock(BaseAdjustmentInterface::class);

        $this->calculator
            ->method('calculate')
            ->with($payment)
            ->willReturn(250);

        $this->adjustmentFactory
            ->method('createNew')
            ->willReturn($adjustment);

        $adjustment->expects($this->once())->method('setType')->with(AdjustmentInterface::PAYMENT_ADJUSTMENT);
        $adjustment->expects($this->once())->method('setAmount')->with(250);
        $adjustment->expects($this->once())->method('setNeutral')->with(false);
        $adjustment->expects($this->once())->method('setLabel')->with('Cash on Delivery');
        $adjustment->expects($this->once())->method('setDetails')->with([
            'paymentMethodCode' => 'cash_on_delivery',
            'paymentMethodName' => 'Cash on Delivery',
        ]);

        $order->expects($this->once())->method('removeAdjustments')->with(AdjustmentInterface::PAYMENT_ADJUSTMENT);
        $order->expects($this->once())->method('addAdjustment')->with($adjustment);

        $this->processor->process($order);
    }

    public function testItDoesNotAddAdjustmentWhenFeeIsZero(): void
    {
        $payment = $this->createMock(PaymentInterface::class);

        $order = $this->createMock(OrderInterface::class);
        $order->method('canBeProcessed')->willReturn(true);
        $order->method('getPayments')->willReturn(new ArrayCollection([$payment]));

        $this->calculator
            ->method('calculate')
            ->with($payment)
            ->willReturn(0);

        $order->expects($this->once())->method('removeAdjustments')->with(AdjustmentInterface::PAYMENT_ADJUSTMENT);
        $order->expects($this->never())->method('addAdjustment');

        $this->processor->process($order);
    }

    public function testItDoesNotAddAdjustmentWhenFeeIsNull(): void
    {
        $payment = $this->createMock(PaymentInterface::class);

        $order = $this->createMock(OrderInterface::class);
        $order->method('canBeProcessed')->willReturn(true);
        $order->method('getPayments')->willReturn(new ArrayCollection([$payment]));

        $this->calculator
            ->method('calculate')
            ->with($payment)
            ->willReturn(null);

        $order->expects($this->once())->method('removeAdjustments')->with(AdjustmentInterface::PAYMENT_ADJUSTMENT);
        $order->expects($this->never())->method('addAdjustment');

        $this->processor->process($order);
    }

    public function testItSkipsProcessingWhenOrderCannotBeProcessed(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('canBeProcessed')->willReturn(false);

        $order->expects($this->never())->method('removeAdjustments');
        $order->expects($this->never())->method('addAdjustment');

        $this->processor->process($order);
    }

    public function testItHandlesMultiplePayments(): void
    {
        $method1 = $this->createMock(PaymentMethodInterface::class);
        $method1->method('getName')->willReturn('Cash on Delivery');
        $method1->method('getCode')->willReturn('cod');

        $method2 = $this->createMock(PaymentMethodInterface::class);
        $method2->method('getName')->willReturn('Bank Transfer');
        $method2->method('getCode')->willReturn('bank');

        $payment1 = $this->createMock(PaymentInterface::class);
        $payment1->method('getMethod')->willReturn($method1);

        $payment2 = $this->createMock(PaymentInterface::class);
        $payment2->method('getMethod')->willReturn($method2);

        $order = $this->createMock(OrderInterface::class);
        $order->method('canBeProcessed')->willReturn(true);
        $order->method('getPayments')->willReturn(new ArrayCollection([$payment1, $payment2]));

        $adjustment1 = $this->createMock(BaseAdjustmentInterface::class);
        $adjustment2 = $this->createMock(BaseAdjustmentInterface::class);

        $this->calculator
            ->method('calculate')
            ->willReturnMap([
                [$payment1, 250],
                [$payment2, 100],
            ]);

        $this->adjustmentFactory
            ->method('createNew')
            ->willReturnOnConsecutiveCalls($adjustment1, $adjustment2);

        $adjustment1->expects($this->once())->method('setAmount')->with(250);
        $adjustment2->expects($this->once())->method('setAmount')->with(100);

        $order->expects($this->exactly(2))->method('addAdjustment');

        $this->processor->process($order);
    }
}

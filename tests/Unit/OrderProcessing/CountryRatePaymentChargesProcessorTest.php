<?php

declare(strict_types=1);

namespace Tests\Elcuro\SyliusPaymentFeePlugin\Unit\OrderProcessing;

use Doctrine\Common\Collections\ArrayCollection;
use Elcuro\SyliusPaymentFeePlugin\Calculator\CountryRateCalculator;
use Elcuro\SyliusPaymentFeePlugin\Calculator\DelegatingCalculator;
use Elcuro\SyliusPaymentFeePlugin\Model\AdjustmentInterface;
use Elcuro\SyliusPaymentFeePlugin\Model\PaymentMethodWithFeeInterface;
use Elcuro\SyliusPaymentFeePlugin\OrderProcessing\PaymentChargesProcessor;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Currency\Converter\CurrencyConverterInterface;
use Sylius\Component\Order\Model\AdjustmentInterface as BaseAdjustmentInterface;
use Sylius\Component\Registry\ServiceRegistry;
use Sylius\Resource\Factory\FactoryInterface;

final class CountryRatePaymentChargesProcessorTest extends TestCase
{
    private FactoryInterface&MockObject $adjustmentFactory;

    private CurrencyConverterInterface&MockObject $currencyConverter;

    private PaymentChargesProcessor $processor;

    protected function setUp(): void
    {
        $this->adjustmentFactory = $this->createMock(FactoryInterface::class);
        $this->currencyConverter = $this->createMock(CurrencyConverterInterface::class);

        $countryRateCalculator = new CountryRateCalculator($this->currencyConverter);

        $registry = new ServiceRegistry(
            'Elcuro\SyliusPaymentFeePlugin\Calculator\CalculatorInterface',
            'payment fee calculator',
        );
        $registry->register('country_rate', $countryRateCalculator);

        $delegatingCalculator = new DelegatingCalculator($registry);

        $this->processor = new PaymentChargesProcessor(
            $this->adjustmentFactory,
            $delegatingCalculator,
        );
    }

    public function testItAddsCountryRateFeeForConfiguredCountry(): void
    {
        $order = $this->createOrder('SK', 'EUR');
        $payment = $this->createPaymentWithCountryRateMethod([
            'SK' => ['amount' => 150, 'currency' => 'EUR'],
            'CZ' => ['amount' => 200, 'currency' => 'CZK'],
        ], $order);

        $order->method('getPayments')->willReturn(new ArrayCollection([$payment]));

        $adjustment = $this->createMock(BaseAdjustmentInterface::class);
        $this->adjustmentFactory->method('createNew')->willReturn($adjustment);

        $adjustment->expects($this->once())->method('setType')->with(AdjustmentInterface::PAYMENT_ADJUSTMENT);
        $adjustment->expects($this->once())->method('setAmount')->with(150);
        $order->expects($this->once())->method('addAdjustment')->with($adjustment);

        $this->processor->process($order);
    }

    public function testItDoesNotAddFeeWhenCountryNotConfigured(): void
    {
        $order = $this->createOrder('DE', 'EUR');
        $payment = $this->createPaymentWithCountryRateMethod([
            'SK' => ['amount' => 150, 'currency' => 'EUR'],
        ], $order);

        $order->method('getPayments')->willReturn(new ArrayCollection([$payment]));

        $order->expects($this->never())->method('addAdjustment');

        $this->processor->process($order);
    }

    public function testItDoesNotAddFeeWhenNoShippingAddress(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('canBeProcessed')->willReturn(true);
        $order->method('getShippingAddress')->willReturn(null);

        $payment = $this->createPaymentWithCountryRateMethod([
            'SK' => ['amount' => 150, 'currency' => 'EUR'],
        ], $order);

        $order->method('getPayments')->willReturn(new ArrayCollection([$payment]));

        $order->expects($this->never())->method('addAdjustment');

        $this->processor->process($order);
    }

    public function testItConvertsCurrencyWhenOrderCurrencyDiffers(): void
    {
        $order = $this->createOrder('SK', 'USD');
        $payment = $this->createPaymentWithCountryRateMethod([
            'SK' => ['amount' => 100, 'currency' => 'EUR'],
        ], $order);

        $order->method('getPayments')->willReturn(new ArrayCollection([$payment]));

        $this->currencyConverter
            ->method('convert')
            ->with(100, 'EUR', 'USD')
            ->willReturn(110);

        $adjustment = $this->createMock(BaseAdjustmentInterface::class);
        $this->adjustmentFactory->method('createNew')->willReturn($adjustment);

        $adjustment->expects($this->once())->method('setAmount')->with(110);
        $order->expects($this->once())->method('addAdjustment')->with($adjustment);

        $this->processor->process($order);
    }

    public function testItDoesNotAddFeeWhenNoExchangeRateExists(): void
    {
        $order = $this->createOrder('SK', 'USD');
        $payment = $this->createPaymentWithCountryRateMethod([
            'SK' => ['amount' => 100, 'currency' => 'EUR'],
        ], $order);

        $order->method('getPayments')->willReturn(new ArrayCollection([$payment]));

        $this->currencyConverter
            ->method('convert')
            ->with(100, 'EUR', 'USD')
            ->willReturn(100);

        $order->expects($this->never())->method('addAdjustment');

        $this->processor->process($order);
    }

    public function testItDoesNotAddFeeWhenAmountIsZero(): void
    {
        $order = $this->createOrder('SK', 'EUR');
        $payment = $this->createPaymentWithCountryRateMethod([
            'SK' => ['amount' => 0, 'currency' => 'EUR'],
        ], $order);

        $order->method('getPayments')->willReturn(new ArrayCollection([$payment]));

        $order->expects($this->never())->method('addAdjustment');

        $this->processor->process($order);
    }

    private function createOrder(string $countryCode, string $currencyCode): OrderInterface&MockObject
    {
        $address = $this->createMock(AddressInterface::class);
        $address->method('getCountryCode')->willReturn($countryCode);

        $order = $this->createMock(OrderInterface::class);
        $order->method('canBeProcessed')->willReturn(true);
        $order->method('getShippingAddress')->willReturn($address);
        $order->method('getCurrencyCode')->willReturn($currencyCode);

        return $order;
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function createPaymentWithCountryRateMethod(array $configuration, (OrderInterface&MockObject)|null $order = null): PaymentInterface&MockObject
    {
        $method = $this->createMock(CountryRatePaymentMethodInterface::class);
        $method->method('getCalculator')->willReturn('country_rate');
        $method->method('getCalculatorConfiguration')->willReturn($configuration);
        $method->method('getName')->willReturn('Cash on Delivery');
        $method->method('getCode')->willReturn('cod');

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getMethod')->willReturn($method);

        if (null !== $order) {
            $payment->method('getOrder')->willReturn($order);
        }

        return $payment;
    }
}

/** @internal */
interface CountryRatePaymentMethodInterface extends PaymentMethodInterface, PaymentMethodWithFeeInterface
{
}

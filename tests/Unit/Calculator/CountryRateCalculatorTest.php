<?php

declare(strict_types=1);

namespace Tests\Elcuro\SyliusPaymentFeePlugin\Unit\Calculator;

use Elcuro\SyliusPaymentFeePlugin\Calculator\CountryRateCalculator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Currency\Converter\CurrencyConverterInterface;

final class CountryRateCalculatorTest extends TestCase
{
    private CurrencyConverterInterface&MockObject $currencyConverter;

    private CountryRateCalculator $calculator;

    protected function setUp(): void
    {
        $this->currencyConverter = $this->createMock(CurrencyConverterInterface::class);
        $this->calculator = new CountryRateCalculator($this->currencyConverter);
    }

    public function testReturnsCorrectAmountWhenCountryConfiguredAndSameCurrency(): void
    {
        $payment = $this->createPaymentWithShippingAddress('SK', 'EUR');

        $configuration = [
            'SK' => ['amount' => 100, 'currency' => 'EUR'],
            'CZ' => ['amount' => 200, 'currency' => 'CZK'],
        ];

        $this->assertSame(100, $this->calculator->calculate($payment, $configuration));
    }

    public function testReturnsZeroWhenNoShippingAddress(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getShippingAddress')->willReturn(null);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);

        $configuration = [
            'SK' => ['amount' => 100, 'currency' => 'EUR'],
        ];

        $this->assertSame(0, $this->calculator->calculate($payment, $configuration));
    }

    public function testReturnsZeroWhenCountryNotInConfiguration(): void
    {
        $payment = $this->createPaymentWithShippingAddress('DE', 'EUR');

        $configuration = [
            'SK' => ['amount' => 100, 'currency' => 'EUR'],
        ];

        $this->assertSame(0, $this->calculator->calculate($payment, $configuration));
    }

    public function testConvertsCurrencyWhenCurrenciesDiffer(): void
    {
        $payment = $this->createPaymentWithShippingAddress('SK', 'USD');

        $configuration = [
            'SK' => ['amount' => 100, 'currency' => 'EUR'],
        ];

        $this->currencyConverter
            ->method('convert')
            ->with(100, 'EUR', 'USD')
            ->willReturn(110);

        $this->assertSame(110, $this->calculator->calculate($payment, $configuration));
    }

    public function testReturnsZeroWhenNoExchangeRate(): void
    {
        $payment = $this->createPaymentWithShippingAddress('SK', 'USD');

        $configuration = [
            'SK' => ['amount' => 100, 'currency' => 'EUR'],
        ];

        $this->currencyConverter
            ->method('convert')
            ->with(100, 'EUR', 'USD')
            ->willReturn(100);

        $this->assertSame(0, $this->calculator->calculate($payment, $configuration));
    }

    public function testReturnsCorrectType(): void
    {
        $this->assertSame('country_rate', $this->calculator->getType());
    }

    private function createPaymentWithShippingAddress(string $countryCode, string $currencyCode): PaymentInterface&MockObject
    {
        $address = $this->createMock(AddressInterface::class);
        $address->method('getCountryCode')->willReturn($countryCode);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getShippingAddress')->willReturn($address);
        $order->method('getCurrencyCode')->willReturn($currencyCode);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);

        return $payment;
    }
}

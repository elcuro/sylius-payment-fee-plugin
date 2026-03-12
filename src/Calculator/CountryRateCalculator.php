<?php

declare(strict_types=1);

namespace Elcuro\SyliusPaymentFeePlugin\Calculator;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Currency\Converter\CurrencyConverterInterface;
use Sylius\Component\Payment\Model\PaymentInterface as BasePaymentInterface;
use Webmozart\Assert\Assert;

final class CountryRateCalculator implements CalculatorInterface
{
    public function __construct(
        private readonly CurrencyConverterInterface $currencyConverter,
    ) {
    }

    public function calculate(BasePaymentInterface $subject, array $configuration): ?int
    {
        Assert::isInstanceOf($subject, PaymentInterface::class);
        $order = $subject->getOrder();
        Assert::isInstanceOf($order, OrderInterface::class);

        $shippingAddress = $order->getShippingAddress();
        if (null === $shippingAddress) {
            return 0;
        }

        $countryCode = $shippingAddress->getCountryCode();
        if (null === $countryCode || !isset($configuration[$countryCode])) {
            return 0;
        }

        /** @var array{amount: int|string, currency: string} $countryConfig */
        $countryConfig = $configuration[$countryCode];
        $amount = (int) $countryConfig['amount'];
        if (0 === $amount) {
            return 0;
        }

        $configCurrency = $countryConfig['currency'];
        $orderCurrency = $order->getCurrencyCode();
        Assert::notNull($orderCurrency);

        if ($configCurrency === $orderCurrency) {
            return $amount;
        }

        $converted = $this->currencyConverter->convert($amount, $configCurrency, $orderCurrency);

        if ($converted === $amount) {
            return 0;
        }

        return $converted;
    }

    public function getType(): string
    {
        return 'country_rate';
    }
}

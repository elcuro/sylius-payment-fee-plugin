<?php

declare(strict_types=1);

namespace Tests\Elcuro\SyliusPaymentFeePlugin\Behat\Context\Setup;

use Behat\Behat\Context\Context;
use Doctrine\Persistence\ObjectManager;
use Elcuro\SyliusPaymentFeePlugin\Model\PaymentMethodWithFeeInterface;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Component\Addressing\Converter\CountryNameConverterInterface;
use Webmozart\Assert\Assert;

final readonly class PaymentFeeContext implements Context
{
    public function __construct(
        private SharedStorageInterface $sharedStorage,
        private ObjectManager $paymentMethodManager,
        private CountryNameConverterInterface $countryNameConverter,
    ) {
    }

    /**
     * @Given this payment method has a country rate fee :amount for :countryName in :currencyCode currency
     */
    public function thisPaymentMethodHasACountryRateFeeForCountry(
        string $amount,
        string $countryName,
        string $currencyCode,
    ): void {
        /** @var PaymentMethodWithFeeInterface $paymentMethod */
        $paymentMethod = $this->sharedStorage->get('payment_method');
        Assert::isInstanceOf($paymentMethod, PaymentMethodWithFeeInterface::class);

        $countryCode = $this->countryNameConverter->convertToCode($countryName);
        $amountInMinorUnits = (int) round((float) preg_replace('/[^0-9.]/', '', $amount) * 100);

        $paymentMethod->setCalculator('country_rate');

        $configuration = $paymentMethod->getCalculatorConfiguration();
        $configuration[$countryCode] = [
            'amount' => $amountInMinorUnits,
            'currency' => $currencyCode,
        ];
        $paymentMethod->setCalculatorConfiguration($configuration);

        $this->paymentMethodManager->flush();
    }
}

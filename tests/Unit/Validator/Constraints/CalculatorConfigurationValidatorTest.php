<?php

declare(strict_types=1);

namespace Tests\Elcuro\SyliusPaymentFeePlugin\Unit\Validator\Constraints;

use Elcuro\SyliusPaymentFeePlugin\Calculator\CountryRateCalculator;
use Elcuro\SyliusPaymentFeePlugin\Calculator\FlatRateCalculator;
use Elcuro\SyliusPaymentFeePlugin\Calculator\FreeCalculator;
use Elcuro\SyliusPaymentFeePlugin\Validator\Constraints\CalculatorConfigurationValidator;
use Elcuro\SyliusPaymentFeePlugin\Validator\Constraints\RegisteredCalculatorValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Addressing\Model\Country;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Currency\Converter\CurrencyConverterInterface;
use Sylius\Component\Currency\Model\Currency;
use Sylius\Component\Registry\ServiceRegistry;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Tests\Elcuro\SyliusPaymentFeePlugin\Entity\PaymentMethod;

/** Both constraints of PaymentMethodWithFeeTrait, through a real validator reading the trait's attributes. */
final class CalculatorConfigurationValidatorTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $registry = new ServiceRegistry(\Elcuro\SyliusPaymentFeePlugin\Calculator\CalculatorInterface::class);
        $registry->register('free', new FreeCalculator());
        $registry->register('flat_rate', new FlatRateCalculator());
        $registry->register('country_rate', new CountryRateCalculator($this->createMock(CurrencyConverterInterface::class)));

        $channelRepository = $this->createMock(ChannelRepositoryInterface::class);
        $channelRepository->method('findAllWithBasicData')->willReturn([['code' => 'WEB'], ['code' => 'MOBILE']]);

        $country = new Country();
        $country->setCode('DE');
        $countryRepository = $this->createMock(RepositoryInterface::class);
        $countryRepository->method('findAll')->willReturn([$country]);

        $currency = new Currency();
        $currency->setCode('EUR');
        $currencyRepository = $this->createMock(RepositoryInterface::class);
        $currencyRepository->method('findAll')->willReturn([$currency]);

        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory([
                'elcuro_sylius_payment_fee.validator.registered_calculator' => new RegisteredCalculatorValidator($registry),
                'elcuro_sylius_payment_fee.validator.calculator_configuration' => new CalculatorConfigurationValidator(
                    $registry,
                    $channelRepository,
                    $countryRepository,
                    $currencyRepository,
                ),
            ]))
            ->getValidator()
        ;
    }

    /** @param array<string, mixed> $configuration */
    #[DataProvider('validConfigurations')]
    public function testAcceptsValidConfiguration(?string $calculator, array $configuration): void
    {
        self::assertSame([], $this->violations($calculator, $configuration));
    }

    /** @return iterable<string, array{?string, array<string, mixed>}> */
    public static function validConfigurations(): iterable
    {
        yield 'no calculator' => [null, []];
        yield 'free ignores the configuration' => ['free', ['anything' => 'goes']];
        yield 'flat rate for every channel' => ['flat_rate', ['WEB' => ['amount' => 150], 'MOBILE' => ['amount' => 0]]];
        yield 'country rate for some countries' => ['country_rate', ['DE' => ['currency' => 'EUR', 'amount' => 200]]];
        yield 'country rate for no country' => ['country_rate', []];
    }

    /**
     * @param array<string, mixed> $configuration
     * @param array<string, string> $expected property path => message key
     */
    #[DataProvider('invalidConfigurations')]
    public function testRefusesInvalidConfiguration(?string $calculator, array $configuration, array $expected): void
    {
        self::assertSame($expected, $this->violations($calculator, $configuration));
    }

    /** @return iterable<string, array{?string, array<string, mixed>, array<string, string>}> */
    public static function invalidConfigurations(): iterable
    {
        $prefix = 'elcuro_sylius_payment_fee.payment_method.calculator_configuration.';

        yield 'unknown calculator' => ['ghost', [], ['calculator' => 'elcuro_sylius_payment_fee.payment_method.calculator.unknown']];
        yield 'flat rate: missing channel' => ['flat_rate', ['WEB' => ['amount' => 150]], ['calculatorConfiguration[MOBILE]' => $prefix . 'channel_missing']];
        yield 'flat rate: unknown channel' => [
            'flat_rate',
            ['WEB' => ['amount' => 1], 'MOBILE' => ['amount' => 1], 'GHOST' => ['amount' => 1]],
            ['calculatorConfiguration[GHOST]' => $prefix . 'channel_unknown'],
        ];
        yield 'flat rate: missing amount' => [
            'flat_rate',
            ['WEB' => ['amount' => null], 'MOBILE' => []],
            ['calculatorConfiguration[WEB][amount]' => $prefix . 'amount_not_blank', 'calculatorConfiguration[MOBILE][amount]' => 'This field is missing.'],
        ];
        yield 'flat rate: negative amount' => [
            'flat_rate',
            ['WEB' => ['amount' => -1], 'MOBILE' => ['amount' => 1]],
            ['calculatorConfiguration[WEB][amount]' => $prefix . 'amount_negative'],
        ];
        yield 'flat rate: amount of a wrong type' => [
            'flat_rate',
            ['WEB' => ['amount' => '1.50'], 'MOBILE' => ['amount' => 1.5]],
            ['calculatorConfiguration[WEB][amount]' => $prefix . 'amount_integer', 'calculatorConfiguration[MOBILE][amount]' => $prefix . 'amount_integer'],
        ];
        yield 'flat rate: entry is not an object' => [
            'flat_rate',
            ['WEB' => 150, 'MOBILE' => ['amount' => 1, 'currency' => 'EUR']],
            ['calculatorConfiguration[WEB]' => 'This value should be of type {{ type }}.', 'calculatorConfiguration[MOBILE][currency]' => 'This field was not expected.'],
        ];
        yield 'country rate: unknown country' => ['country_rate', ['XX' => ['currency' => 'EUR', 'amount' => 1]], ['calculatorConfiguration[XX]' => $prefix . 'country_unknown']];
        yield 'country rate: unknown currency' => [
            'country_rate',
            ['DE' => ['currency' => 'USD', 'amount' => 1]],
            ['calculatorConfiguration[DE][currency]' => $prefix . 'currency_unknown'],
        ];
        yield 'country rate: missing currency, negative amount' => [
            'country_rate',
            ['DE' => ['currency' => '', 'amount' => -5]],
            ['calculatorConfiguration[DE][currency]' => $prefix . 'currency_not_blank', 'calculatorConfiguration[DE][amount]' => $prefix . 'amount_negative'],
        ];
    }

    /**
     * @param array<string, mixed> $configuration
     *
     * @return array<string, string>
     */
    private function violations(?string $calculator, array $configuration): array
    {
        $paymentMethod = new PaymentMethod();
        $paymentMethod->setCalculator($calculator);
        $paymentMethod->setCalculatorConfiguration($configuration);

        $violations = [];
        foreach ($this->validator->validateProperty($paymentMethod, 'calculator', ['sylius']) as $violation) {
            $violations[$violation->getPropertyPath()] = $violation->getMessageTemplate();
        }
        foreach ($this->validator->validateProperty($paymentMethod, 'calculatorConfiguration', ['sylius']) as $violation) {
            $violations[$violation->getPropertyPath()] = $violation->getMessageTemplate();
        }

        return $violations;
    }
}

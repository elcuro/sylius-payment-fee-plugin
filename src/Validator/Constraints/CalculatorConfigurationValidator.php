<?php

declare(strict_types=1);

namespace Elcuro\SyliusPaymentFeePlugin\Validator\Constraints;

use Elcuro\SyliusPaymentFeePlugin\Calculator\CalculatorInterface;
use Elcuro\SyliusPaymentFeePlugin\Model\PaymentMethodWithFeeInterface;
use Sylius\Component\Addressing\Model\CountryInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Sylius\Component\Registry\ServiceRegistryInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PositiveOrZero;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validates the calculator configuration against the calculator of the validated payment method.
 * Only the built-in configuration types are known: "flat_rate" and "country_rate". "free", custom
 * calculators of other types and an unknown or missing calculator (see RegisteredCalculator) are
 * left alone.
 */
final class CalculatorConfigurationValidator extends ConstraintValidator
{
    /**
     * @param ChannelRepositoryInterface<\Sylius\Component\Channel\Model\ChannelInterface> $channelRepository
     * @param RepositoryInterface<CountryInterface> $countryRepository
     * @param RepositoryInterface<CurrencyInterface> $currencyRepository
     */
    public function __construct(
        private readonly ServiceRegistryInterface $calculatorRegistry,
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly RepositoryInterface $countryRepository,
        private readonly RepositoryInterface $currencyRepository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof CalculatorConfiguration) {
            throw new UnexpectedTypeException($constraint, CalculatorConfiguration::class);
        }

        $paymentMethod = $this->context->getObject();
        if (!$paymentMethod instanceof PaymentMethodWithFeeInterface) {
            return;
        }

        $calculatorName = $paymentMethod->getCalculator();
        if (null === $calculatorName || !$this->calculatorRegistry->has($calculatorName)) {
            return;
        }

        $calculator = $this->calculatorRegistry->get($calculatorName);
        \assert($calculator instanceof CalculatorInterface);

        $configurationConstraint = match ($calculator->getType()) {
            'flat_rate' => $this->flatRateConstraint($constraint),
            'country_rate' => $this->countryRateConstraint($constraint),
            default => null,
        };

        if (null === $configurationConstraint) {
            return;
        }

        $this->context
            ->getValidator()
            ->inContext($this->context)
            ->validate($value ?? [], $configurationConstraint, [Constraint::DEFAULT_GROUP])
        ;
    }

    /** {"<CHANNEL_CODE>": {"amount": int}} for every channel of the shop, like the admin form. */
    private function flatRateConstraint(CalculatorConfiguration $constraint): Collection
    {
        $channelCodes = [];
        foreach ($this->channelRepository->findAllWithBasicData() as $channel) {
            if (is_string($channel['code'] ?? null)) {
                $channelCodes[] = $channel['code'];
            }
        }

        return new Collection(
            fields: array_fill_keys($channelCodes, new Collection(
                fields: ['amount' => $this->amountConstraint($constraint)],
            )),
            extraFieldsMessage: $constraint->unknownChannelMessage,
            missingFieldsMessage: $constraint->missingChannelMessage,
        );
    }

    /** {"<COUNTRY_CODE>": {"currency": "<CODE>", "amount": int}}; countries left out pay no fee. */
    private function countryRateConstraint(CalculatorConfiguration $constraint): Collection
    {
        $countryCodes = array_map(
            static fn (CountryInterface $country): string => (string) $country->getCode(),
            $this->countryRepository->findAll(),
        );
        $currencyCodes = array_map(
            static fn (CurrencyInterface $currency): string => (string) $currency->getCode(),
            $this->currencyRepository->findAll(),
        );

        return new Collection(
            fields: array_fill_keys($countryCodes, new Collection(
                fields: [
                    'currency' => new Sequentially([
                        new NotBlank(message: $constraint->currencyNotBlankMessage),
                        new Choice(choices: $currencyCodes, message: $constraint->unknownCurrencyMessage),
                    ]),
                    'amount' => $this->amountConstraint($constraint),
                ],
            )),
            allowMissingFields: true,
            extraFieldsMessage: $constraint->unknownCountryMessage,
        );
    }

    /** An amount in the smallest currency unit: present, integer, not negative. */
    private function amountConstraint(CalculatorConfiguration $constraint): Sequentially
    {
        return new Sequentially([
            new NotBlank(message: $constraint->amountNotBlankMessage),
            new Type(type: 'integer', message: $constraint->amountIntegerMessage),
            new PositiveOrZero(message: $constraint->amountNegativeMessage),
        ]);
    }
}

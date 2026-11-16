<?php

declare(strict_types=1);

namespace Elcuro\SyliusPaymentFeePlugin\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * The calculator configuration of a payment method must have the shape its calculator expects
 * (flat_rate: an amount for every channel; country_rate: currency + amount per known country).
 * Validated on the entity, so API writes are checked like the admin form.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
final class CalculatorConfiguration extends Constraint
{
    public string $missingChannelMessage = 'elcuro_sylius_payment_fee.payment_method.calculator_configuration.channel_missing';

    public string $unknownChannelMessage = 'elcuro_sylius_payment_fee.payment_method.calculator_configuration.channel_unknown';

    public string $unknownCountryMessage = 'elcuro_sylius_payment_fee.payment_method.calculator_configuration.country_unknown';

    public string $amountNotBlankMessage = 'elcuro_sylius_payment_fee.payment_method.calculator_configuration.amount_not_blank';

    public string $amountIntegerMessage = 'elcuro_sylius_payment_fee.payment_method.calculator_configuration.amount_integer';

    public string $amountNegativeMessage = 'elcuro_sylius_payment_fee.payment_method.calculator_configuration.amount_negative';

    public string $currencyNotBlankMessage = 'elcuro_sylius_payment_fee.payment_method.calculator_configuration.currency_not_blank';

    public string $unknownCurrencyMessage = 'elcuro_sylius_payment_fee.payment_method.calculator_configuration.currency_unknown';

    public function __construct(?array $groups = null, mixed $payload = null)
    {
        parent::__construct(null, $groups, $payload);
    }

    public function validatedBy(): string
    {
        return 'elcuro_sylius_payment_fee.validator.calculator_configuration';
    }
}

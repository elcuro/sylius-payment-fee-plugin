<?php

declare(strict_types=1);

namespace Elcuro\SyliusPaymentFeePlugin\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/** The payment fee calculator name must be registered; null (no fee) passes. */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
final class RegisteredCalculator extends Constraint
{
    public string $message = 'elcuro_sylius_payment_fee.payment_method.calculator.unknown';

    public function __construct(?array $groups = null, mixed $payload = null)
    {
        parent::__construct(null, $groups, $payload);
    }

    public function validatedBy(): string
    {
        return 'elcuro_sylius_payment_fee.validator.registered_calculator';
    }
}

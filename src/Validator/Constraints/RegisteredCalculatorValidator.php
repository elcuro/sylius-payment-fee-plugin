<?php

declare(strict_types=1);

namespace Elcuro\SyliusPaymentFeePlugin\Validator\Constraints;

use Sylius\Component\Registry\ServiceRegistryInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class RegisteredCalculatorValidator extends ConstraintValidator
{
    public function __construct(
        private readonly ServiceRegistryInterface $calculatorRegistry,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof RegisteredCalculator) {
            throw new UnexpectedTypeException($constraint, RegisteredCalculator::class);
        }

        if (null === $value || (is_string($value) && $this->calculatorRegistry->has($value))) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ value }}', $this->formatValue($value))
            ->setParameter('{{ choices }}', implode(', ', array_keys($this->calculatorRegistry->all())))
            ->addViolation()
        ;
    }
}

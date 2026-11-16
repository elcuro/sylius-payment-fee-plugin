<?php

declare(strict_types=1);

namespace Elcuro\SyliusPaymentFeePlugin\Model;

use Doctrine\ORM\Mapping as ORM;
use Elcuro\SyliusPaymentFeePlugin\Validator\Constraints\CalculatorConfiguration;
use Elcuro\SyliusPaymentFeePlugin\Validator\Constraints\RegisteredCalculator;
use Sylius\Component\Taxation\Model\TaxCategoryInterface;

trait PaymentMethodWithFeeTrait
{
    #[ORM\Column(name: 'calculator', type: 'text', nullable: true)]
    #[RegisteredCalculator(groups: ['sylius', 'Default'])]
    protected ?string $calculator = null;

    /** @var array<string, mixed> */
    #[ORM\Column(name: 'calculator_configuration', type: 'json', nullable: true)]
    #[CalculatorConfiguration(groups: ['sylius', 'Default'])]
    protected array $calculatorConfiguration = [];

    #[ORM\ManyToOne(targetEntity: TaxCategoryInterface::class)]
    #[ORM\JoinColumn(name: 'tax_category_id')]
    protected ?TaxCategoryInterface $taxCategory = null;

    public function getCalculator(): ?string
    {
        return $this->calculator;
    }

    public function setCalculator(?string $calculator): void
    {
        $this->calculator = $calculator;
    }

    public function getCalculatorConfiguration(): array
    {
        return $this->calculatorConfiguration ?? [];
    }

    public function setCalculatorConfiguration(array $calculatorConfiguration): void
    {
        $this->calculatorConfiguration = $calculatorConfiguration;
    }

    public function getTaxCategory(): ?TaxCategoryInterface
    {
        return $this->taxCategory;
    }

    public function setTaxCategory(?TaxCategoryInterface $taxCategory): void
    {
        $this->taxCategory = $taxCategory;
    }
}

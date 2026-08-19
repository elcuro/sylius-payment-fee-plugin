<?php

declare(strict_types=1);

namespace Elcuro\SyliusPaymentFeePlugin\Fixture\Factory;

use Elcuro\SyliusPaymentFeePlugin\Model\PaymentMethodWithFeeInterface;
use Sylius\Bundle\CoreBundle\Fixture\Factory\PaymentMethodExampleFactory as BasePaymentMethodExampleFactory;
use Sylius\Bundle\CoreBundle\Fixture\OptionsResolver\LazyOption;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Factory\PaymentMethodFactoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Taxation\Model\TaxCategoryInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PaymentMethodExampleFactory extends BasePaymentMethodExampleFactory
{
    /**
     * @param PaymentMethodFactoryInterface<PaymentMethodInterface> $paymentMethodFactory
     * @param RepositoryInterface<LocaleInterface> $localeRepository
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     * @param RepositoryInterface<TaxCategoryInterface> $taxCategoryRepository
     */
    public function __construct(
        PaymentMethodFactoryInterface $paymentMethodFactory,
        RepositoryInterface $localeRepository,
        ChannelRepositoryInterface $channelRepository,
        private readonly RepositoryInterface $taxCategoryRepository,
    ) {
        parent::__construct($paymentMethodFactory, $localeRepository, $channelRepository);
    }

    public function create(array $options = []): PaymentMethodInterface
    {
        $resolvedOptions = $this->optionsResolver->resolve($options);

        $paymentMethod = parent::create($options);

        if (!$paymentMethod instanceof PaymentMethodWithFeeInterface) {
            return $paymentMethod;
        }

        if ($resolvedOptions['fee_calculator'] !== null) {
            $paymentMethod->setCalculator($resolvedOptions['fee_calculator']);
            $paymentMethod->setCalculatorConfiguration($resolvedOptions['fee_calculator_configuration']);
        }

        if ($resolvedOptions['fee_tax_category'] instanceof TaxCategoryInterface) {
            $paymentMethod->setTaxCategory($resolvedOptions['fee_tax_category']);
        }

        return $paymentMethod;
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver
            ->setDefault('fee_calculator', null)
            ->setAllowedTypes('fee_calculator', ['null', 'string'])
            ->setDefault('fee_calculator_configuration', [])
            ->setAllowedTypes('fee_calculator_configuration', 'array')
            ->setDefault('fee_tax_category', null)
            ->setAllowedTypes('fee_tax_category', ['null', 'string', TaxCategoryInterface::class])
            ->setNormalizer('fee_tax_category', LazyOption::findOneBy($this->taxCategoryRepository, 'code'))
        ;
    }
}

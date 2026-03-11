<?php

declare(strict_types=1);

namespace Elcuro\SyliusPaymentFeePlugin\Form\Type\Calculator;

use Sylius\Bundle\CoreBundle\Form\Type\ChannelCollectionType;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Webmozart\Assert\Assert;

final class ChannelBasedFlatRateConfigurationType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'entry_type' => FlatRateConfigurationType::class,
            'entry_options' => function (ChannelInterface $channel): array {
                $baseCurrency = $channel->getBaseCurrency();
                Assert::notNull($baseCurrency, 'Channel base currency cannot be null.');

                return [
                    'label' => $channel->getName(),
                    'currency' => $baseCurrency->getCode(),
                ];
            },
        ]);
    }

    public function getParent(): string
    {
        return ChannelCollectionType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'elcuro_channel_based_payment_calculator_flat_rate';
    }
}

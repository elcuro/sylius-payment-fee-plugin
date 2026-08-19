<?php

declare(strict_types=1);

namespace Elcuro\SyliusPaymentFeePlugin\Fixture;

use Sylius\Bundle\CoreBundle\Fixture\PaymentMethodFixture as BasePaymentMethodFixture;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

class PaymentMethodFixture extends BasePaymentMethodFixture
{
    protected function configureResourceNode(ArrayNodeDefinition $resourceNode): void
    {
        parent::configureResourceNode($resourceNode);

        $resourceNode
            ->children()
                ->scalarNode('fee_calculator')->end()
                ->arrayNode('fee_calculator_configuration')
                    ->variablePrototype()->end()
                ->end()
                ->scalarNode('fee_tax_category')->end()
            ->end()
        ;
    }
}

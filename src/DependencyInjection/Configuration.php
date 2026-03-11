<?php

declare(strict_types=1);

namespace Elcuro\SyliusPaymentFeePlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('elcuro_sylius_payment_fee');
        $treeBuilder->getRootNode();

        return $treeBuilder;
    }
}

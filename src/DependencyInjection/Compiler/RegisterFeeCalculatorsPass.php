<?php

declare(strict_types=1);

namespace Elcuro\SyliusPaymentFeePlugin\DependencyInjection\Compiler;

use Elcuro\SyliusPaymentFeePlugin\Calculator\DelegatingCalculator;
use Elcuro\SyliusPaymentFeePlugin\Model\AdjustmentInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class RegisterFeeCalculatorsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (
            !$container->hasDefinition('elcuro_sylius_payment_fee.registry.calculator') ||
            !$container->hasDefinition('elcuro_sylius_payment_fee.form_registry.calculator')
        ) {
            return;
        }

        $registry = $container->getDefinition('elcuro_sylius_payment_fee.registry.calculator');
        $formTypeRegistry = $container->getDefinition('elcuro_sylius_payment_fee.form_registry.calculator');
        $calculators = [];

        foreach ($container->findTaggedServiceIds(DelegatingCalculator::class) as $id => $attributes) {
            if (!isset($attributes[0]['calculator'], $attributes[0]['label'])) {
                throw new \InvalidArgumentException('Tagged payment fee calculators need to have `calculator` and `label` attributes.');
            }

            $name = $attributes[0]['calculator'];
            $calculators[$name] = $attributes[0]['label'];

            $registry->addMethodCall('register', [$name, new Reference($id)]);

            if (isset($attributes[0]['form_type'])) {
                $formTypeRegistry->addMethodCall('add', [$name, 'default', $attributes[0]['form_type']]);
            }
        }

        $container->setParameter('elcuro_sylius_payment_fee.calculators', $calculators);

        // Add payment adjustment to the clearing types parameter
        if ($container->hasParameter('sylius.order_processing.adjustment_clearing_types')) {
            /** @var array<int, string> $clearingTypes */
            $clearingTypes = $container->getParameter('sylius.order_processing.adjustment_clearing_types');
            $clearingTypes[] = AdjustmentInterface::PAYMENT_ADJUSTMENT;
            $container->setParameter('sylius.order_processing.adjustment_clearing_types', $clearingTypes);
        }
    }
}

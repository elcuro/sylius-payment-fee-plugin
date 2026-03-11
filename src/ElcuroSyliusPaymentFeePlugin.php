<?php

declare(strict_types=1);

namespace Elcuro\SyliusPaymentFeePlugin;

use Elcuro\SyliusPaymentFeePlugin\DependencyInjection\Compiler\RegisterFeeCalculatorsPass;
use Sylius\Bundle\CoreBundle\Application\SyliusPluginTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class ElcuroSyliusPaymentFeePlugin extends Bundle
{
    use SyliusPluginTrait;

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new RegisterFeeCalculatorsPass());
    }
}

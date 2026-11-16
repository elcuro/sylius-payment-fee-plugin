<?php

declare(strict_types=1);

namespace Elcuro\SyliusPaymentFeePlugin\DependencyInjection;

use Sylius\Bundle\CoreBundle\DependencyInjection\PrependDoctrineMigrationsTrait;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

final class ElcuroSyliusPaymentFeeExtension extends AbstractResourceExtension implements PrependExtensionInterface
{
    use PrependDoctrineMigrationsTrait;

    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.xml');
    }

    public function prepend(ContainerBuilder $container): void
    {
        $this->prependDoctrineMigrations($container);
        $this->prependAdminApi($container);
    }

    /**
     * Property descriptions (config/api_platform) and the serialization groups that put the fee
     * fields into the core PaymentMethod resource (config/serialization). Hosts get them with the
     * bundle; without API Platform there is nothing to register.
     */
    private function prependAdminApi(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('api_platform')) {
            return;
        }

        $configDir = dirname(__DIR__, 2) . '/config';

        $container->prependExtensionConfig('api_platform', [
            'mapping' => ['paths' => [$configDir . '/api_platform']],
        ]);
        $container->prependExtensionConfig('framework', [
            'serializer' => ['mapping' => ['paths' => [$configDir . '/serialization']]],
        ]);
    }

    protected function getMigrationsNamespace(): string
    {
        return 'Elcuro\SyliusPaymentFeePlugin\Migrations';
    }

    protected function getMigrationsDirectory(): string
    {
        return '@ElcuroSyliusPaymentFeePlugin/src/Migrations';
    }

    /** @return array<int, string> */
    protected function getNamespacesOfMigrationsExecutedBefore(): array
    {
        return [
            'Sylius\Bundle\CoreBundle\Migrations',
        ];
    }
}

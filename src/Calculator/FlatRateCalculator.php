<?php

declare(strict_types=1);

namespace Elcuro\SyliusPaymentFeePlugin\Calculator;

use Sylius\Component\Core\Exception\MissingChannelConfigurationException;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentInterface as BasePaymentInterface;
use Webmozart\Assert\Assert;

final class FlatRateCalculator implements CalculatorInterface
{
    public function calculate(BasePaymentInterface $subject, array $configuration): ?int
    {
        Assert::isInstanceOf($subject, PaymentInterface::class);
        $order = $subject->getOrder();
        Assert::isInstanceOf($order, OrderInterface::class);

        $channel = $order->getChannel();
        Assert::notNull($channel, 'Order channel cannot be null.');

        $channelCode = $channel->getCode();

        if (!isset($configuration[$channelCode])) {
            throw new MissingChannelConfigurationException(sprintf(
                'Channel %s has no amount defined for payment method %s',
                $channel->getName() ?? $channelCode,
                $subject->getMethod()?->getName() ?? 'unknown',
            ));
        }

        /** @var array{amount: int|string} $channelConfig */
        $channelConfig = $configuration[$channelCode];

        return (int) $channelConfig['amount'];
    }

    public function getType(): string
    {
        return 'flat_rate';
    }
}

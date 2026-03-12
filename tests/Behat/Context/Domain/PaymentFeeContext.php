<?php

declare(strict_types=1);

namespace Tests\Elcuro\SyliusPaymentFeePlugin\Behat\Context\Domain;

use Behat\Behat\Context\Context;
use Elcuro\SyliusPaymentFeePlugin\Model\AdjustmentInterface;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Webmozart\Assert\Assert;

final readonly class PaymentFeeContext implements Context
{
    public function __construct(
        private SharedStorageInterface $sharedStorage,
    ) {
    }

    /**
     * @Then this order should have a :amount payment fee
     */
    public function thisOrderShouldHavePaymentFee(string $amount): void
    {
        $order = $this->getOrder();
        $expectedAmount = (int) round((float) preg_replace('/[^0-9.]/', '', $amount) * 100);

        $adjustmentTotal = $order->getAdjustmentsTotal(AdjustmentInterface::PAYMENT_ADJUSTMENT);

        Assert::same(
            $adjustmentTotal,
            $expectedAmount,
            sprintf(
                'Expected payment fee of %d, but got %d.',
                $expectedAmount,
                $adjustmentTotal,
            ),
        );
    }

    /**
     * @Then this order should not have a payment fee
     */
    public function thisOrderShouldNotHavePaymentFee(): void
    {
        $order = $this->getOrder();
        $adjustmentTotal = $order->getAdjustmentsTotal(AdjustmentInterface::PAYMENT_ADJUSTMENT);

        Assert::same(
            $adjustmentTotal,
            0,
            sprintf('Expected no payment fee, but got %d.', $adjustmentTotal),
        );
    }

    /**
     * @Then this order should have a payment fee from currency conversion
     */
    public function thisOrderShouldHavePaymentFeeFromCurrencyConversion(): void
    {
        $order = $this->getOrder();
        $adjustmentTotal = $order->getAdjustmentsTotal(AdjustmentInterface::PAYMENT_ADJUSTMENT);

        Assert::greaterThan(
            $adjustmentTotal,
            0,
            'Expected a payment fee from currency conversion, but got 0.',
        );
    }

    private function getOrder(): OrderInterface
    {
        $order = $this->sharedStorage->get('order');
        Assert::isInstanceOf($order, OrderInterface::class);

        return $order;
    }
}

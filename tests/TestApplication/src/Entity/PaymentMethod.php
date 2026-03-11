<?php

declare(strict_types=1);

namespace Tests\Elcuro\SyliusPaymentFeePlugin\Entity;

use Doctrine\ORM\Mapping as ORM;
use Elcuro\SyliusPaymentFeePlugin\Model\PaymentMethodWithFeeInterface;
use Elcuro\SyliusPaymentFeePlugin\Model\PaymentMethodWithFeeTrait;
use Sylius\Component\Core\Model\PaymentMethod as BasePaymentMethod;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_payment_method')]
class PaymentMethod extends BasePaymentMethod implements PaymentMethodWithFeeInterface
{
    use PaymentMethodWithFeeTrait;
}

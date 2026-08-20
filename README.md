# Sylius Payment Fee Plugin

Payment method fee plugin for Sylius 2.0. Adds configurable fees to payment methods — displayed during checkout and applied as order adjustments with tax support.

## Features

- **Fee Calculators**: Flat rate (per-channel), country rate (per-country with currency conversion), and free calculator, with support for custom calculators
- **Per-channel configuration**: Different fee amounts for each sales channel
- **Per-country configuration**: Different fee amounts and currencies for each country, with automatic currency conversion via exchange rates
- **Tax support**: Assign tax categories to payment fees, including VAT reverse charge
- **Admin UI**: Calculator selection and configuration on Payment Method edit page, fee display on order detail
- **Shop UI**: Payment fee displayed during checkout

## Requirements

- PHP ^8.2
- Sylius ^2.0

## Installation

1. Install the plugin via Composer:

    ```bash
    composer require elcuro/sylius-payment-fee-plugin
    ```

2. Register the bundle in `config/bundles.php` (if not auto-registered):

    ```php
    return [
        // ...
        Elcuro\SyliusPaymentFeePlugin\ElcuroSyliusPaymentFeePlugin::class => ['all' => true],
    ];
    ```

3. Run the database migration:

    ```bash
    bin/console doctrine:migrations:migrate -n
    ```

4. Extend your `PaymentMethod` entity with the fee trait:

    ```php
    use Elcuro\SyliusPaymentFeePlugin\Model\PaymentMethodWithFeeInterface;
    use Elcuro\SyliusPaymentFeePlugin\Model\PaymentMethodWithFeeTrait;

    class PaymentMethod extends BasePaymentMethod implements PaymentMethodWithFeeInterface
    {
        use PaymentMethodWithFeeTrait;
    }
    ```

## Configuration

Go to **Admin > Configuration > Payment Methods**, edit a payment method, and select a fee calculator:

- **Free** — no fee (default)
- **Flat Rate** — fixed fee amount per channel
- **Country Rate** — fee amount and currency per country; if the order currency differs from the configured currency, the fee is automatically converted using Sylius exchange rates

Optionally assign a **Tax Category** to apply tax on the payment fee.

### VAT reverse charge

If another plugin removes VAT from an order (EU reverse charge), the fee follows automatically —
`PaymentFeeReverseChargeProcessor` drops the fee's tax adjustments and takes the tax they carried off the
fee itself, so the checkout shows the fee net and `Order::getTaxTotal()` reaches zero.

Reverse charge is recognised by a `reverseCharge` detail flag on any of the order's tax adjustments — the
convention `elcuro/sylius-enhanced-plugin` writes and `elcuro/sylius-superfaktura-plugin` reads. There is no
package dependency on either: without such a plugin nothing carries the flag and the processor stays inert.

The fee tax is *not* cancelled with a negative order-level tax adjustment, which would be the obvious move.
`Order::getShippingTotal()` adds every non-neutral order-level `TAX_ADJUSTMENT` to the shipping line, so the
deduction would silently land on the shipping row instead of the fee row.

## Development

### DDEV Environment

```bash
ddev start                    # Start containers
ddev ssh                      # Shell into web container
ddev composer install         # Install dependencies
```

### Database

```bash
vendor/bin/console doctrine:database:create
vendor/bin/console doctrine:migrations:migrate -n
vendor/bin/console sylius:fixtures:load -n
composer run database-reset   # Drop + create + migrate + fixtures
```

### Frontend

```bash
(cd vendor/sylius/test-application && yarn install && yarn build)
vendor/bin/console assets:install
```

### Testing

```bash
vendor/bin/phpunit                                                    # PHPUnit
vendor/bin/behat --strict --tags="~@javascript&&~@mink:chromedriver"  # Behat (non-JS)

# JS scenarios require Chrome headless + Symfony server on port 8080
APP_ENV=test symfony server:start --port=8080 --daemon
vendor/bin/behat --strict --tags="@javascript,@mink:chromedriver"
```

### Code Quality

```bash
vendor/bin/phpstan analyse -c phpstan.neon -l max src/   # Static analysis
vendor/bin/ecs check                                      # Coding standards
```

## License

MIT. See [LICENSE](LICENSE).

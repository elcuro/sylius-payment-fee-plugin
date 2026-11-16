# Sylius Payment Fee Plugin

Payment method fee plugin for Sylius 2.3. Adds configurable fees to payment methods — displayed during checkout and applied as order adjustments with tax support.

## Features

- **Fee Calculators**: Flat rate (per-channel), country rate (per-country with currency conversion), and free calculator, with support for custom calculators
- **Per-channel configuration**: Different fee amounts for each sales channel
- **Per-country configuration**: Different fee amounts and currencies for each country, with automatic currency conversion via exchange rates
- **Tax support**: Assign tax categories to payment fees, including VAT reverse charge
- **Admin UI**: Calculator selection and configuration on Payment Method edit page, fee display on order detail
- **Shop UI**: Payment fee displayed during checkout
- **Admin API**: fee fields on the core PaymentMethod resource, validated like the admin form (see [Admin API](#admin-api))

## Requirements

- PHP ^8.3
- Sylius ^2.3 (hosts on Sylius 2.2 need an older plugin version, tag `v2.2.0`)

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

3. Run the database migrations:

    ```bash
    bin/console doctrine:migrations:migrate -n
    ```

    The plugin adds `calculator`, `calculator_configuration` and `tax_category_id` to `sylius_payment_method`
    (`Version20260311000000`). `Version20261116000000` drops the Doctrine DBAL 3 type comment
    `(DC2Type:json)` from `calculator_configuration` for DBAL 4 (Sylius 2.3); it changes nothing where the
    column has no comment.

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

Amounts are stored as integers in the smallest currency unit (cents). They cannot be negative; a flat rate needs an
amount for every channel of the shop.

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

## Admin API

The fee is part of the core `PaymentMethod` resource of the Sylius Admin API (`/api/v2/admin/payment-methods`,
JWT of an administrator with `ROLE_API_ACCESS`). Nothing has to be configured: when API Platform is installed, the
extension registers `config/serialization` (groups) and `config/api_platform` (property descriptions) itself. The
host needs `sylius_api.enabled: true` and its `PaymentMethod` must use `PaymentMethodWithFeeTrait` (the fields are
mapped on `PaymentMethodWithFeeInterface`, so they appear only then). Spec: `etc/specs/admin-api.md`.

| Field | list | show | create / update | Value |
|---|---|---|---|---|
| `calculator` | x | x | x | `"free"`, `"flat_rate"`, `"country_rate"` (or a calculator the shop registers); `null` = no fee |
| `calculatorConfiguration` | | x | x | JSON object, see below; replaced as a whole on write |
| `taxCategory` | | x | x | IRI `/api/v2/admin/tax-categories/{code}` or `null` (fee not taxed) |

`calculatorConfiguration` (amounts in the smallest currency unit, integers, not negative):

```json
// flat_rate: one entry for EVERY channel of the shop, in the channel's base currency
{"FASHION_WEB": {"amount": 500}}

// country_rate: by country code of the shipping address; countries left out pay no fee,
// other order currencies are converted with the shop's exchange rates
{"DE": {"currency": "EUR", "amount": 250}, "SK": {"currency": "EUR", "amount": 150}}
```

`free` ignores the configuration (switching to it with `PUT {"calculator": "free"}` keeps the old one stored).
`PUT` changes only the fields in the body.

**Validation** lives on the entity (constraints `RegisteredCalculator` and `CalculatorConfiguration` on the trait,
group `sylius`), not in the admin form: an unknown calculator, a missing / unknown channel, an unknown country or
currency, a missing, negative or non-integer amount and unexpected keys answer **422** with a message on the property
path (e.g. `calculatorConfiguration[FASHION_WEB][amount]`); nothing is stored. The admin form shows the same
messages, once.

**Read-only:** the fees already charged on orders. They are order adjustments of type `payment` (label = payment
method name, details = its code and name), readable through the core
`GET /api/v2/admin/orders/{tokenValue}/adjustments?type=payment`; the core exposes no write operation for
adjustments and the plugin adds none.

**MCP** (`elcuro/sylius-mcp-plugin`): the plugin only extends the core `PaymentMethod` resource, so it needs no
entry of its own in `elcuro_sylius_mcp.resources.enabled`; the fields come with `PaymentMethod`.

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
vendor/bin/phpunit                                                    # PHPUnit: unit + functional (Admin API, test DB)
vendor/bin/behat --strict --no-interaction --tags="~@javascript&&~@mink:chromedriver"  # Behat (non-JS)

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

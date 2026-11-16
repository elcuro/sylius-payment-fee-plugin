# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Sylius Payment Fee Plugin — adds configurable payment method fees to Sylius 2.3 e-commerce platform. Fees are calculated per payment method, applied as order adjustments, and support taxation.

- **Namespace**: `Elcuro\SyliusPaymentFeePlugin`
- **PHP**: ^8.3, **Sylius**: ^2.3 (API Platform 5, Doctrine DBAL 4; the last Sylius 2.2 version is tag `v2.2.0`)
- **Local dev**: DDEV (nginx + MariaDB 11.8 + PHP 8.4)

## Development Commands

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
# Or: composer run frontend-clear
```

### Testing
```bash
vendor/bin/phpunit                                                    # PHPUnit: suites unit + functional
vendor/bin/behat --strict --no-interaction --tags="~@javascript&&~@mink:chromedriver"  # Behat (non-JS)

# JS scenarios require Chrome headless + symfony server on port 8080
APP_ENV=test symfony server:start --port=8080 --daemon
vendor/bin/behat --strict --tags="@javascript,@mink:chromedriver"
```

Through `ddev exec`, quote the whole Behat command (`ddev exec 'vendor/bin/behat … --tags="…&&…"'`), otherwise the
shell splits it at `&&`. After a new migration run it for the test DB too
(`ddev exec "APP_ENV=test vendor/bin/console doctrine:migrations:migrate -n"`). `behat.yml.dist` imports the core
suites from `suites.php` (Sylius 2.3 ships them as PHP). If a new validator message shows up untranslated in tests,
delete `var/cache/test/translations` (a stale catalogue survives `cache:clear`).

### Code Quality
```bash
vendor/bin/phpstan analyse -c phpstan.neon -l max src/   # Static analysis
vendor/bin/ecs check                                      # Coding standards (Sylius CS)
```

## Architecture

### Plugin Structure
- `src/ElcuroSyliusPaymentFeePlugin.php` — Bundle entry point using `SyliusPluginTrait`, registers `RegisterFeeCalculatorsPass` compiler pass
- `src/DependencyInjection/ElcuroSyliusPaymentFeeExtension.php` — Loads `config/services.xml`, prepends Doctrine migrations and (with API Platform) the Admin API mapping paths
- `config/services.xml` — Service definitions (XML format), imports from `config/services/`
- `config/services/calculators.xml` — Fee calculator service definitions
- `config/services/forms.xml` — Form type and extension definitions
- `config/services/order_processing.xml` — Order processor for applying payment fees
- `config/services/taxation.xml` — Tax applicator for payment fees
- `config/routes/{admin,shop}.yaml` — Route definitions
- `config/twig_hooks/{admin,shop}.yaml` — Twig hooks for UI integration
- `templates/admin/` — Admin templates (order detail, payment method config)
- `templates/shop/` — Shop templates (checkout fee display)

### Calculator Pattern
- `src/Calculator/CalculatorInterface.php` — Interface for fee calculators
- `src/Calculator/DelegatingCalculator.php` — Delegates to the correct calculator based on payment method config
- `src/Calculator/FlatRateCalculator.php` — Fixed fee amount per channel
- `src/Calculator/FreeCalculator.php` — No fee
- `src/DependencyInjection/Compiler/RegisterFeeCalculatorsPass.php` — Auto-registers tagged calculators
- New calculators: implement `CalculatorInterface`, tag with the appropriate service tag

### Model
- `src/Model/PaymentMethodWithFeeTrait.php` — Adds `calculator`, `calculatorConfiguration`, and `taxCategory` to PaymentMethod entity
- `src/Model/PaymentMethodWithFeeInterface.php` — Interface for the trait
- `src/Model/AdjustmentInterface.php` — Defines `PAYMENT_ADJUSTMENT` (`payment`) adjustment type
- Validation of the fee fields lives on the trait properties, not in the form types:
  `src/Validator/Constraints/RegisteredCalculator` (name registered or null) and `CalculatorConfiguration`
  (shape per calculator type: `flat_rate` = `{CHANNEL: {amount}}` for every channel, `country_rate` =
  `{COUNTRY: {currency, amount}}`; amounts integer cents >= 0), validators in `config/services/validators.xml`,
  messages in `translations/validators.*.yaml` (six languages). The form types carry no constraints, so the admin
  shows each message once.

### Migrations
- `src/Migrations/Version20260311000000.php` — fee columns on `sylius_payment_method`
- `src/Migrations/Version20261116000000.php` — drops the DBAL 3 `(DC2Type:json)` comment of
  `calculator_configuration` (no-op where there is none); `doctrine:schema:update --dump-sql` then lists nothing on
  `sylius_payment_method` (remaining lines are core: `sylius_payment_request`, `sylius_payment_security_token`,
  `messenger_messages`). Needs `serverVersion=11.8.0-mariadb` in `DATABASE_URL` (test app `.env`): without it DBAL 4
  assumes MySQL 5.7 and reports MariaDB JSON columns as changed for ever.

### Admin API
- Spec `etc/specs/admin-api.md`; README "Admin API". The fee fields join the **core** PaymentMethod resource (no own
  resource): `config/serialization/PaymentMethod.xml` maps them on `PaymentMethodWithFeeInterface` into
  `sylius:admin:payment_method:{index (calculator only),show,create,update}`; descriptions (and the `object` schema of
  `calculatorConfiguration`) in `config/api_platform/properties/PaymentMethod.xml`. Both directories are registered by
  `prependAdminApi()` in the extension when the `api_platform` extension exists.
- Order fee adjustments (type `payment`) stay read-only (core `/admin/orders/{tokenValue}/adjustments`).
- Functional tests `tests/Functional/Api/Admin/` (`AdminApiTestCase`: WebTestCase, `disableReboot()`, rolled-back
  transaction, JWT of a fresh `ROLE_API_ACCESS` admin); `tests/bootstrap.php` loads the test app's env.

### Order Processing
- `src/OrderProcessing/PaymentChargesProcessor.php` — Calculates and applies payment fee adjustments to orders (priority 15, i.e. before `OrderTaxesProcessor` at 10, so the tax applicator sees the gross fee)
- `src/OrderProcessing/PaymentFeeReverseChargeProcessor.php` — Nets the fee when the order is VAT reverse charged (priority 5, after taxes and after any reverse-charge processor)

#### Why the fee tax cannot be cancelled the usual way

`OrderPaymentTaxesApplicator` has no adjustable to attach to — a payment is not an `AdjustableInterface` —
so the fee tax lands on the **order**. Two consequences:

1. A reverse-charge processor that walks item units and shipments (`elcuro/sylius-enhanced-plugin`) never
   reaches it, and the fee VAT survives in `Order::getTaxTotal()`.
2. Cancelling it with a negative order-level `TAX_ADJUSTMENT` does fix the total, but
   `Order::getShippingTotal()` sums `SHIPPING_ADJUSTMENT + ORDER_SHIPPING_PROMOTION_ADJUSTMENT +
   TAX_ADJUSTMENT` **at order level** — so the deduction shows up on the shipping row while the fee row stays
   gross.

`PaymentFeeReverseChargeProcessor` therefore removes the fee's tax adjustments outright and subtracts the
included tax from the fee adjustment itself (`Adjustment::setAmount()` recalculates the adjustable when the
adjustment is non-neutral, so no manual recalculation is needed). Every run of the pipeline starts from a
gross fee (priority 15) and freshly built taxes (priority 10), which keeps the processor idempotent.

Reverse charge is detected via a `reverseCharge` flag in an adjustment's `details` — a convention shared with
`elcuro/sylius-enhanced-plugin` (writer) and `elcuro/sylius-superfaktura-plugin` (reader), deliberately not a
package dependency. Note that shipment adjustments live in the order's adjustment collection too
(`Adjustment::setShipment()` calls `setAdjustable($shipment->getOrder())`), so scanning
`$order->getAdjustments(TAX_ADJUSTMENT)` covers shipping as well as order level.

### Taxation
- `src/Taxation/Applicator/OrderPaymentTaxesApplicator.php` — Applies tax to payment fee adjustments

### Form Extensions
- `src/Form/Extension/PaymentMethodTypeExtension.php` — Adds calculator and tax category fields to admin PaymentMethod form
- `src/Form/Extension/PaymentMethodChoiceTypeExtension.php` — Extends shop checkout payment method choice
- `src/Form/Type/CalculatorChoiceType.php` — Calculator selection dropdown
- `src/Form/Type/Calculator/FlatRateConfigurationType.php` — Flat rate amount field
- `src/Form/Type/Calculator/ChannelBasedFlatRateConfigurationType.php` — Per-channel flat rate config

### Test Application
The plugin runs inside `vendor/sylius/test-application` (installed via Composer). Database config lives in `tests/TestApplication/.env` and `.env.test`. The test app's public dir is `vendor/sylius/test-application/public`.

### Testing Layers
- **Behat**: `features/` (Gherkin) + `tests/Behat/` (contexts, pages, services)
- **PHPUnit**: `tests/Unit/` (suite `unit`), `tests/Functional/` (suite `functional`, boots the test app on the test DB; `phpunit.xml.dist` forces `APP_ENV=test`)
- Behat suite config: `tests/Behat/Resources/suites.yml`

## AI Development Guides

- **COMPATIBILITY_GUIDE.md** — Multi-version Sylius compatibility

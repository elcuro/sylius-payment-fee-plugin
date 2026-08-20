# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Sylius Payment Fee Plugin — adds configurable payment method fees to Sylius 2.0 e-commerce platform. Fees are calculated per payment method, applied as order adjustments, and support taxation.

- **Namespace**: `Elcuro\SyliusPaymentFeePlugin`
- **PHP**: ^8.2, **Sylius**: ^2.0
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
vendor/bin/phpunit                                                    # PHPUnit
vendor/bin/behat --strict --tags="~@javascript&&~@mink:chromedriver"  # Behat (non-JS)

# JS scenarios require Chrome headless + symfony server on port 8080
APP_ENV=test symfony server:start --port=8080 --daemon
vendor/bin/behat --strict --tags="@javascript,@mink:chromedriver"
```

### Code Quality
```bash
vendor/bin/phpstan analyse -c phpstan.neon -l max src/   # Static analysis
vendor/bin/ecs check                                      # Coding standards (Sylius CS)
```

## Architecture

### Plugin Structure
- `src/ElcuroSyliusPaymentFeePlugin.php` — Bundle entry point using `SyliusPluginTrait`, registers `RegisterFeeCalculatorsPass` compiler pass
- `src/DependencyInjection/ElcuroSyliusPaymentFeeExtension.php` — Loads `config/services.xml`, prepends Doctrine migrations
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
- `src/Model/AdjustmentInterface.php` — Defines `PAYMENT_FEE_ADJUSTMENT` constant

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
- **PHPUnit**: `tests/Unit/`, `tests/Integration/`, `tests/Functional/`
- Behat suite config: `tests/Behat/Resources/suites.yml`

## AI Development Guides

- **COMPATIBILITY_GUIDE.md** — Multi-version Sylius compatibility

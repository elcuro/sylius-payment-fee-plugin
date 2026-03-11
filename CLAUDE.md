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
- `src/OrderProcessing/PaymentChargesProcessor.php` — Calculates and applies payment fee adjustments to orders

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

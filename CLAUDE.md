# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Sylius Payment Fee Plugin — adds payment method fees to Sylius 2.0 e-commerce platform. Currently in early development (scaffolded from `sylius/plugin-skeleton`, example greeting code still present).

- **Namespace**: `Elcuro\SyliusPaymentFeePluginPlugin`
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
- `src/ElcuroSyliusPaymentFeePluginPlugin.php` — Bundle entry point using `SyliusPluginTrait`
- `src/DependencyInjection/ElcuroSyliusPaymentFeePluginExtension.php` — Loads `config/services.xml`, prepends Doctrine migrations
- `config/services.xml` — Service definitions (XML format), imports from `config/services/`
- `config/routes/{admin,shop}.yaml` — Route definitions
- `config/twig_hooks/` — Twig hooks for shop/admin UI integration
- `templates/{admin,shop}/` — Twig templates
- `assets/{admin,shop}/` — Frontend entrypoints (Webpack Encore via test application)

### Test Application
The plugin runs inside `vendor/sylius/test-application` (installed via Composer). Database config lives in `tests/TestApplication/.env` and `.env.test`. The test app's public dir is `vendor/sylius/test-application/public`.

### Testing Layers
- **Behat**: `features/` (Gherkin) + `tests/Behat/` (contexts, pages, services)
- **PHPUnit**: `tests/Unit/`, `tests/Integration/`, `tests/Functional/` (currently empty)
- Behat suite config: `tests/Behat/Resources/suites.yml`

## Current State

The plugin still contains example/greeting scaffold code that should be cleaned up before building payment fee functionality. See `CLEANUP_GUIDE.md` for removal instructions. Key files to remove:
- `src/Controller/GreetingController.php`
- `templates/shop/greeting/`
- `assets/shop/js/greetings.js`
- `features/*greeting*`
- `tests/Behat/Context/Ui/Shop/WelcomeContext.php`
- `tests/Behat/Page/Shop/*Welcome*`

## AI Development Guides

- **CLEANUP_GUIDE.md** — Removing example/scaffold code
- **RENAME_GUIDE.md** — Renaming plugin namespace and components
- **COMPATIBILITY_GUIDE.md** — Multi-version Sylius compatibility
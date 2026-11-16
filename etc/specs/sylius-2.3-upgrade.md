# Upgrade to Sylius 2.3

## Goal
Move the plugin from Sylius 2.2 (2.2.5) to Sylius 2.3 (2.3.2 at the time of writing), together
with the API Platform / Doctrine / Symfony versions it pulls in.

## Tasks
- [x] `composer.json`: `sylius/sylius: ^2.3`, `php: ^8.3` (Sylius 2.3 requires it),
      `sylius/test-application: ~2.3.0@alpha`
- [x] `composer update -W`
- [x] Reset dev + test databases, rebuild the TestApplication frontend
- [x] `behat.yml.dist`: Sylius 2.3 ships its Behat suites as PHP (`suites.php`), the YAML file
      the config imported is gone
- [x] `tests/TestApplication/.env{,.test}`: `DATABASE_URL` gets `serverVersion=11.8.0-mariadb&charset=utf8mb4`;
      without it DBAL 4 assumes MySQL 5.7 and introspects MariaDB JSON columns as `LONGTEXT`, so
      `doctrine:schema:update --dump-sql` listed a change of `calculator_configuration` for ever
- [x] Migration `Version20261116000000`: drops the DBAL 3 `(DC2Type:json)` comment from
      `sylius_payment_method.calculator_configuration` (run on dev + test DB; down() restores it)
- [x] PHPUnit, Behat (non-JS), PHPStan, ECS green; admin and shop render

## Acceptance criteria
- `composer show sylius/sylius` reports 2.3.x
- All test suites and static analysis pass
- Admin payment method form and shop checkout of the TestApplication render without errors

## Notes
- Resolved versions: Sylius 2.3.2, API Platform 5.0.6, Symfony 7.4.23, Doctrine DBAL 4.5.1 /
  ORM 3.6.9, sylius/twig-hooks 0.14.3, test-application 2.3.0-ALPHA.4.
- No plugin code needed a change for the upgrade; every twig hook the plugin uses still exists.
- **DBAL 4** drops the `(DC2Type:…)` column comments. The plugin has no tables of its own; its
  columns `calculator`, `calculator_configuration`, `tax_category_id` live on the core table
  `sylius_payment_method`. Only the JSON column can carry a comment (`(DC2Type:json)`, written when
  the column was created by DBAL 3 schema tools — the plugin's own migration never wrote one), so the
  plugin ships `Version20261116000000`, which normalises the column to `JSON DEFAULT NULL` (a no-op
  where there was no comment). After it, `doctrine:schema:update --dump-sql` lists nothing on
  `sylius_payment_method`; what it still lists is core only (`sylius_payment_request`,
  `sylius_payment_security_token`, `messenger_messages`).
- Hosts on Sylius 2.2 can no longer install this version of the plugin.

# Admin API (for the MCP gateway)

## Goal

Make the payment fee of a payment method readable and writable through the Sylius Admin API
(`/api/v2/admin/…`), so that `elcuro/sylius-mcp-plugin` — a gateway that only reads the API
definition — can use it. Nothing MCP-specific lives in this plugin: the work is a good Admin API
(fields in the right groups, descriptions, validation that does not depend on the admin form).

The plugin has no entities of its own. It adds three fields to the core `PaymentMethod` through
`PaymentMethodWithFeeTrait` / `PaymentMethodWithFeeInterface`, so the fields go into the groups of
the **core** PaymentMethod resource. No second resource for the same entity.

## Fields per group (core resource `PaymentMethod`, `/admin/payment-methods/{code}`)

| Field | index | show | create | update | Notes |
|---|---|---|---|---|---|
| `calculator` | x | x | x | x | name of the fee calculator; `null` = no fee |
| `calculatorConfiguration` | | x | x | x | JSON object, shape depends on the calculator |
| `taxCategory` | | x | x | x | IRI of a tax category (`/api/v2/admin/tax-categories/{code}`) or `null` |

The mapping targets `Elcuro\SyliusPaymentFeePlugin\Model\PaymentMethodWithFeeInterface`, the
plugin's own interface that only the host's PaymentMethod implements, so the fields appear exactly
when the host entity uses the trait and do not leak into other classes.

Calculators and their configuration (amounts are integers in the smallest currency unit, e.g.
cents: `150` = 1.50):

- `free` — no fee; configuration is ignored.
- `flat_rate` — `{"<CHANNEL_CODE>": {"amount": 150}, …}`; one entry for **every channel of the
  shop** (as in the admin form, which shows every channel, and as the core shipping method rates).
- `country_rate` — `{"<COUNTRY_CODE>": {"currency": "EUR", "amount": 150}, …}`; key = country
  code of a country of the shop, `currency` = currency code of the shop; countries left out pay
  no fee; when the order currency differs, the amount is converted with the shop's exchange rates.

## Read-only and why

- **Fees charged on orders** are order adjustments of type `payment` (label = payment method
  name, details = method code/name). They are history of a placed order, recomputed by the order
  processor while the order is a cart. They stay read-only: the core already exposes them only
  through `GET /admin/orders/{tokenValue}/adjustments?type=payment` and `/admin/adjustments/{id}`
  (core properties are `writable="false"`); the plugin adds nothing writable there.

## Validation moved out of forms

Today the rules live in the calculator configuration form types (`NotBlank`, `Type(integer)` in
`FlatRateConfigurationType` / `CountryRateConfigurationType`) and the calculator choice of the
form. An API write runs no form, so:

- `calculator`: a constraint on the trait property (`RegisteredCalculator`, group `sylius`) —
  `null` or a calculator registered in `elcuro_sylius_payment_fee.registry.calculator`.
- `calculatorConfiguration`: a constraint on the trait property (`CalculatorConfiguration`,
  group `sylius`) that validates the shape for the selected calculator type (`flat_rate`,
  `country_rate`): missing/unknown channel, unknown country, missing amount, amount not an
  integer, negative amount, unknown/missing currency, unexpected keys, entry not an object.
- The duplicated constraints are removed from the form types, so the admin shows each message
  once (the entity violations map onto the form fields). New: a negative amount is refused in
  the admin too (it was accepted before).
- The admin form's PRE_SUBMIT listener ignores an unknown calculator instead of throwing (500).

## Tasks

- [x] Upgrade to Sylius 2.3 (separate spec `sylius-2.3-upgrade.md`)
- [x] `prepend()`: register `config/api_platform` and `config/serialization` when the
      `api_platform` extension exists
- [x] `config/serialization/PaymentMethod.xml` — fields into the core admin groups
- [x] `config/api_platform/properties/PaymentMethod.xml` — descriptions
- [x] Constraints `RegisteredCalculator`, `CalculatorConfiguration` + validators (services), on
      the trait properties; translations in all six languages
- [x] Remove the duplicated constraints from the form types; guard PRE_SUBMIT
- [x] Unit tests of the validators
- [x] Functional API tests (`tests/Functional/Api/Admin/`): GET shows the fields, POST/PUT set
      them, every invalid configuration answers 422, order adjustments are not writable
- [x] `phpunit.xml.dist` with Unit + Functional suites, `tests/bootstrap.php`
- [x] HTTP check (form POST with an admin session): admin payment method form saves and shows each
      message once, on the amount field (negative and empty amount → 422)
- [x] OpenAPI: `calculatorConfiguration` is typed `object` (PHP `array` would be shown as a JSON array)
- [x] README (Admin API section) and CLAUDE.md

## Acceptance criteria

- `GET /api/v2/admin/payment-methods/{code}` returns `calculator`, `calculatorConfiguration`,
  `taxCategory`; the list returns `calculator`.
- `POST` / `PUT` set them; `PUT` without them leaves them unchanged.
- Unknown calculator, missing amount, negative amount, non-integer amount, unknown channel /
  country / currency, missing channel for `flat_rate` → 422 with a message on the property path;
  nothing stored. Never 500.
- The admin form keeps working and shows each validation message once.
- PHPUnit (unit + functional), Behat (non-JS), PHPStan max, ECS green.

## Decisions / open questions

- **`calculator` may be `null`** (= no fee, same as `free`): payment methods created before the
  plugin, or through the API by a client that does not know the plugin, have none; requiring it
  would break those writes. To confirm: should a calculator be mandatory?
- **`flat_rate` requires every channel of the shop**, like the admin form (one amount field per
  channel, each required) and the core shipping method rates. A shop that adds a channel must
  then add the amount before the method can be saved again. Alternative: only the channels the
  method is enabled in (the calculator throws for an order in a channel without an amount).
- **`free` ignores the configuration** and does not validate it: switching a method to `free`
  through `PUT` must not fail because of the configuration the previous calculator left behind
  (the admin form does not clear it either).
- **Negative amounts are now refused in the admin form too** (the form accepted them before;
  a negative fee would be a discount).
- **Custom calculators** added by a host are accepted by name; their configuration is validated
  only when their type is `flat_rate` or `country_rate`.
- **No new filter** on the payment method collection (e.g. by calculator): an administrator rarely
  searches payment methods by fee, the list is short and carries `calculator`.
- **Wrong JSON type of the whole configuration** (`"calculatorConfiguration": "x"`) is refused by API
  Platform's deserialization (the setter takes an array) before validation; the test accepts 400 or 422.
- **Messages** of the configuration rules are the plugin's own (six languages); the generic ones
  ("This field is missing.", "This field was not expected.", "This value should be of type array")
  come from Symfony for a missing/unexpected key inside an entry and an entry that is not an object.
- **MCP:** the plugin extends only the core `PaymentMethod` resource, so it needs no allowlist
  entry of its own in `elcuro_sylius_mcp.resources.enabled`.

## Results

- PHPUnit 53 tests (unit 32 incl. 15 validator cases, functional 21 API tests), Behat 8 scenarios /
  119 steps (non-JS), PHPStan max and ECS: green.

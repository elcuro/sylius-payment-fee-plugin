@country_rate_payment_fee
Feature: Applying country rate payment fee
    In order to charge different payment fees per country
    As a Store Owner
    I want the payment fee to be calculated based on the shipping country

    Background:
        Given the store operates on a single channel in "United States"
        And the store has a product "PHP T-Shirt" priced at "$19.99"
        And the store ships everywhere for Free
        And the store allows paying with "Cash on Delivery"
        And the store also has country "Germany"

    Scenario: Applying payment fee for a configured country
        Given this payment method has a country rate fee "$1.50" for "United States" in "USD" currency
        And there is a customer "john@example.com" that placed an order "#00000001"
        And the customer bought a single "PHP T-Shirt"
        And the customer "John Doe" addressed it to "123 Main St", "10001" "New York" in the "United States" with identical billing address
        And the customer chose "Free" shipping method with "Cash on Delivery" payment
        Then this order should have a "$1.50" payment fee

    Scenario: No payment fee for an unconfigured country
        Given this payment method has a country rate fee "$1.50" for "United States" in "USD" currency
        And there is a customer "john@example.com" that placed an order "#00000002"
        And the customer bought a single "PHP T-Shirt"
        And the customer "John Doe" addressed it to "456 Berlin St", "10115" "Berlin" in the "Germany" with identical billing address
        And the customer chose "Free" shipping method with "Cash on Delivery" payment
        Then this order should not have a payment fee

    Scenario: Applying payment fee with currency conversion
        Given the store has currency "Euro"
        And the exchange rate of "US Dollar" to "Euro" is 0.85
        And this payment method has a country rate fee "1.00 EUR" for "United States" in "EUR" currency
        And there is a customer "john@example.com" that placed an order "#00000003"
        And the customer bought a single "PHP T-Shirt"
        And the customer "John Doe" addressed it to "123 Main St", "10001" "New York" in the "United States" with identical billing address
        And the customer chose "Free" shipping method with "Cash on Delivery" payment
        Then this order should have a payment fee from currency conversion

    Scenario: Applying different fees for different countries
        Given this payment method has a country rate fee "$2.00" for "United States" in "USD" currency
        And this payment method has a country rate fee "$3.00" for "Germany" in "USD" currency
        And there is a customer "john@example.com" that placed an order "#00000004"
        And the customer bought a single "PHP T-Shirt"
        And the customer "John Doe" addressed it to "456 Berlin St", "10115" "Berlin" in the "Germany" with identical billing address
        And the customer chose "Free" shipping method with "Cash on Delivery" payment
        Then this order should have a "$3.00" payment fee

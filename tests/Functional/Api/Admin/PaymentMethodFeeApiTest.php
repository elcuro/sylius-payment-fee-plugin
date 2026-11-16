<?php

declare(strict_types=1);

namespace Tests\Elcuro\SyliusPaymentFeePlugin\Functional\Api\Admin;

use Elcuro\SyliusPaymentFeePlugin\Model\PaymentMethodWithFeeInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Sylius\Component\Addressing\Model\CountryInterface;
use Sylius\Component\Core\Factory\PaymentMethodFactoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Taxation\Model\TaxCategoryInterface;

/** The fee fields on the core PaymentMethod resource: read, written and validated without the admin form. */
final class PaymentMethodFeeApiTest extends AdminApiTestCase
{
    private const URI = '/admin/payment-methods/API_TEST_COD';

    protected function setUp(): void
    {
        parent::setUp();

        $country = $this->factory('country')->createNew();
        self::assertInstanceOf(CountryInterface::class, $country);
        $country->setCode('XA');

        $taxCategory = $this->factory('tax_category')->createNew();
        self::assertInstanceOf(TaxCategoryInterface::class, $taxCategory);
        $taxCategory->setCode('api_test_fee');
        $taxCategory->setName('Api test fee');

        $factory = $this->service('sylius.factory.payment_method', PaymentMethodFactoryInterface::class);
        $paymentMethod = $factory->createWithGateway('offline');
        self::assertInstanceOf(PaymentMethodWithFeeInterface::class, $paymentMethod);
        self::assertInstanceOf(PaymentMethodInterface::class, $paymentMethod);
        $paymentMethod->setCode('API_TEST_COD');
        $paymentMethod->setCurrentLocale('en_US');
        $paymentMethod->setFallbackLocale('en_US');
        $paymentMethod->setName('Api test cash on delivery');
        $paymentMethod->getGatewayConfig()?->setGatewayName('offline');
        $paymentMethod->addChannel($this->channel);
        $paymentMethod->setCalculator('flat_rate');
        $paymentMethod->setCalculatorConfiguration($this->flatRate(300));
        $paymentMethod->setTaxCategory($taxCategory);

        $this->save($country, $taxCategory, $paymentMethod);
    }

    public function testFeeFieldsAreShown(): void
    {
        [$status, $method] = $this->request('GET', self::URI);

        self::assertSame(200, $status);
        self::assertSame('flat_rate', $method['calculator']);
        self::assertSame($this->flatRate(300), $method['calculatorConfiguration']);
        self::assertSame('/api/v2/admin/tax-categories/api_test_fee', $method['taxCategory']);
    }

    public function testListCarriesOnlyTheCalculator(): void
    {
        [$status, $data] = $this->request('GET', '/admin/payment-methods?code=API_TEST_COD');

        self::assertSame(200, $status);
        $members = self::members($data);
        self::assertCount(1, $members);
        self::assertSame('flat_rate', $members[0]['calculator']);
        self::assertArrayNotHasKey('calculatorConfiguration', $members[0]);
        self::assertArrayNotHasKey('taxCategory', $members[0]);
    }

    public function testFeeFieldsAreWrittenOnUpdate(): void
    {
        [$status, $method] = $this->request('PUT', self::URI, [
            'calculator' => 'country_rate',
            'calculatorConfiguration' => ['XA' => ['currency' => 'EUR', 'amount' => 250]],
            'taxCategory' => null,
        ]);

        self::assertSame(200, $status, json_encode(self::violations($method), \JSON_THROW_ON_ERROR));
        self::assertSame('country_rate', $method['calculator']);
        self::assertSame(['XA' => ['currency' => 'EUR', 'amount' => 250]], $method['calculatorConfiguration']);
        self::assertNull($method['taxCategory'] ?? null);

        $stored = $this->storedMethod();
        self::assertSame('country_rate', $stored->getCalculator());
        self::assertNull($stored->getTaxCategory());
    }

    public function testUpdateWithoutFeeFieldsKeepsThem(): void
    {
        [$status, $method] = $this->request('PUT', self::URI, ['enabled' => false]);

        self::assertSame(200, $status);
        self::assertSame('flat_rate', $method['calculator']);
        self::assertSame($this->flatRate(300), $method['calculatorConfiguration']);
        self::assertSame('/api/v2/admin/tax-categories/api_test_fee', $method['taxCategory']);
    }

    public function testSwitchingToFreeKeepsTheOldConfigurationWithoutValidatingIt(): void
    {
        [$status, $method] = $this->request('PUT', self::URI, ['calculator' => 'free']);

        self::assertSame(200, $status);
        self::assertSame('free', $method['calculator']);
    }

    public function testFeeFieldsAreSetOnCreate(): void
    {
        [$status, $method] = $this->request('POST', '/admin/payment-methods', [
            'code' => 'API_TEST_NEW',
            'enabled' => true,
            'channels' => ['/api/v2/admin/channels/API_TEST_WEB'],
            'gatewayConfig' => ['factoryName' => 'offline', 'gatewayName' => 'offline'],
            'translations' => ['en_US' => ['locale' => 'en_US', 'name' => 'Api test new']],
            'calculator' => 'flat_rate',
            'calculatorConfiguration' => $this->flatRate(99),
            'taxCategory' => '/api/v2/admin/tax-categories/api_test_fee',
        ]);

        self::assertSame(201, $status, json_encode(self::violations($method), \JSON_THROW_ON_ERROR));
        self::assertSame('flat_rate', $method['calculator']);
        self::assertSame($this->flatRate(99), $method['calculatorConfiguration']);
        self::assertSame('/api/v2/admin/tax-categories/api_test_fee', $method['taxCategory']);
    }

    /**
     * @param array<string, mixed> $body
     * @param list<string> $paths expected violation property paths
     */
    #[DataProvider('invalidFees')]
    public function testInvalidFeeIsRefusedAndNothingIsStored(array $body, array $paths): void
    {
        // A flat rate needs every channel of the shop (Behat may have left some): only the given ones are broken.
        if (!isset($body['calculator']) && is_array($body['calculatorConfiguration'] ?? null)) {
            $body['calculatorConfiguration'] = array_replace($this->flatRate(1), $body['calculatorConfiguration']);
        }

        [$status, $data] = $this->request('PUT', self::URI, $body);

        self::assertSame(422, $status, json_encode($data, \JSON_THROW_ON_ERROR));
        $violations = self::violations($data);
        self::assertEqualsCanonicalizing($paths, array_keys($violations));
        foreach ($violations as $message) {
            self::assertStringNotContainsString('elcuro_sylius_payment_fee.', $message, 'The message is translated.');
        }

        $this->assertNothingStored();
    }

    /** @return iterable<string, array{array<string, mixed>, list<string>}> */
    public static function invalidFees(): iterable
    {
        yield 'unknown calculator' => [['calculator' => 'ghost'], ['calculator']];
        yield 'flat rate: unknown channel' => [
            ['calculatorConfiguration' => ['GHOST' => ['amount' => 100]]],
            ['calculatorConfiguration[GHOST]'],
        ];
        yield 'flat rate: missing amount' => [
            ['calculatorConfiguration' => ['API_TEST_WEB' => []]],
            ['calculatorConfiguration[API_TEST_WEB][amount]'],
        ];
        yield 'flat rate: null amount' => [
            ['calculatorConfiguration' => ['API_TEST_WEB' => ['amount' => null]]],
            ['calculatorConfiguration[API_TEST_WEB][amount]'],
        ];
        yield 'flat rate: negative amount' => [
            ['calculatorConfiguration' => ['API_TEST_WEB' => ['amount' => -100]]],
            ['calculatorConfiguration[API_TEST_WEB][amount]'],
        ];
        yield 'flat rate: decimal amount' => [
            ['calculatorConfiguration' => ['API_TEST_WEB' => ['amount' => 1.5]]],
            ['calculatorConfiguration[API_TEST_WEB][amount]'],
        ];
        yield 'flat rate: amount as a string' => [
            ['calculatorConfiguration' => ['API_TEST_WEB' => ['amount' => '150']]],
            ['calculatorConfiguration[API_TEST_WEB][amount]'],
        ];
        yield 'flat rate: entry is a number' => [
            ['calculatorConfiguration' => ['API_TEST_WEB' => 150]],
            ['calculatorConfiguration[API_TEST_WEB]'],
        ];
        yield 'country rate: unknown country' => [
            ['calculator' => 'country_rate', 'calculatorConfiguration' => ['ZZ' => ['currency' => 'EUR', 'amount' => 100]]],
            ['calculatorConfiguration[ZZ]'],
        ];
        yield 'country rate: unknown currency' => [
            ['calculator' => 'country_rate', 'calculatorConfiguration' => ['XA' => ['currency' => 'XXX', 'amount' => 100]]],
            ['calculatorConfiguration[XA][currency]'],
        ];
        yield 'country rate: missing currency and amount' => [
            ['calculator' => 'country_rate', 'calculatorConfiguration' => ['XA' => []]],
            ['calculatorConfiguration[XA][currency]', 'calculatorConfiguration[XA][amount]'],
        ];
        yield 'country rate: unexpected key' => [
            ['calculator' => 'country_rate', 'calculatorConfiguration' => ['XA' => ['currency' => 'EUR', 'amount' => 1, 'percent' => 5]]],
            ['calculatorConfiguration[XA][percent]'],
        ];
    }

    public function testFlatRateWithoutAnAmountForEveryChannelIsRefused(): void
    {
        [$status, $data] = $this->request('PUT', self::URI, ['calculatorConfiguration' => []]);

        self::assertSame(422, $status);
        self::assertEqualsCanonicalizing(
            array_map(static fn (string $code): string => 'calculatorConfiguration[' . $code . ']', array_keys($this->flatRate(1))),
            array_keys(self::violations($data)),
        );
        $this->assertNothingStored();
    }

    public function testConfigurationThatIsNotAnObjectIsRefused(): void
    {
        [$status] = $this->request('PUT', self::URI, ['calculatorConfiguration' => 'flat 1.50']);

        self::assertContains($status, [400, 422]);
        $this->assertNothingStored();
    }

    public function testChargedFeesOnOrdersAreNotWritable(): void
    {
        foreach (['PUT', 'PATCH', 'DELETE'] as $method) {
            [$status] = $this->request($method, '/admin/adjustments/1', 'DELETE' === $method ? null : ['amount' => 0]);
            self::assertSame(405, $status, $method);
        }

        [$status] = $this->request('POST', '/admin/adjustments', ['type' => 'payment', 'amount' => 100]);
        self::assertContains($status, [404, 405]);
    }

    /** @return array<string, array{amount: int}> an amount for every channel of the shop */
    private function flatRate(int $amount): array
    {
        $configuration = [];
        foreach ($this->entityManager->getRepository(ChannelInterface::class)->findAll() as $channel) {
            $configuration[(string) $channel->getCode()] = ['amount' => $amount];
        }
        ksort($configuration);

        return $configuration;
    }

    private function assertNothingStored(): void
    {
        $stored = $this->storedMethod();
        self::assertSame('flat_rate', $stored->getCalculator());
        self::assertSame($this->flatRate(300), $stored->getCalculatorConfiguration());
    }

    private function storedMethod(): PaymentMethodWithFeeInterface
    {
        $method = $this->entityManager->getRepository(PaymentMethodInterface::class)->findOneBy(['code' => 'API_TEST_COD']);
        self::assertInstanceOf(PaymentMethodWithFeeInterface::class, $method);

        return $method;
    }
}

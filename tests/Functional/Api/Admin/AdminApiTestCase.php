<?php

declare(strict_types=1);

namespace Tests\Elcuro\SyliusPaymentFeePlugin\Functional\Api\Admin;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Admin API requests against the test application with a JWT of a fresh API administrator.
 * Every test runs in a transaction that is rolled back, so the test database keeps whatever
 * the last Behat run left (codes used here are prefixed to stay clear of it).
 */
abstract class AdminApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected EntityManagerInterface $entityManager;

    protected ChannelInterface $channel;

    private string $token;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // One kernel for the whole test: the requests share the connection and its transaction.
        $this->client->disableReboot();

        $this->entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
        $this->connection()->beginTransaction();

        $this->createChannel();
        $this->token = $this->createApiAdministrator();
    }

    protected function tearDown(): void
    {
        $connection = $this->connection();
        while ($connection->isTransactionActive()) {
            $connection->rollBack();
        }

        parent::tearDown();
    }

    /**
     * @param array<string, mixed>|null $body
     *
     * @return array{int, array<string, mixed>}
     */
    protected function request(string $method, string $uri, ?array $body = null): array
    {
        $this->client->request(
            $method,
            '/api/v2' . $uri,
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
                'HTTP_ACCEPT' => 'application/ld+json',
                'CONTENT_TYPE' => 'PATCH' === $method ? 'application/merge-patch+json' : 'application/ld+json',
            ],
            content: null === $body ? null : json_encode($body, \JSON_THROW_ON_ERROR),
        );

        $response = $this->client->getResponse();
        $content = (string) $response->getContent();
        /** @var array<string, mixed> $data */
        $data = '' === $content ? [] : json_decode($content, true, flags: \JSON_THROW_ON_ERROR);

        // Every request ends with the identity map of the previous one; read fresh state.
        $this->entityManager->clear();

        return [$response->getStatusCode(), $data];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, string> property path => message
     */
    protected static function violations(array $data): array
    {
        $violations = [];
        foreach ((array) ($data['violations'] ?? []) as $violation) {
            if (is_array($violation)) {
                $violations[(string) $violation['propertyPath']] = (string) $violation['message'];
            }
        }

        return $violations;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<array<string, mixed>>
     */
    protected static function members(array $data): array
    {
        /** @var list<array<string, mixed>> $members */
        $members = $data['hydra:member'] ?? $data['member'] ?? [];

        return $members;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    protected function service(string $id, string $class): object
    {
        $service = static::getContainer()->get($id);
        self::assertInstanceOf($class, $service);

        return $service;
    }

    /**
     * @param string $resource a core resource name ("channel") or a full factory service id
     *
     * @return FactoryInterface<object>
     */
    protected function factory(string $resource): FactoryInterface
    {
        $id = str_contains($resource, '.') ? $resource : 'sylius.factory.' . $resource;
        /** @var FactoryInterface<object> $factory */
        $factory = $this->service($id, FactoryInterface::class);

        return $factory;
    }

    protected function save(object ...$objects): void
    {
        foreach ($objects as $object) {
            $this->entityManager->persist($object);
        }
        $this->entityManager->flush();
    }

    private function connection(): Connection
    {
        return $this->service('doctrine.dbal.default_connection', Connection::class);
    }

    private function createChannel(): void
    {
        $locale = $this->entityManager->getRepository(LocaleInterface::class)->findOneBy(['code' => 'en_US']);
        if (!$locale instanceof LocaleInterface) {
            $locale = $this->factory('locale')->createNew();
            self::assertInstanceOf(LocaleInterface::class, $locale);
            $locale->setCode('en_US');
        }

        $currency = $this->entityManager->getRepository(CurrencyInterface::class)->findOneBy(['code' => 'EUR']);
        if (!$currency instanceof CurrencyInterface) {
            $currency = $this->factory('currency')->createNew();
            self::assertInstanceOf(CurrencyInterface::class, $currency);
            $currency->setCode('EUR');
        }

        $channel = $this->factory('channel')->createNew();
        self::assertInstanceOf(ChannelInterface::class, $channel);
        $channel->setCode('API_TEST_WEB');
        $channel->setName('API test web');
        $channel->setTaxCalculationStrategy('order_items_based');
        $channel->setBaseCurrency($currency);
        $channel->addCurrency($currency);
        $channel->setDefaultLocale($locale);
        $channel->addLocale($locale);
        $this->channel = $channel;

        $this->save($locale, $currency, $channel);
    }

    private function createApiAdministrator(): string
    {
        $admin = $this->factory('admin_user')->createNew();
        self::assertInstanceOf(AdminUserInterface::class, $admin);
        $admin->setUsername('api-test-admin');
        $admin->setEmail('api-test-admin@example.com');
        $admin->setPlainPassword('api-test');
        $admin->setLocaleCode('en_US');
        $admin->setEnabled(true);
        $admin->addRole('ROLE_ADMINISTRATION_ACCESS');
        $admin->addRole('ROLE_API_ACCESS');
        $this->save($admin);

        return $this->service('lexik_jwt_authentication.jwt_manager', JWTTokenManagerInterface::class)->create($admin);
    }
}

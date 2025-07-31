<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Integration;

use EdgeBinder\EdgeBinder;
use EdgeBinder\Exception\AdapterException;
use EdgeBinder\Registry\AdapterRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * Integration tests for the complete adapter registry workflow.
 *
 * These tests verify that the entire flow from adapter registration
 * to EdgeBinder creation and usage works correctly.
 */
final class AdapterRegistryIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        // Clear registry before each test for clean isolation
        AdapterRegistry::clear();
    }

    protected function tearDown(): void
    {
        // Clear registry after each test for clean isolation
        AdapterRegistry::clear();
    }

    public function testCompleteWorkflowFromRegistrationToUsage(): void
    {
        // Step 1: Register a mock adapter factory
        $mockFactory = new MockAdapterFactory('redis');
        AdapterRegistry::register($mockFactory);

        // Verify adapter is registered
        $this->assertTrue(AdapterRegistry::hasAdapter('redis'));
        $this->assertContains('redis', AdapterRegistry::getRegisteredTypes());

        // Step 2: Create container mock with client service
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
            ->with('redis.client.cache')
            ->willReturn(new \stdClass()); // Mock Redis client

        // Step 3: Create EdgeBinder from configuration
        $config = [
            'adapter' => 'redis',
            'redis_client' => 'redis.client.cache',
            'ttl' => 3600,
            'prefix' => 'edgebinder:',
        ];

        $edgeBinder = EdgeBinder::fromConfiguration($config, $container);

        // Step 4: Verify EdgeBinder works with the adapter
        $this->assertInstanceOf(EdgeBinder::class, $edgeBinder);

        // Create test entities
        $user = new TestEntity('user-123', 'User');
        $project = new TestEntity('project-456', 'Project');

        // Step 5: Test binding creation
        $binding = $edgeBinder->bind(
            from: $user,
            to: $project,
            type: 'has_access',
            metadata: ['level' => 'admin']
        );

        $this->assertNotNull($binding);
        $this->assertEquals('has_access', $binding->getType());
        $this->assertEquals(['level' => 'admin'], $binding->getMetadata());

        // Step 6: Test querying
        $bindings = $edgeBinder->findBindingsFor($user);
        $this->assertCount(1, $bindings);
        $this->assertEquals($binding->getId(), $bindings[0]->getId());

        // Step 7: Test relationship checking
        $this->assertTrue($edgeBinder->areBound($user, $project, 'has_access'));
        $this->assertFalse($edgeBinder->areBound($user, $project, 'different_type'));
    }

    public function testMultipleAdapterTypesCanBeRegistered(): void
    {
        // Register multiple adapter types
        AdapterRegistry::register(new MockAdapterFactory('redis'));
        AdapterRegistry::register(new MockAdapterFactory('weaviate'));
        AdapterRegistry::register(new MockAdapterFactory('janus'));

        // Verify all are registered
        $types = AdapterRegistry::getRegisteredTypes();
        $this->assertContains('redis', $types);
        $this->assertContains('weaviate', $types);
        $this->assertContains('janus', $types);

        // Create EdgeBinder instances with different adapters
        $container = $this->createMock(ContainerInterface::class);

        $redisEdgeBinder = EdgeBinder::fromConfiguration(
            ['adapter' => 'redis', 'redis_client' => 'redis.client'],
            $container
        );

        $weaviateEdgeBinder = EdgeBinder::fromConfiguration(
            ['adapter' => 'weaviate', 'weaviate_client' => 'weaviate.client'],
            $container
        );

        $this->assertInstanceOf(EdgeBinder::class, $redisEdgeBinder);
        $this->assertInstanceOf(EdgeBinder::class, $weaviateEdgeBinder);

        // Verify they use different adapters
        $this->assertNotSame(
            $redisEdgeBinder->getStorageAdapter(),
            $weaviateEdgeBinder->getStorageAdapter()
        );
    }

    public function testAdapterFactoryReceivesCorrectConfigurationStructure(): void
    {
        // Create a factory that validates the configuration structure
        $factory = new class implements \EdgeBinder\Registry\AdapterFactoryInterface {
            public array $receivedConfig = [];

            public function createAdapter(array $config): \EdgeBinder\Contracts\PersistenceAdapterInterface
            {
                $this->receivedConfig = $config;

                // Validate the expected structure
                if (!isset($config['instance'], $config['global'], $config['container'])) {
                    throw new \InvalidArgumentException('Invalid configuration structure');
                }

                return new MockAdapter($config);
            }

            public function getAdapterType(): string
            {
                return 'test';
            }
        };

        AdapterRegistry::register($factory);

        $container = $this->createMock(ContainerInterface::class);
        $config = [
            'adapter' => 'test',
            'test_client' => 'test.client.default',
            'host' => 'localhost',
            'port' => 1234,
        ];
        $globalConfig = ['debug' => true];

        EdgeBinder::fromConfiguration($config, $container, $globalConfig);

        // Verify the factory received the correct configuration structure
        $this->assertArrayHasKey('instance', $factory->receivedConfig);
        $this->assertArrayHasKey('global', $factory->receivedConfig);
        $this->assertArrayHasKey('container', $factory->receivedConfig);

        // Verify instance config contains our original config
        $this->assertEquals($config, $factory->receivedConfig['instance']);

        // Verify global config was passed
        $this->assertEquals($globalConfig, $factory->receivedConfig['global']);

        // Verify container was passed
        $this->assertSame($container, $factory->receivedConfig['container']);
    }

    public function testErrorHandlingForAdapterCreationFailure(): void
    {
        // Register a factory that throws an exception
        $factory = new class implements \EdgeBinder\Registry\AdapterFactoryInterface {
            public function createAdapter(array $config): \EdgeBinder\Contracts\PersistenceAdapterInterface
            {
                throw new \RuntimeException('Database connection failed');
            }

            public function getAdapterType(): string
            {
                return 'failing';
            }
        };

        AdapterRegistry::register($factory);

        $container = $this->createMock(ContainerInterface::class);
        $config = ['adapter' => 'failing'];

        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage("Failed to create adapter of type 'failing': Database connection failed");

        EdgeBinder::fromConfiguration($config, $container);
    }

    public function testConfigurationValidationWithHelpfulErrorMessages(): void
    {
        // Register an adapter so we have available types
        AdapterRegistry::register(new MockAdapterFactory('redis'));

        $container = $this->createMock(ContainerInterface::class);

        // Test missing adapter key
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Configuration must contain 'adapter' key");
        $this->expectExceptionMessage("Available types: redis");

        EdgeBinder::fromConfiguration([], $container);
    }
}

/**
 * Simple test entity for integration testing.
 */
class TestEntity
{
    public function __construct(
        private readonly string $id,
        private readonly string $type
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }
}

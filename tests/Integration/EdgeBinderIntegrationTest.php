<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Integration;

use EdgeBinder\Contracts\PersistenceAdapterInterface;
use EdgeBinder\EdgeBinder;
use EdgeBinder\Exception\AdapterException;
use EdgeBinder\Persistence\InMemory\InMemoryAdapter;
use EdgeBinder\Registry\AdapterFactoryInterface;
use EdgeBinder\Registry\AdapterRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * Integration tests for EdgeBinder core functionality and adapter registry integration.
 */
final class EdgeBinderIntegrationTest extends TestCase
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

    public function testFromConfigurationCreatesEdgeBinderWithRegisteredAdapter(): void
    {
        // Register an InMemory adapter factory
        $inMemoryAdapter = new InMemoryAdapter();
        $mockFactory = $this->createMockAdapterFactory('test', $inMemoryAdapter);
        AdapterRegistry::register($mockFactory);

        // Create container mock
        $container = $this->createMock(ContainerInterface::class);

        // Configuration
        $config = [
            'adapter' => 'test',
            'test_client' => 'test.client.default',
            'host' => 'localhost',
            'port' => 1234,
        ];

        // Create EdgeBinder from configuration
        $edgeBinder = EdgeBinder::fromConfiguration($config, $container);

        // Verify it's an EdgeBinder instance
        $this->assertInstanceOf(EdgeBinder::class, $edgeBinder);

        // Verify the adapter was used by checking storage adapter
        $this->assertSame($inMemoryAdapter, $edgeBinder->getStorageAdapter());
    }

    public function testFromConfigurationWithGlobalConfig(): void
    {
        // Register an InMemory adapter factory that expects global config
        $inMemoryAdapter = new InMemoryAdapter();
        $mockFactory = $this->createMockAdapterFactoryWithGlobalConfig('test', $inMemoryAdapter);
        AdapterRegistry::register($mockFactory);

        // Create container mock
        $container = $this->createMock(ContainerInterface::class);

        // Configuration
        $config = [
            'adapter' => 'test',
            'test_client' => 'test.client.default',
        ];

        $globalConfig = [
            'default_metadata_validation' => true,
            'entity_extraction_strategy' => 'reflection',
        ];

        // Create EdgeBinder from configuration
        $edgeBinder = EdgeBinder::fromConfiguration($config, $container, $globalConfig);

        // Verify it's an EdgeBinder instance
        $this->assertInstanceOf(EdgeBinder::class, $edgeBinder);
        $this->assertSame($inMemoryAdapter, $edgeBinder->getStorageAdapter());
    }

    public function testFromConfigurationThrowsExceptionForMissingAdapterKey(): void
    {
        $container = $this->createMock(ContainerInterface::class);

        // Configuration missing 'adapter' key
        $config = [
            'host' => 'localhost',
            'port' => 1234,
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Configuration must contain 'adapter' key");

        EdgeBinder::fromConfiguration($config, $container);
    }

    public function testFromConfigurationThrowsExceptionForInvalidAdapterType(): void
    {
        $container = $this->createMock(ContainerInterface::class);

        // Configuration with invalid adapter type
        $config = [
            'adapter' => 123, // Should be string
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Adapter type must be a non-empty string');

        EdgeBinder::fromConfiguration($config, $container);
    }

    public function testFromConfigurationThrowsExceptionForEmptyAdapterType(): void
    {
        $container = $this->createMock(ContainerInterface::class);

        // Configuration with empty adapter type
        $config = [
            'adapter' => '',
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Adapter type must be a non-empty string');

        EdgeBinder::fromConfiguration($config, $container);
    }

    public function testFromConfigurationThrowsExceptionForUnregisteredAdapter(): void
    {
        $container = $this->createMock(ContainerInterface::class);

        // Configuration with unregistered adapter type
        $config = [
            'adapter' => 'nonexistent',
        ];

        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage("Adapter factory for type 'nonexistent' not found");

        EdgeBinder::fromConfiguration($config, $container);
    }

    public function testFromAdapterCreatesEdgeBinderInstance(): void
    {
        $inMemoryAdapter = new InMemoryAdapter();

        $edgeBinder = EdgeBinder::fromAdapter($inMemoryAdapter);

        $this->assertInstanceOf(EdgeBinder::class, $edgeBinder);
        $this->assertSame($inMemoryAdapter, $edgeBinder->getStorageAdapter());
    }

    public function testBackwardCompatibilityWithDirectConstructor(): void
    {
        $inMemoryAdapter = new InMemoryAdapter();

        // Direct constructor should still work
        $edgeBinder = new EdgeBinder($inMemoryAdapter);

        $this->assertInstanceOf(EdgeBinder::class, $edgeBinder);
        $this->assertSame($inMemoryAdapter, $edgeBinder->getStorageAdapter());
    }

    public function testAdapterFactoryReceivesCorrectConfiguration(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $inMemoryAdapter = new InMemoryAdapter();

        // Create a factory that verifies the configuration structure
        $mockFactory = $this->createMock(AdapterFactoryInterface::class);
        $mockFactory->method('getAdapterType')->willReturn('test');
        $mockFactory->expects($this->once())
            ->method('createAdapter')
            ->with($this->callback(function (array $config) use ($container): bool {
                // Verify the configuration structure matches AdapterFactoryInterface expectations
                $this->assertArrayHasKey('instance', $config);
                $this->assertArrayHasKey('global', $config);
                $this->assertArrayHasKey('container', $config);

                // Verify instance config contains our original config
                $this->assertEquals('test', $config['instance']['adapter']);
                $this->assertEquals('test.client.default', $config['instance']['test_client']);
                $this->assertEquals('localhost', $config['instance']['host']);

                // Verify container is passed through
                $this->assertSame($container, $config['container']);

                return true;
            }))
            ->willReturn($inMemoryAdapter);

        AdapterRegistry::register($mockFactory);

        $config = [
            'adapter' => 'test',
            'test_client' => 'test.client.default',
            'host' => 'localhost',
        ];

        $globalConfig = ['some' => 'global_config'];

        EdgeBinder::fromConfiguration($config, $container, $globalConfig);
    }

    private function createMockAdapterFactory(string $type, PersistenceAdapterInterface $adapter): AdapterFactoryInterface
    {
        $factory = $this->createMock(AdapterFactoryInterface::class);
        $factory->method('getAdapterType')->willReturn($type);
        $factory->method('createAdapter')->willReturn($adapter);

        return $factory;
    }

    private function createMockAdapterFactoryWithGlobalConfig(string $type, PersistenceAdapterInterface $adapter): AdapterFactoryInterface
    {
        $factory = $this->createMock(AdapterFactoryInterface::class);
        $factory->method('getAdapterType')->willReturn($type);
        $factory->expects($this->once())
            ->method('createAdapter')
            ->with($this->callback(function (array $config): bool {
                // Verify global config is passed
                $this->assertArrayHasKey('global', $config);
                $this->assertEquals('reflection', $config['global']['entity_extraction_strategy']);

                return true;
            }))
            ->willReturn($adapter);

        return $factory;
    }
}

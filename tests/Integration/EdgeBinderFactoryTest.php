<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Integration;

use EdgeBinder\Contracts\BindingInterface;
use EdgeBinder\Contracts\PersistenceAdapterInterface;
use EdgeBinder\Contracts\QueryBuilderInterface;
use EdgeBinder\EdgeBinder;
use EdgeBinder\Exception\AdapterException;
use EdgeBinder\Registry\AdapterFactoryInterface;
use EdgeBinder\Registry\AdapterRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * Integration tests for EdgeBinder factory methods and adapter registry integration.
 */
final class EdgeBinderFactoryTest extends TestCase
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
        // Register a mock adapter factory
        $mockAdapter = $this->createMockAdapter();
        $mockFactory = $this->createMockAdapterFactory('test', $mockAdapter);
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
        $this->assertSame($mockAdapter, $edgeBinder->getStorageAdapter());
    }

    public function testFromConfigurationWithGlobalConfig(): void
    {
        // Register a mock adapter factory that expects global config
        $mockAdapter = $this->createMockAdapter();
        $mockFactory = $this->createMockAdapterFactoryWithGlobalConfig('test', $mockAdapter);
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
        $this->assertSame($mockAdapter, $edgeBinder->getStorageAdapter());
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
        $this->expectExceptionMessage("Adapter type must be a non-empty string");

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
        $this->expectExceptionMessage("Adapter type must be a non-empty string");

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
        $mockAdapter = $this->createMockAdapter();

        $edgeBinder = EdgeBinder::fromAdapter($mockAdapter);

        $this->assertInstanceOf(EdgeBinder::class, $edgeBinder);
        $this->assertSame($mockAdapter, $edgeBinder->getStorageAdapter());
    }

    public function testBackwardCompatibilityWithDirectConstructor(): void
    {
        $mockAdapter = $this->createMockAdapter();

        // Direct constructor should still work
        $edgeBinder = new EdgeBinder($mockAdapter);

        $this->assertInstanceOf(EdgeBinder::class, $edgeBinder);
        $this->assertSame($mockAdapter, $edgeBinder->getStorageAdapter());
    }

    public function testAdapterFactoryReceivesCorrectConfiguration(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $mockAdapter = $this->createMockAdapter();

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
            ->willReturn($mockAdapter);

        AdapterRegistry::register($mockFactory);

        $config = [
            'adapter' => 'test',
            'test_client' => 'test.client.default',
            'host' => 'localhost',
        ];

        $globalConfig = ['some' => 'global_config'];

        EdgeBinder::fromConfiguration($config, $container, $globalConfig);
    }

    private function createMockAdapter(): PersistenceAdapterInterface
    {
        return $this->createMock(PersistenceAdapterInterface::class);
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

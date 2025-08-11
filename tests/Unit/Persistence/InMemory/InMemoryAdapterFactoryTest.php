<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Unit\Persistence\InMemory;

use EdgeBinder\Contracts\PersistenceAdapterInterface;
use EdgeBinder\Persistence\InMemory\InMemoryAdapter;
use EdgeBinder\Persistence\InMemory\InMemoryAdapterFactory;
use EdgeBinder\Registry\AdapterConfiguration;
use EdgeBinder\Registry\AdapterFactoryInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * Tests for InMemoryAdapterFactory.
 */
final class InMemoryAdapterFactoryTest extends TestCase
{
    private InMemoryAdapterFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new InMemoryAdapterFactory();
    }

    public function testImplementsAdapterFactoryInterface(): void
    {
        $this->assertInstanceOf(AdapterFactoryInterface::class, $this->factory);
    }

    public function testGetAdapterType(): void
    {
        $this->assertEquals('inmemory', $this->factory->getAdapterType());
    }

    public function testCreateAdapterWithMinimalConfig(): void
    {
        $config = new AdapterConfiguration(
            instance: ['adapter' => 'inmemory'],
            global: [],
            container: $this->createMock(ContainerInterface::class)
        );

        $adapter = $this->factory->createAdapter($config);

        $this->assertInstanceOf(PersistenceAdapterInterface::class, $adapter);
        $this->assertInstanceOf(InMemoryAdapter::class, $adapter);
    }

    public function testCreateAdapterWithEmptyConfig(): void
    {
        $config = new AdapterConfiguration(
            instance: [],
            global: [],
            container: $this->createMock(ContainerInterface::class)
        );

        $adapter = $this->factory->createAdapter($config);

        $this->assertInstanceOf(PersistenceAdapterInterface::class, $adapter);
        $this->assertInstanceOf(InMemoryAdapter::class, $adapter);
    }

    public function testCreateAdapterWithFullConfig(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $config = new AdapterConfiguration(
            instance: [
                'adapter' => 'inmemory',
                'some_ignored_setting' => 'value',
                'another_ignored_setting' => 123,
            ],
            global: [
                'debug' => true,
                'some_global_setting' => 'global_value',
            ],
            container: $container
        );

        $adapter = $this->factory->createAdapter($config);

        $this->assertInstanceOf(PersistenceAdapterInterface::class, $adapter);
        $this->assertInstanceOf(InMemoryAdapter::class, $adapter);
    }

    public function testCreateAdapterReturnsNewInstanceEachTime(): void
    {
        $config = new AdapterConfiguration(
            instance: ['adapter' => 'inmemory'],
            global: [],
            container: $this->createMock(ContainerInterface::class)
        );

        $adapter1 = $this->factory->createAdapter($config);
        $adapter2 = $this->factory->createAdapter($config);

        $this->assertInstanceOf(InMemoryAdapter::class, $adapter1);
        $this->assertInstanceOf(InMemoryAdapter::class, $adapter2);
        $this->assertNotSame($adapter1, $adapter2);
    }

    public function testCreatedAdapterIsFullyFunctional(): void
    {
        $config = new AdapterConfiguration(
            instance: ['adapter' => 'inmemory'],
            global: [],
            container: $this->createMock(ContainerInterface::class)
        );

        $adapter = $this->factory->createAdapter($config);

        // Test that the adapter works by performing basic operations
        $this->assertNull($adapter->find('nonexistent'));
        $this->assertEquals([], $adapter->findByEntity('User', 'user-1'));
        $this->assertEquals([], $adapter->validateAndNormalizeMetadata([]));
        $this->assertEquals('test-id', $adapter->extractEntityId(new class {
            public function getId(): string
            {
                return 'test-id';
            }
        }));
        $this->assertEquals('stdClass', $adapter->extractEntityType(new \stdClass()));
    }
}

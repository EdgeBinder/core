<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Unit\Registry;

use EdgeBinder\Contracts\PersistenceAdapterInterface;
use EdgeBinder\Exception\AdapterException;
use EdgeBinder\Persistence\InMemory\InMemoryAdapter;
use EdgeBinder\Registry\AdapterConfiguration;
use EdgeBinder\Registry\AdapterFactoryInterface;
use EdgeBinder\Registry\AdapterRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class AdapterRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        // Clear registry before each test to ensure clean state
        AdapterRegistry::clear();
    }

    protected function tearDown(): void
    {
        // Clear registry after each test to prevent test pollution
        AdapterRegistry::clear();
    }

    public function testRegisterAdapterFactory(): void
    {
        $factory = $this->createMockFactory('test_adapter');

        AdapterRegistry::register($factory);

        $this->assertTrue(AdapterRegistry::hasAdapter('test_adapter'));
        $this->assertContains('test_adapter', AdapterRegistry::getRegisteredTypes());
        $this->assertSame($factory, AdapterRegistry::getFactory('test_adapter'));
    }

    public function testRegisterIsIdempotent(): void
    {
        $factory1 = $this->createMockFactory('test_adapter');
        $factory2 = $this->createMockFactory('test_adapter');

        // Register the first factory
        AdapterRegistry::register($factory1);
        $this->assertTrue(AdapterRegistry::hasAdapter('test_adapter'));
        $this->assertSame($factory1, AdapterRegistry::getFactory('test_adapter'));

        // Register the second factory with the same type - should be ignored
        AdapterRegistry::register($factory2);

        // Should still have the first factory, not the second
        $this->assertTrue(AdapterRegistry::hasAdapter('test_adapter'));
        $this->assertSame($factory1, AdapterRegistry::getFactory('test_adapter'));
        $this->assertNotSame($factory2, AdapterRegistry::getFactory('test_adapter'));

        // Should still only have one registered type
        $this->assertCount(1, AdapterRegistry::getRegisteredTypes());
    }

    public function testCreateAdapterSuccess(): void
    {
        $inMemoryAdapter = new InMemoryAdapter();
        $factory = $this->createMockFactory('test_adapter', $inMemoryAdapter);

        AdapterRegistry::register($factory);

        $config = new AdapterConfiguration(
            instance: ['adapter' => 'test_adapter'],
            global: [],
            container: $this->createMock(ContainerInterface::class)
        );

        $result = AdapterRegistry::create('test_adapter', $config);

        $this->assertSame($inMemoryAdapter, $result);
    }

    public function testCreateUnregisteredAdapterThrowsException(): void
    {
        $config = new AdapterConfiguration(
            instance: ['adapter' => 'unknown_adapter'],
            global: [],
            container: $this->createMock(ContainerInterface::class)
        );

        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage("Adapter factory for type 'unknown_adapter' not found. No adapters are currently registered.");

        AdapterRegistry::create('unknown_adapter', $config);
    }

    public function testCreateAdapterWithAvailableTypesInErrorMessage(): void
    {
        $factory1 = $this->createMockFactory('adapter1');
        $factory2 = $this->createMockFactory('adapter2');

        AdapterRegistry::register($factory1);
        AdapterRegistry::register($factory2);

        $config = new AdapterConfiguration(
            instance: ['adapter' => 'unknown_adapter'],
            global: [],
            container: $this->createMock(ContainerInterface::class)
        );

        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage("Adapter factory for type 'unknown_adapter' not found. Available types: adapter1, adapter2");

        AdapterRegistry::create('unknown_adapter', $config);
    }

    public function testCreateAdapterFactoryThrowsAdapterException(): void
    {
        $factory = $this->createMock(AdapterFactoryInterface::class);
        $factory->method('getAdapterType')->willReturn('test_adapter');
        $factory->method('createAdapter')->willThrowException(
            AdapterException::invalidConfiguration('test_adapter', 'Missing required config')
        );

        AdapterRegistry::register($factory);

        $config = new AdapterConfiguration(
            instance: ['adapter' => 'test_adapter'],
            global: [],
            container: $this->createMock(ContainerInterface::class)
        );

        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage("Invalid configuration for adapter type 'test_adapter': Missing required config");

        AdapterRegistry::create('test_adapter', $config);
    }

    public function testCreateAdapterFactoryThrowsGenericException(): void
    {
        $factory = $this->createMock(AdapterFactoryInterface::class);
        $factory->method('getAdapterType')->willReturn('test_adapter');
        $factory->method('createAdapter')->willThrowException(
            new \RuntimeException('Connection failed')
        );

        AdapterRegistry::register($factory);

        $config = new AdapterConfiguration(
            instance: ['adapter' => 'test_adapter'],
            global: [],
            container: $this->createMock(ContainerInterface::class)
        );

        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage("Failed to create adapter of type 'test_adapter': Connection failed");

        AdapterRegistry::create('test_adapter', $config);
    }

    public function testHasAdapterReturnsFalseForUnregistered(): void
    {
        $this->assertFalse(AdapterRegistry::hasAdapter('unknown_adapter'));
    }

    public function testGetRegisteredTypesEmptyInitially(): void
    {
        $this->assertEmpty(AdapterRegistry::getRegisteredTypes());
    }

    public function testGetRegisteredTypesWithMultipleAdapters(): void
    {
        $factory1 = $this->createMockFactory('adapter1');
        $factory2 = $this->createMockFactory('adapter2');
        $factory3 = $this->createMockFactory('adapter3');

        AdapterRegistry::register($factory1);
        AdapterRegistry::register($factory2);
        AdapterRegistry::register($factory3);

        $types = AdapterRegistry::getRegisteredTypes();

        $this->assertCount(3, $types);
        $this->assertContains('adapter1', $types);
        $this->assertContains('adapter2', $types);
        $this->assertContains('adapter3', $types);
    }

    public function testUnregisterExistingAdapter(): void
    {
        $factory = $this->createMockFactory('test_adapter');

        AdapterRegistry::register($factory);
        $this->assertTrue(AdapterRegistry::hasAdapter('test_adapter'));

        $result = AdapterRegistry::unregister('test_adapter');

        $this->assertTrue($result);
        $this->assertFalse(AdapterRegistry::hasAdapter('test_adapter'));
        $this->assertNotContains('test_adapter', AdapterRegistry::getRegisteredTypes());
    }

    public function testUnregisterNonExistentAdapter(): void
    {
        $result = AdapterRegistry::unregister('unknown_adapter');

        $this->assertFalse($result);
    }

    public function testClearRemovesAllAdapters(): void
    {
        $factory1 = $this->createMockFactory('adapter1');
        $factory2 = $this->createMockFactory('adapter2');

        AdapterRegistry::register($factory1);
        AdapterRegistry::register($factory2);

        $this->assertCount(2, AdapterRegistry::getRegisteredTypes());

        AdapterRegistry::clear();

        $this->assertEmpty(AdapterRegistry::getRegisteredTypes());
        $this->assertFalse(AdapterRegistry::hasAdapter('adapter1'));
        $this->assertFalse(AdapterRegistry::hasAdapter('adapter2'));
    }

    public function testGetFactoryReturnsNullForUnregistered(): void
    {
        $this->assertNull(AdapterRegistry::getFactory('unknown_adapter'));
    }

    public function testRegisterMultipleTimesWithSameFactoryInstance(): void
    {
        $factory = $this->createMockFactory('test_adapter');

        // Register the same factory instance multiple times
        AdapterRegistry::register($factory);
        AdapterRegistry::register($factory);
        AdapterRegistry::register($factory);

        // Should still work without errors
        $this->assertTrue(AdapterRegistry::hasAdapter('test_adapter'));
        $this->assertSame($factory, AdapterRegistry::getFactory('test_adapter'));
        $this->assertCount(1, AdapterRegistry::getRegisteredTypes());
    }

    public function testAutoRegistrationScenario(): void
    {
        // Simulate auto-registration scenario where adapters check if already registered
        $factory1 = $this->createMockFactory('auto_adapter');
        $factory2 = $this->createMockFactory('auto_adapter');

        // First auto-registration attempt
        if (!AdapterRegistry::hasAdapter('auto_adapter')) {
            AdapterRegistry::register($factory1);
        }

        $this->assertTrue(AdapterRegistry::hasAdapter('auto_adapter'));
        $this->assertSame($factory1, AdapterRegistry::getFactory('auto_adapter'));

        // Second auto-registration attempt - simulate the pattern but since we know
        // the adapter is already registered, we test the idempotent behavior directly
        AdapterRegistry::register($factory2); // This should be ignored due to idempotent behavior

        // Should still have the first factory
        $this->assertTrue(AdapterRegistry::hasAdapter('auto_adapter'));
        $this->assertSame($factory1, AdapterRegistry::getFactory('auto_adapter'));
        $this->assertNotSame($factory2, AdapterRegistry::getFactory('auto_adapter'));
    }

    private function createMockFactory(string $type, ?PersistenceAdapterInterface $adapter = null): AdapterFactoryInterface
    {
        $factory = $this->createMock(AdapterFactoryInterface::class);
        $factory->method('getAdapterType')->willReturn($type);

        if (null !== $adapter) {
            $factory->method('createAdapter')->willReturn($adapter);
        }

        return $factory;
    }
}

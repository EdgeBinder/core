<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Registry;

use EdgeBinder\Contracts\PersistenceAdapterInterface;
use EdgeBinder\Exception\AdapterException;
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

    public function testRegisterDuplicateAdapterThrowsException(): void
    {
        $factory1 = $this->createMockFactory('test_adapter');
        $factory2 = $this->createMockFactory('test_adapter');

        AdapterRegistry::register($factory1);

        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage("Adapter type 'test_adapter' is already registered");

        AdapterRegistry::register($factory2);
    }

    public function testCreateAdapterSuccess(): void
    {
        $mockAdapter = $this->createMock(PersistenceAdapterInterface::class);
        $factory = $this->createMockFactory('test_adapter', $mockAdapter);

        AdapterRegistry::register($factory);

        $config = [
            'instance' => ['adapter' => 'test_adapter'],
            'global' => [],
            'container' => $this->createMock(ContainerInterface::class),
        ];

        $result = AdapterRegistry::create('test_adapter', $config);

        $this->assertSame($mockAdapter, $result);
    }

    public function testCreateUnregisteredAdapterThrowsException(): void
    {
        $config = [
            'instance' => ['adapter' => 'unknown_adapter'],
            'global' => [],
            'container' => $this->createMock(ContainerInterface::class),
        ];

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

        $config = [
            'instance' => ['adapter' => 'unknown_adapter'],
            'global' => [],
            'container' => $this->createMock(ContainerInterface::class),
        ];

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

        $config = [
            'instance' => ['adapter' => 'test_adapter'],
            'global' => [],
            'container' => $this->createMock(ContainerInterface::class),
        ];

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

        $config = [
            'instance' => ['adapter' => 'test_adapter'],
            'global' => [],
            'container' => $this->createMock(ContainerInterface::class),
        ];

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

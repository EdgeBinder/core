<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Registry;

use EdgeBinder\Contracts\PersistenceAdapterInterface;
use EdgeBinder\Registry\AdapterFactoryInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class AdapterFactoryInterfaceTest extends TestCase
{
    public function testAdapterFactoryInterfaceContract(): void
    {
        $factory = $this->createTestFactory();
        
        $this->assertInstanceOf(AdapterFactoryInterface::class, $factory);
        
        // Test getAdapterType method
        $this->assertEquals('test', $factory->getAdapterType());
        
        // Test createAdapter method signature
        $config = [
            'instance' => [
                'adapter' => 'test',
                'test_client' => 'test.client.default',
                'host' => 'localhost',
                'port' => 1234,
            ],
            'global' => [
                'default_metadata_validation' => true,
                'entity_extraction_strategy' => 'reflection',
            ],
            'container' => $this->createMock(ContainerInterface::class),
        ];
        
        $adapter = $factory->createAdapter($config);
        $this->assertInstanceOf(PersistenceAdapterInterface::class, $adapter);
    }

    public function testAdapterFactoryWithMinimalConfiguration(): void
    {
        $factory = $this->createTestFactory();
        
        $config = [
            'instance' => ['adapter' => 'test'],
            'global' => [],
            'container' => $this->createMock(ContainerInterface::class),
        ];
        
        $adapter = $factory->createAdapter($config);
        $this->assertInstanceOf(PersistenceAdapterInterface::class, $adapter);
    }

    public function testAdapterFactoryWithComplexConfiguration(): void
    {
        $factory = $this->createTestFactory();
        
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturn(new \stdClass());
        
        $config = [
            'instance' => [
                'adapter' => 'test',
                'test_client' => 'test.client.custom',
                'host' => 'example.com',
                'port' => 9999,
                'database' => 'test_db',
                'timeout' => 30,
                'ssl' => true,
                'custom_option' => 'custom_value',
            ],
            'global' => [
                'default_metadata_validation' => true,
                'entity_extraction_strategy' => 'interface',
                'max_metadata_size' => 1024,
                'debug_mode' => false,
            ],
            'container' => $container,
        ];
        
        $adapter = $factory->createAdapter($config);
        $this->assertInstanceOf(PersistenceAdapterInterface::class, $adapter);
    }

    public function testAdapterFactoryThrowsExceptionForInvalidConfiguration(): void
    {
        $factory = $this->createTestFactory(true); // Create factory that throws on invalid config
        
        $config = [
            'instance' => ['adapter' => 'test'],
            'global' => [],
            // Missing 'container' key
        ];
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required configuration key: container');
        
        $factory->createAdapter($config);
    }

    public function testAdapterFactoryThrowsRuntimeExceptionOnCreationFailure(): void
    {
        $factory = $this->createTestFactory(false, true); // Create factory that throws runtime exception
        
        $config = [
            'instance' => ['adapter' => 'test'],
            'global' => [],
            'container' => $this->createMock(ContainerInterface::class),
        ];
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to connect to test service');
        
        $factory->createAdapter($config);
    }

    public function testMultipleFactoriesWithDifferentTypes(): void
    {
        $factory1 = $this->createTestFactory(false, false, 'adapter1');
        $factory2 = $this->createTestFactory(false, false, 'adapter2');
        $factory3 = $this->createTestFactory(false, false, 'adapter3');
        
        $this->assertEquals('adapter1', $factory1->getAdapterType());
        $this->assertEquals('adapter2', $factory2->getAdapterType());
        $this->assertEquals('adapter3', $factory3->getAdapterType());
        
        // Ensure each factory creates its own adapter type
        $this->assertNotEquals($factory1->getAdapterType(), $factory2->getAdapterType());
        $this->assertNotEquals($factory2->getAdapterType(), $factory3->getAdapterType());
        $this->assertNotEquals($factory1->getAdapterType(), $factory3->getAdapterType());
    }

    private function createTestFactory(
        bool $throwInvalidArgument = false,
        bool $throwRuntimeException = false,
        string $adapterType = 'test'
    ): AdapterFactoryInterface {
        return new class($throwInvalidArgument, $throwRuntimeException, $adapterType) implements AdapterFactoryInterface {
            public function __construct(
                private bool $throwInvalidArgument,
                private bool $throwRuntimeException,
                private string $adapterType
            ) {}

            /** @param array<string, mixed> $config */
            public function createAdapter(array $config): PersistenceAdapterInterface
            {
                if ($this->throwInvalidArgument) {
                    if (!isset($config['container'])) {
                        throw new \InvalidArgumentException('Missing required configuration key: container');
                    }
                }
                
                if ($this->throwRuntimeException) {
                    throw new \RuntimeException('Failed to connect to test service');
                }
                
                // Create a mock adapter for testing
                return $this->createMockAdapter();
            }
            
            public function getAdapterType(): string
            {
                return $this->adapterType;
            }
            
            private function createMockAdapter(): PersistenceAdapterInterface
            {
                return new class implements PersistenceAdapterInterface {
                    public function extractEntityId(object $entity): string { return 'test-id'; }
                    public function extractEntityType(object $entity): string { return 'test-type'; }
                    public function validateAndNormalizeMetadata(array $metadata): array { return $metadata; }
                    public function store(\EdgeBinder\Contracts\BindingInterface $binding): void {}
                    public function find(string $bindingId): ?\EdgeBinder\Contracts\BindingInterface { return null; }
                    public function findByEntity(string $entityType, string $entityId): array { return []; }
                    public function findBetweenEntities(string $fromType, string $fromId, string $toType, string $toId, ?string $bindingType = null): array { return []; }
                    public function executeQuery(\EdgeBinder\Contracts\QueryBuilderInterface $query): array { return []; }
                    public function count(\EdgeBinder\Contracts\QueryBuilderInterface $query): int { return 0; }
                    public function updateMetadata(string $bindingId, array $metadata): void {}
                    public function delete(string $bindingId): void {}
                    public function deleteByEntity(string $entityType, string $entityId): int { return 0; }
                };
            }
        };
    }
}

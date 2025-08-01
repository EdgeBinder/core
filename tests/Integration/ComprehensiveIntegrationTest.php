<?php
declare(strict_types=1);

namespace EdgeBinder\Tests\Integration;

use PHPUnit\Framework\TestCase;
use EdgeBinder\Registry\AdapterRegistry;
use EdgeBinder\Registry\AdapterFactoryInterface;
use EdgeBinder\Contracts\PersistenceAdapterInterface;
use EdgeBinder\Contracts\BindingInterface;
use EdgeBinder\Contracts\QueryBuilderInterface;
use EdgeBinder\EdgeBinder;
use EdgeBinder\Binding;
use EdgeBinder\Exception\AdapterException;
use Psr\Container\ContainerInterface;

/**
 * Comprehensive integration tests for the extensible adapter system.
 * 
 * These tests validate the complete workflow from adapter registration
 * to EdgeBinder usage across different scenarios and configurations.
 */
class ComprehensiveIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        AdapterRegistry::clear();
    }

    protected function tearDown(): void
    {
        AdapterRegistry::clear();
    }

    public function testCompleteWorkflowWithMultipleAdapters(): void
    {
        // Register multiple adapters
        AdapterRegistry::register(new MockRedisAdapterFactory());
        AdapterRegistry::register(new MockNeo4jAdapterFactory());
        AdapterRegistry::register(new MockWeaviateAdapterFactory());
        
        // Verify all adapters are registered
        $types = AdapterRegistry::getRegisteredTypes();
        $this->assertContains('redis', $types);
        $this->assertContains('neo4j', $types);
        $this->assertContains('weaviate', $types);
        
        // Create container with mock services
        $container = $this->createMockContainer();
        
        // Test Redis adapter
        $redisConfig = [
            'adapter' => 'redis',
            'redis_client' => 'redis.client.test',
            'ttl' => 3600,
            'prefix' => 'test:',
        ];
        
        $redisEdgeBinder = EdgeBinder::fromConfiguration($redisConfig, $container);
        $this->assertInstanceOf(EdgeBinder::class, $redisEdgeBinder);
        
        // Test Neo4j adapter
        $neo4jConfig = [
            'adapter' => 'neo4j',
            'neo4j_client' => 'neo4j.client.test',
            'database' => 'test_db',
        ];
        
        $neo4jEdgeBinder = EdgeBinder::fromConfiguration($neo4jConfig, $container);
        $this->assertInstanceOf(EdgeBinder::class, $neo4jEdgeBinder);
        
        // Test Weaviate adapter
        $weaviateConfig = [
            'adapter' => 'weaviate',
            'weaviate_client' => 'weaviate.client.test',
            'collection_name' => 'TestBindings',
        ];
        
        $weaviateEdgeBinder = EdgeBinder::fromConfiguration($weaviateConfig, $container);
        $this->assertInstanceOf(EdgeBinder::class, $weaviateEdgeBinder);
    }

    public function testAdapterConfigurationValidation(): void
    {
        AdapterRegistry::register(new MockValidatingAdapterFactory());
        
        $container = $this->createMockContainer();
        
        // Test valid configuration
        $validConfig = [
            'adapter' => 'validating',
            'required_param' => 'value',
            'optional_param' => 'optional_value',
        ];
        
        $edgeBinder = EdgeBinder::fromConfiguration($validConfig, $container);
        $this->assertInstanceOf(EdgeBinder::class, $edgeBinder);
        
        // Test missing required parameter
        $invalidConfig = [
            'adapter' => 'validating',
            'optional_param' => 'optional_value',
            // missing required_param
        ];
        
        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage('required_param is required');
        EdgeBinder::fromConfiguration($invalidConfig, $container);
    }

    public function testErrorHandlingAndRecovery(): void
    {
        // Register an adapter that sometimes fails
        AdapterRegistry::register(new MockFailingAdapterFactory());
        
        $container = $this->createMockContainer();
        
        // Test configuration that causes failure
        $failingConfig = [
            'adapter' => 'failing',
            'should_fail' => true,
        ];
        
        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage('Simulated adapter creation failure');
        EdgeBinder::fromConfiguration($failingConfig, $container);
        
        // Test configuration that succeeds
        $workingConfig = [
            'adapter' => 'failing',
            'should_fail' => false,
        ];
        
        $edgeBinder = EdgeBinder::fromConfiguration($workingConfig, $container);
        $this->assertInstanceOf(EdgeBinder::class, $edgeBinder);
    }

    public function testConcurrentAdapterUsage(): void
    {
        // Register adapters
        AdapterRegistry::register(new MockRedisAdapterFactory());
        AdapterRegistry::register(new MockNeo4jAdapterFactory());
        
        $container = $this->createMockContainer();
        
        // Create multiple EdgeBinder instances concurrently
        $edgeBinders = [];
        
        for ($i = 0; $i < 10; $i++) {
            $config = [
                'adapter' => $i % 2 === 0 ? 'redis' : 'neo4j',
                'instance_id' => $i,
            ];
            
            $edgeBinders[] = EdgeBinder::fromConfiguration($config, $container);
        }
        
        // Verify all instances were created successfully
        $this->assertCount(10, $edgeBinders);
        
        foreach ($edgeBinders as $edgeBinder) {
            $this->assertInstanceOf(EdgeBinder::class, $edgeBinder);
        }
    }

    public function testAdapterFactoryStateIsolation(): void
    {
        // Create a stateful adapter factory
        $factory = new MockStatefulAdapterFactory();
        AdapterRegistry::register($factory);
        
        $container = $this->createMockContainer();
        
        // Create multiple adapters and verify they don't share state
        $config1 = ['adapter' => 'stateful', 'state_value' => 'value1'];
        $config2 = ['adapter' => 'stateful', 'state_value' => 'value2'];
        
        $edgeBinder1 = EdgeBinder::fromConfiguration($config1, $container);
        $edgeBinder2 = EdgeBinder::fromConfiguration($config2, $container);
        
        $this->assertInstanceOf(EdgeBinder::class, $edgeBinder1);
        $this->assertInstanceOf(EdgeBinder::class, $edgeBinder2);
        
        // Verify that the factory was called twice with different configurations
        $this->assertEquals(2, $factory->getCreationCount());
    }

    public function testComplexConfigurationScenarios(): void
    {
        AdapterRegistry::register(new MockComplexAdapterFactory());
        
        $container = $this->createMockContainer();
        
        // Test nested configuration
        $complexConfig = [
            'adapter' => 'complex',
            'database' => [
                'host' => 'localhost',
                'port' => 5432,
                'name' => 'test_db',
                'credentials' => [
                    'username' => 'test_user',
                    'password' => 'test_pass',
                ],
            ],
            'cache' => [
                'enabled' => true,
                'ttl' => 3600,
                'prefix' => 'complex_test:',
            ],
            'features' => [
                'transactions' => true,
                'batch_operations' => false,
                'async_queries' => true,
            ],
        ];
        
        $edgeBinder = EdgeBinder::fromConfiguration($complexConfig, $container);
        $this->assertInstanceOf(EdgeBinder::class, $edgeBinder);
    }

    public function testFrameworkContainerIntegration(): void
    {
        AdapterRegistry::register(new MockRedisAdapterFactory());
        
        // Simulate different framework container behaviors
        $containers = [
            $this->createLaminasStyleContainer(),
            $this->createSymfonyStyleContainer(),
            $this->createLaravelStyleContainer(),
        ];
        
        foreach ($containers as $container) {
            $config = [
                'adapter' => 'redis',
                'redis_client' => 'redis.client.test',
            ];
            
            $edgeBinder = EdgeBinder::fromConfiguration($config, $container);
            $this->assertInstanceOf(EdgeBinder::class, $edgeBinder);
        }
    }

    public function testAdapterRegistryPersistence(): void
    {
        // Register adapters
        AdapterRegistry::register(new MockRedisAdapterFactory());
        AdapterRegistry::register(new MockNeo4jAdapterFactory());
        
        // Verify persistence across multiple operations
        for ($i = 0; $i < 100; $i++) {
            $this->assertTrue(AdapterRegistry::hasAdapter('redis'));
            $this->assertTrue(AdapterRegistry::hasAdapter('neo4j'));
            $this->assertFalse(AdapterRegistry::hasAdapter('nonexistent'));
        }
        
        // Verify registry contents remain consistent
        $types = AdapterRegistry::getRegisteredTypes();
        $this->assertCount(2, $types);
        $this->assertContains('redis', $types);
        $this->assertContains('neo4j', $types);
    }

    public function testEdgeBinderOperationsWithRegisteredAdapters(): void
    {
        AdapterRegistry::register(new MockFunctionalAdapterFactory());
        
        $container = $this->createMockContainer();
        $config = ['adapter' => 'functional'];
        
        $edgeBinder = EdgeBinder::fromConfiguration($config, $container);
        
        // Test basic operations
        $user = $this->createTestEntity('user', 'user123');
        $project = $this->createTestEntity('project', 'project456');
        
        // Test binding creation
        $binding = $edgeBinder->bind($user, $project, 'has_access', [
            'access_level' => 'write',
            'granted_at' => new \DateTimeImmutable(),
        ]);
        
        $this->assertInstanceOf(BindingInterface::class, $binding);
        $this->assertEquals('has_access', $binding->getType());
        
        // Test binding retrieval
        $foundBinding = $edgeBinder->findBinding($binding->getId());
        $this->assertNotNull($foundBinding);
        $this->assertEquals($binding->getId(), $foundBinding->getId());
        
        // Test querying
        $results = $edgeBinder->query()
            ->from($user)
            ->type('has_access')
            ->get();
        
        $this->assertIsArray($results);
        $this->assertNotEmpty($results);
        
        // Test unbinding
        $edgeBinder->unbind($binding->getId());
        $deletedBinding = $edgeBinder->findBinding($binding->getId());
        $this->assertNull($deletedBinding);
    }

    private function createMockContainer(): ContainerInterface
    {
        return new class implements ContainerInterface {
            private array $services = [
                'redis.client.test' => 'mock_redis_client',
                'neo4j.client.test' => 'mock_neo4j_client',
                'weaviate.client.test' => 'mock_weaviate_client',
            ];
            
            public function get(string $id)
            {
                if (!$this->has($id)) {
                    throw new \Exception("Service {$id} not found");
                }
                return $this->services[$id];
            }
            
            public function has(string $id): bool
            {
                return isset($this->services[$id]);
            }
        };
    }

    private function createLaminasStyleContainer(): ContainerInterface
    {
        return new class implements ContainerInterface {
            public function get(string $id) {
                return "laminas_service_$id";
            }
            
            public function has(string $id): bool {
                return true;
            }
        };
    }

    private function createSymfonyStyleContainer(): ContainerInterface
    {
        return new class implements ContainerInterface {
            public function get(string $id) {
                return "symfony_service_$id";
            }
            
            public function has(string $id): bool {
                return true;
            }
        };
    }

    private function createLaravelStyleContainer(): ContainerInterface
    {
        return new class implements ContainerInterface {
            public function get(string $id) {
                return "laravel_service_$id";
            }
            
            public function has(string $id): bool {
                return true;
            }
        };
    }

    private function createTestEntity(string $type, string $id): object
    {
        return new class($type, $id) {
            public function __construct(private string $type, private string $id) {}
            public function getId(): string { return $this->id; }
            public function getType(): string { return $this->type; }
        };
    }
}

// Mock adapter factories for testing
class MockRedisAdapterFactory implements AdapterFactoryInterface
{
    public function createAdapter(array $config): PersistenceAdapterInterface
    {
        return new class implements PersistenceAdapterInterface {
            public function store(BindingInterface $binding): void {}
            public function find(string $bindingId): ?BindingInterface { return null; }
            public function delete(string $bindingId): void {}
            public function executeQuery(QueryBuilderInterface $query): array { return []; }
            public function extractEntityId(object $entity): string { return $entity->getId(); }
            public function extractEntityType(object $entity): string { return $entity->getType(); }
            public function validateAndNormalizeMetadata(array $metadata): array { return $metadata; }
        };
    }
    
    public function getAdapterType(): string { return 'redis'; }
}

class MockNeo4jAdapterFactory implements AdapterFactoryInterface
{
    public function createAdapter(array $config): PersistenceAdapterInterface
    {
        return new class implements PersistenceAdapterInterface {
            public function store(BindingInterface $binding): void {}
            public function find(string $bindingId): ?BindingInterface { return null; }
            public function delete(string $bindingId): void {}
            public function executeQuery(QueryBuilderInterface $query): array { return []; }
            public function extractEntityId(object $entity): string { return $entity->getId(); }
            public function extractEntityType(object $entity): string { return $entity->getType(); }
            public function validateAndNormalizeMetadata(array $metadata): array { return $metadata; }
        };
    }
    
    public function getAdapterType(): string { return 'neo4j'; }
}

class MockWeaviateAdapterFactory implements AdapterFactoryInterface
{
    public function createAdapter(array $config): PersistenceAdapterInterface
    {
        return new class implements PersistenceAdapterInterface {
            public function store(BindingInterface $binding): void {}
            public function find(string $bindingId): ?BindingInterface { return null; }
            public function delete(string $bindingId): void {}
            public function executeQuery(QueryBuilderInterface $query): array { return []; }
            public function extractEntityId(object $entity): string { return $entity->getId(); }
            public function extractEntityType(object $entity): string { return $entity->getType(); }
            public function validateAndNormalizeMetadata(array $metadata): array { return $metadata; }
        };
    }
    
    public function getAdapterType(): string { return 'weaviate'; }
}

class MockValidatingAdapterFactory implements AdapterFactoryInterface
{
    public function createAdapter(array $config): PersistenceAdapterInterface
    {
        $instanceConfig = $config['instance'];
        
        if (!isset($instanceConfig['required_param'])) {
            throw new \InvalidArgumentException('required_param is required');
        }
        
        return new class implements PersistenceAdapterInterface {
            public function store(BindingInterface $binding): void {}
            public function find(string $bindingId): ?BindingInterface { return null; }
            public function delete(string $bindingId): void {}
            public function executeQuery(QueryBuilderInterface $query): array { return []; }
            public function extractEntityId(object $entity): string { return $entity->getId(); }
            public function extractEntityType(object $entity): string { return $entity->getType(); }
            public function validateAndNormalizeMetadata(array $metadata): array { return $metadata; }
        };
    }
    
    public function getAdapterType(): string { return 'validating'; }
}

class MockFailingAdapterFactory implements AdapterFactoryInterface
{
    public function createAdapter(array $config): PersistenceAdapterInterface
    {
        if ($config['instance']['should_fail'] ?? false) {
            throw new \RuntimeException('Simulated adapter creation failure');
        }
        
        return new class implements PersistenceAdapterInterface {
            public function store(BindingInterface $binding): void {}
            public function find(string $bindingId): ?BindingInterface { return null; }
            public function delete(string $bindingId): void {}
            public function executeQuery(QueryBuilderInterface $query): array { return []; }
            public function extractEntityId(object $entity): string { return $entity->getId(); }
            public function extractEntityType(object $entity): string { return $entity->getType(); }
            public function validateAndNormalizeMetadata(array $metadata): array { return $metadata; }
        };
    }
    
    public function getAdapterType(): string { return 'failing'; }
}

class MockStatefulAdapterFactory implements AdapterFactoryInterface
{
    private int $creationCount = 0;
    
    public function createAdapter(array $config): PersistenceAdapterInterface
    {
        $this->creationCount++;
        
        return new class implements PersistenceAdapterInterface {
            public function store(BindingInterface $binding): void {}
            public function find(string $bindingId): ?BindingInterface { return null; }
            public function delete(string $bindingId): void {}
            public function executeQuery(QueryBuilderInterface $query): array { return []; }
            public function extractEntityId(object $entity): string { return $entity->getId(); }
            public function extractEntityType(object $entity): string { return $entity->getType(); }
            public function validateAndNormalizeMetadata(array $metadata): array { return $metadata; }
        };
    }
    
    public function getAdapterType(): string { return 'stateful'; }
    
    public function getCreationCount(): int { return $this->creationCount; }
}

class MockComplexAdapterFactory implements AdapterFactoryInterface
{
    public function createAdapter(array $config): PersistenceAdapterInterface
    {
        // Validate complex configuration structure
        $instanceConfig = $config['instance'];
        
        if (!isset($instanceConfig['database']['host'])) {
            throw new \InvalidArgumentException('Database host is required');
        }
        
        return new class implements PersistenceAdapterInterface {
            public function store(BindingInterface $binding): void {}
            public function find(string $bindingId): ?BindingInterface { return null; }
            public function delete(string $bindingId): void {}
            public function executeQuery(QueryBuilderInterface $query): array { return []; }
            public function extractEntityId(object $entity): string { return $entity->getId(); }
            public function extractEntityType(object $entity): string { return $entity->getType(); }
            public function validateAndNormalizeMetadata(array $metadata): array { return $metadata; }
        };
    }
    
    public function getAdapterType(): string { return 'complex'; }
}

class MockFunctionalAdapterFactory implements AdapterFactoryInterface
{
    public function createAdapter(array $config): PersistenceAdapterInterface
    {
        return new class implements PersistenceAdapterInterface {
            private array $bindings = [];
            
            public function store(BindingInterface $binding): void {
                $this->bindings[$binding->getId()] = $binding;
            }
            
            public function find(string $bindingId): ?BindingInterface {
                return $this->bindings[$bindingId] ?? null;
            }
            
            public function delete(string $bindingId): void {
                unset($this->bindings[$bindingId]);
            }
            
            public function executeQuery(QueryBuilderInterface $query): array {
                return array_values($this->bindings);
            }
            
            public function extractEntityId(object $entity): string {
                return $entity->getId();
            }
            
            public function extractEntityType(object $entity): string {
                return $entity->getType();
            }
            
            public function validateAndNormalizeMetadata(array $metadata): array {
                return $metadata;
            }
        };
    }
    
    public function getAdapterType(): string { return 'functional'; }
}

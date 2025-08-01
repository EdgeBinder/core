# EdgeBinder Extensible Adapters Developer Guide

This guide provides comprehensive instructions for creating third-party adapters that work seamlessly across all PHP frameworks using EdgeBinder's extensible adapter system.

## Overview

EdgeBinder's extensible adapter system allows you to create custom persistence adapters that:

- **Work across all PHP frameworks** (Laminas, Symfony, Laravel, Slim, etc.)
- **Require no modifications** to EdgeBinder Core
- **Use a single package** that works everywhere
- **Access framework services** through PSR-11 containers
- **Follow consistent patterns** for configuration and integration

## Quick Start

### 1. Create Your Adapter Class

First, implement the `PersistenceAdapterInterface`:

```php
<?php
namespace MyVendor\RedisAdapter;

use EdgeBinder\Contracts\PersistenceAdapterInterface;
use EdgeBinder\Contracts\BindingInterface;
use EdgeBinder\Contracts\QueryBuilderInterface;
use EdgeBinder\Exception\PersistenceException;

class RedisAdapter implements PersistenceAdapterInterface
{
    private $redis;
    private array $config;

    public function __construct($redisClient, array $config = [])
    {
        $this->redis = $redisClient;
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    public function store(BindingInterface $binding): void
    {
        try {
            $key = $this->buildKey($binding->getId());
            $data = json_encode($binding->toArray());
            
            $result = $this->redis->setex(
                $key,
                $this->config['ttl'],
                $data
            );
            
            if (!$result) {
                throw PersistenceException::operationFailed('store', 'Redis setex returned false');
            }
        } catch (\Exception $e) {
            if ($e instanceof PersistenceException) {
                throw $e;
            }
            throw PersistenceException::serverError('store', $e->getMessage(), $e);
        }
    }

    public function find(string $bindingId): ?BindingInterface
    {
        try {
            $key = $this->buildKey($bindingId);
            $data = $this->redis->get($key);
            
            if ($data === false) {
                return null;
            }
            
            $array = json_decode($data, true);
            return \EdgeBinder\Binding::fromArray($array);
        } catch (\Exception $e) {
            throw PersistenceException::serverError('find', $e->getMessage(), $e);
        }
    }

    // Implement other required methods...
    
    private function getDefaultConfig(): array
    {
        return [
            'ttl' => 3600,
            'prefix' => 'edgebinder:',
        ];
    }
    
    private function buildKey(string $bindingId): string
    {
        return $this->config['prefix'] . $bindingId;
    }
}
```

### 2. Create Your Adapter Factory

Implement the `AdapterFactoryInterface`:

```php
<?php
namespace MyVendor\RedisAdapter;

use EdgeBinder\Registry\AdapterFactoryInterface;
use EdgeBinder\Contracts\PersistenceAdapterInterface;

class RedisAdapterFactory implements AdapterFactoryInterface
{
    public function createAdapter(array $config): PersistenceAdapterInterface
    {
        $container = $config['container'];
        $instanceConfig = $config['instance'];
        $globalConfig = $config['global'];
        
        // Get Redis client from container
        $redisClient = $container->get(
            $instanceConfig['redis_client'] ?? 'redis.client.default'
        );
        
        // Build adapter configuration
        $adapterConfig = [
            'ttl' => $instanceConfig['ttl'] ?? 3600,
            'prefix' => $instanceConfig['prefix'] ?? 'edgebinder:',
        ];

        return new RedisAdapter($redisClient, $adapterConfig);
    }
    
    public function getAdapterType(): string
    {
        return 'redis';
    }
}
```

### 3. Register Your Adapter

Register the adapter factory in your application bootstrap:

```php
// Works identically across all frameworks
use EdgeBinder\Registry\AdapterRegistry;
use MyVendor\RedisAdapter\RedisAdapterFactory;

AdapterRegistry::register(new RedisAdapterFactory());
```

### 4. Configure and Use

Create configuration that works across all frameworks:

```php
$config = [
    'adapter' => 'redis',
    'redis_client' => 'redis.client.cache',
    'ttl' => 7200,
    'prefix' => 'myapp:bindings:',
];

$edgeBinder = EdgeBinder::fromConfiguration($config, $container);
```

## Detailed Implementation Guide

### Understanding the Configuration Structure

Your adapter factory receives a standardized configuration array with three sections:

```php
[
    'instance' => [
        'adapter' => 'redis',           // Your adapter type
        'redis_client' => 'redis.client.cache',  // Service names
        'ttl' => 7200,                 // Adapter-specific config
        'prefix' => 'myapp:',          // More adapter config
    ],
    'global' => $globalEdgeBinderConfig,  // Full global EdgeBinder config
    'container' => $psrContainer,         // PSR-11 container instance
]
```

### Required Interface Methods

Your adapter must implement all methods from `PersistenceAdapterInterface`:

#### Core Storage Operations

```php
public function store(BindingInterface $binding): void;
public function find(string $bindingId): ?BindingInterface;
public function delete(string $bindingId): void;
public function executeQuery(QueryBuilderInterface $query): array;
```

#### Entity Extraction

```php
public function extractEntityId(object $entity): string;
public function extractEntityType(object $entity): string;
```

#### Metadata Handling

```php
public function validateAndNormalizeMetadata(array $metadata): array;
```

### Error Handling Best Practices

Use EdgeBinder's exception hierarchy for consistent error handling:

```php
use EdgeBinder\Exception\PersistenceException;
use EdgeBinder\Exception\EntityExtractionException;
use EdgeBinder\Exception\InvalidMetadataException;

// For storage operations
try {
    $result = $this->client->operation($data);
    if (!$result) {
        throw PersistenceException::operationFailed('store', 'Operation returned false');
    }
} catch (\Exception $e) {
    if ($e instanceof PersistenceException) {
        throw $e;
    }
    throw PersistenceException::serverError('store', $e->getMessage(), $e);
}

// For entity extraction
public function extractEntityId(object $entity): string
{
    if ($entity instanceof EntityInterface) {
        return $entity->getId();
    }

    if (method_exists($entity, 'getId')) {
        $id = $entity->getId();
        if (is_string($id) && !empty($id)) {
            return $id;
        }
    }

    throw new EntityExtractionException('Cannot extract entity ID', $entity);
}
```

### Configuration Validation

Validate your adapter's configuration in the factory:

```php
public function createAdapter(array $config): PersistenceAdapterInterface
{
    $this->validateConfiguration($config);
    
    // ... rest of factory logic
}

private function validateConfiguration(array $config): void
{
    $required = ['container', 'instance'];
    foreach ($required as $key) {
        if (!isset($config[$key])) {
            throw new \InvalidArgumentException("Required config key '{$key}' missing");
        }
    }
    
    $instanceRequired = ['redis_client'];
    foreach ($instanceRequired as $key) {
        if (!isset($config['instance'][$key])) {
            throw new \InvalidArgumentException("Required instance config key '{$key}' missing");
        }
    }
}
```

## Testing Your Adapter

### Unit Testing

Create comprehensive unit tests for your adapter:

```php
<?php
namespace MyVendor\RedisAdapter\Tests;

use PHPUnit\Framework\TestCase;
use MyVendor\RedisAdapter\RedisAdapter;
use EdgeBinder\Binding;

class RedisAdapterTest extends TestCase
{
    private $mockRedis;
    private RedisAdapter $adapter;

    protected function setUp(): void
    {
        $this->mockRedis = $this->createMock(\Redis::class);
        $this->adapter = new RedisAdapter($this->mockRedis);
    }

    public function testStoreBinding(): void
    {
        $binding = Binding::create($user, $project, 'has_access');
        
        $this->mockRedis
            ->expects($this->once())
            ->method('setex')
            ->with(
                'edgebinder:' . $binding->getId(),
                3600,
                $this->isType('string')
            )
            ->willReturn(true);

        $this->adapter->store($binding);
    }

    // Add more tests...
}
```

### Integration Testing

Test your adapter factory with the registry system:

```php
public function testAdapterFactoryIntegration(): void
{
    $factory = new RedisAdapterFactory();
    AdapterRegistry::register($factory);

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')->willReturn($this->mockRedis);

    $config = [
        'adapter' => 'redis',
        'redis_client' => 'redis.client.test',
        'ttl' => 1800,
    ];

    $edgeBinder = EdgeBinder::fromConfiguration($config, $container);
    $this->assertInstanceOf(EdgeBinder::class, $edgeBinder);
}
```

## Framework Integration Patterns

See the [Framework Integration Guide](FRAMEWORK_INTEGRATION.md) for detailed examples of how to register your adapter in different PHP frameworks.

## Common Patterns and Best Practices

### Adapter Constructor Pattern

```php
public function __construct($client, array $config = [], ?BindingMapper $mapper = null)
{
    $this->client = $client;
    $this->config = array_merge($this->getDefaultConfig(), $config);
    $this->mapper = $mapper ?? new DefaultBindingMapper();
    $this->validateClient($client);
}
```

### Configuration Merging

```php
private function getDefaultConfig(): array
{
    return [
        'timeout' => 30,
        'retry_attempts' => 3,
        'batch_size' => 100,
    ];
}
```

### Query Builder Support

```php
public function executeQuery(QueryBuilderInterface $query): array
{
    $criteria = $query->getCriteria();
    $results = [];
    
    // Convert EdgeBinder query to your storage's query format
    $nativeQuery = $this->buildNativeQuery($criteria);
    $rawResults = $this->client->query($nativeQuery);
    
    // Convert results back to Binding objects
    foreach ($rawResults as $raw) {
        $results[] = $this->mapToBinding($raw);
    }
    
    return $results;
}
```

## Troubleshooting

### Common Issues

1. **Adapter Not Found**: Ensure `AdapterRegistry::register()` is called before EdgeBinder instantiation
2. **Configuration Errors**: Verify config structure matches expected format
3. **Container Service Missing**: Ensure client services are registered in container
4. **Entity Extraction Fails**: Implement EntityInterface or ensure getId()/getType() methods exist

### Debug Helpers

```php
// Check registered adapters
$types = AdapterRegistry::getRegisteredTypes();
var_dump($types);

// Validate configuration structure
$required = ['instance', 'global', 'container'];
foreach ($required as $key) {
    if (!isset($config[$key])) {
        throw new \InvalidArgumentException("Missing config key: {$key}");
    }
}
```

## Next Steps

1. Review the [Framework Integration Guide](FRAMEWORK_INTEGRATION.md) for framework-specific examples
2. Check the [Redis Adapter Example](../examples/RedisAdapter/) for a complete reference implementation
3. See the [Migration Guide](MIGRATION_GUIDE.md) if you're converting existing adapters

## Support

For questions and issues:
- Check the [GitHub issue tracker](https://github.com/EdgeBinder/EdgeBinder/issues)
- Review existing adapter implementations for patterns
- Join the EdgeBinder community discussions

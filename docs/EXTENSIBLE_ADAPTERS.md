# EdgeBinder Extensible Adapters Developer Guide (v0.6.2)

This guide provides comprehensive instructions for creating third-party adapters using EdgeBinder v0.6.2's **revolutionary Criteria Transformer Pattern** that work seamlessly across all PHP frameworks.

## Overview

EdgeBinder v0.6.0's **revolutionary architecture** makes creating adapters dramatically easier with the Criteria Transformer Pattern:

- **95% less adapter code** - Transformers handle all conversion logic
- **Work across all PHP frameworks** (Laminas, Symfony, Laravel, Slim, etc.)
- **Require no modifications** to EdgeBinder
- **Use a single package** that works everywhere
- **Access framework services** through PSR-11 containers
- **Follow consistent patterns** for configuration and integration
- **Better separation of concerns** - Adapters execute, transformers convert
- **Easier testing** - Unit test transformers independently

## Quick Start (v0.6.0 Pattern)

### 1. Create Your Transformer Class (NEW in v0.6.0)

First, implement the `CriteriaTransformerInterface` - this is where your conversion logic goes:

```php
<?php
namespace MyVendor\RedisAdapter;

use EdgeBinder\Contracts\CriteriaTransformerInterface;
use EdgeBinder\Query\{EntityCriteria, WhereCriteria, OrderByCriteria};

class RedisTransformer implements CriteriaTransformerInterface
{
    public function transformEntity(EntityCriteria $entity, string $direction): mixed
    {
        // Convert to Redis key pattern
        return [
            'pattern' => "entity:{$entity->entityType}:{$entity->entityId}:*",
            'direction' => $direction
        ];
    }

    public function transformWhere(WhereCriteria $where): mixed
    {
        // Convert to Redis filtering format
        return [
            'field' => $where->field,
            'operator' => $this->mapOperator($where->operator),
            'value' => $where->value
        ];
    }

    public function transformOrderBy(OrderByCriteria $orderBy): mixed
    {
        // Convert to Redis sort format
        return [
            'by' => $orderBy->field,
            'order' => strtoupper($orderBy->direction)
        ];
    }

    public function transformBindingType(string $type): mixed
    {
        return ['type_pattern' => "type:{$type}"];
    }

    public function combineFilters(array $filters, array $orFilters = []): mixed
    {
        $combined = ['and' => $filters];
        if (!empty($orFilters)) {
            $combined['or'] = $orFilters;
        }
        return $combined;
    }

    private function mapOperator(string $operator): string
    {
        return match($operator) {
            '=' => 'eq',
            '!=' => 'ne',
            '>' => 'gt',
            '<' => 'lt',
            '>=' => 'gte',
            '<=' => 'lte',
            'in' => 'in',
            'notIn' => 'nin',
            default => throw new \InvalidArgumentException("Unsupported operator: $operator")
        };
    }
}
```

### 2. Create Your Light Adapter Class (v0.6.0 Pattern)

Now implement the `PersistenceAdapterInterface` - it's incredibly simple with the transformer:
```php
<?php
namespace MyVendor\RedisAdapter;

use EdgeBinder\Contracts\{PersistenceAdapterInterface, QueryResultInterface};
use EdgeBinder\Contracts\BindingInterface;
use EdgeBinder\Query\{QueryCriteria, QueryResult};
use EdgeBinder\Exception\PersistenceException;

class RedisAdapter implements PersistenceAdapterInterface
{
    private $redis;
    private RedisTransformer $transformer;
    private array $config;

    public function __construct($redisClient, array $config = [])
    {
        $this->redis = $redisClient;
        $this->transformer = new RedisTransformer();  // NEW: Include transformer
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    // 🚀 v0.6.0 LIGHT ADAPTER PATTERN - Just 3 lines!
    public function executeQuery(QueryCriteria $criteria): QueryResultInterface
    {
        $query = $criteria->transform($this->transformer);  // 1 line transformation!
        $results = $this->executeRedisQuery($query);        // Execute with Redis
        return new QueryResult($results);                   // Return QueryResult object
    }

    public function count(QueryCriteria $criteria): int
    {
        $query = $criteria->transform($this->transformer);
        return $this->executeRedisCount($query);
    }

    // Standard methods remain the same
    public function store(BindingInterface $binding): void
    {
        try {
            $key = $this->buildKey($binding->getId());
            $data = json_encode($binding->toArray());

            $result = $this->redis->setex($key, $this->config['ttl'], $data);
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

    private function executeRedisQuery(array $query): array
    {
        // Execute the transformed query with Redis
        // Return array of BindingInterface objects
    }

    private function executeRedisCount(array $query): int
    {
        // Execute count query with Redis
        // Return integer count
    }

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

### 3. Create Your Adapter Factory

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

### 3. Implement Auto-Registration

Create a bootstrap file for automatic registration when your package is loaded:

**File: `src/bootstrap.php`**
```php
<?php
// Auto-register the adapter when package is loaded
use EdgeBinder\Registry\AdapterRegistry;
use MyVendor\RedisAdapter\RedisAdapterFactory;

// Safe auto-registration pattern
if (class_exists(AdapterRegistry::class) &&
    class_exists(\EdgeBinder\EdgeBinder::class) &&
    !AdapterRegistry::hasAdapter('redis')) {

    AdapterRegistry::register(new RedisAdapterFactory());
}
```

**Update your `composer.json`:**
```json
{
    "autoload": {
        "psr-4": {
            "MyVendor\\RedisAdapter\\": "src/"
        },
        "files": ["src/bootstrap.php"]
    }
}
```

### 4. Configure and Use

Create configuration that works across all frameworks (no registration needed):

```php
$config = [
    'adapter' => 'redis',
    'redis_client' => 'redis.client.cache',
    'ttl' => 7200,
    'prefix' => 'myapp:bindings:',
];

// Adapter auto-registers when package is loaded
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
public function executeQuery(QueryCriteria $criteria): QueryResultInterface;  // v0.6.0 BREAKING CHANGE
public function count(QueryCriteria $criteria): int;  // v0.6.0 BREAKING CHANGE
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

### **REQUIRED: AbstractAdapterTestSuite Compliance**

**⚠️ CRITICAL**: All adapters MUST extend `AbstractAdapterTestSuite` to ensure 100% compliance with EdgeBinder's expected behavior.

```php
<?php
namespace MyVendor\RedisAdapter\Tests;

use EdgeBinder\Testing\AbstractAdapterTestSuite;
use EdgeBinder\Contracts\PersistenceAdapterInterface;
use MyVendor\RedisAdapter\RedisAdapter;

class RedisAdapterTest extends AbstractAdapterTestSuite
{
    private $redis;

    protected function createAdapter(): PersistenceAdapterInterface
    {
        $this->redis = new \Redis();
        $this->redis->connect('127.0.0.1', 6379);
        $this->redis->select(15); // Use test database

        return new RedisAdapter($this->redis, [
            'prefix' => 'test:bindings:',
            'ttl' => 3600
        ]);
    }

    protected function cleanupAdapter(): void
    {
        if ($this->redis) {
            $this->redis->flushDB(); // Clean test database
            $this->redis->close();
        }
    }

    // AbstractAdapterTestSuite provides 57+ comprehensive tests automatically
    // These tests ensure your adapter behaves identically to InMemoryAdapter

    // Add adapter-specific tests only if needed:
    public function testRedisSpecificFeature(): void
    {
        // Test Redis-specific functionality that's not covered by AbstractAdapterTestSuite
    }
}
```

### **Why AbstractAdapterTestSuite is Required**

The `AbstractAdapterTestSuite` provides **57 comprehensive integration tests** that:

- ✅ **Test all public API methods** with real EdgeBinder integration
- ✅ **Cover complex query scenarios** (from, to, type, where, ordering, pagination)
- ✅ **Validate metadata handling** (validation, normalization, edge cases)
- ✅ **Test entity extraction** (EntityInterface, getId(), fallbacks)
- ✅ **Check error handling** (exceptions, edge cases, invalid data)
- ✅ **Ensure data consistency** (CRUD operations, indexing)
- ✅ **Catch production bugs** (proven to find 5+ critical issues)

**Without these tests, your adapter may have subtle bugs** that only appear in production with specific query patterns.

### Unit Testing (Optional - For Internal Logic)

You can also add unit tests for adapter-specific internal logic:

```php
<?php
namespace MyVendor\RedisAdapter\Tests\Unit;

use PHPUnit\Framework\TestCase;
use MyVendor\RedisAdapter\RedisAdapter;

class RedisAdapterUnitTest extends TestCase
{
    public function testInternalRedisKeyGeneration(): void
    {
        $mockRedis = $this->createMock(\Redis::class);
        $adapter = new RedisAdapter($mockRedis);

        // Test internal logic that doesn't require full integration
    }
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

See the [Framework Integration Guide](FRAMEWORK_INTEGRATION.md) for detailed examples of how to use your auto-registering adapter in different PHP frameworks.

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

### Query Execution Support (v0.6.0 Pattern)

```php
public function executeQuery(QueryCriteria $criteria): QueryResultInterface
{
    // v0.6.0 LIGHT ADAPTER PATTERN - Just 3 lines!
    $query = $criteria->transform($this->transformer);  // 1 line transformation!
    $results = $this->executeNativeQuery($query);       // Execute with your client
    return new QueryResult($results);                   // Return QueryResult object
}

public function count(QueryCriteria $criteria): int
{
    $query = $criteria->transform($this->transformer);
    return $this->executeNativeCount($query);
}
```

### OLD v0.5.0 Pattern (DON'T USE - For Reference Only)

```php
// OLD v0.5.0 - Heavy adapter with manual conversion (50+ lines):
public function executeQuery(QueryBuilderInterface $query): array
{
    $criteria = $query->getCriteria();
    $results = [];

    // 20+ lines of complex conversion logic...
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

1. **Adapter Not Found**: Ensure your package's bootstrap.php is loaded via composer autoload files
2. **Configuration Errors**: Verify config structure matches expected format
3. **Container Service Missing**: Ensure client services are registered in container
4. **Entity Extraction Fails**: Implement EntityInterface or ensure getId()/getType() methods exist

### Debug Helpers

```php
// Check if your adapter auto-registered
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

1. **[Read the Adapter Testing Standard](ADAPTER_TESTING_STANDARD.md)** - REQUIRED compliance testing guide
2. Review the [Framework Integration Guide](FRAMEWORK_INTEGRATION.md) for framework-specific examples
3. Check the [Redis Adapter Example](../examples/RedisAdapter/) for a complete reference implementation
4. See the [Migration Guide](MIGRATION_GUIDE.md) if you're converting existing adapters

## Support

For questions and issues:
- Check the [GitHub issue tracker](https://github.com/EdgeBinder/edgebinder/issues)
- Review existing adapter implementations for patterns
- Join the EdgeBinder community discussions

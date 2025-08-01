# Migration Guide: Converting to Extensible Adapters

This guide helps you migrate existing custom EdgeBinder adapters to the new extensible adapter system introduced in EdgeBinder v2.0.

## Overview

The extensible adapter system provides several benefits over direct adapter usage:

- **Framework Agnostic**: One adapter package works across all PHP frameworks
- **Standardized Configuration**: Consistent configuration patterns
- **Container Integration**: Access to framework services through PSR-11
- **Registry Management**: Centralized adapter discovery and creation
- **Better Error Handling**: Consistent exception handling across adapters

## Migration Steps

### Step 1: Assess Your Current Implementation

#### Before (Direct Adapter Usage)

```php
// Old way - direct adapter instantiation
$redisClient = new \Redis();
$redisClient->connect('localhost', 6379);

$adapter = new MyCustomRedisAdapter($redisClient, [
    'ttl' => 3600,
    'prefix' => 'edgebinder:',
]);

$edgeBinder = new EdgeBinder($adapter);
```

#### After (Extensible Adapter System)

```php
// New way - registry-based creation
AdapterRegistry::register(new RedisAdapterFactory());

$config = [
    'adapter' => 'redis',
    'redis_client' => 'redis.client.default',
    'ttl' => 3600,
    'prefix' => 'edgebinder:',
];

$edgeBinder = EdgeBinder::fromConfiguration($config, $container);
```

### Step 2: Create Adapter Factory

Create a new factory class that implements `AdapterFactoryInterface`:

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
        
        // Get client from container instead of creating directly
        $redisClient = $container->get(
            $instanceConfig['redis_client'] ?? 'redis.client.default'
        );
        
        // Extract configuration from flatter structure
        $adapterConfig = [
            'ttl' => $instanceConfig['ttl'] ?? 3600,
            'prefix' => $instanceConfig['prefix'] ?? 'edgebinder:',
            'timeout' => $instanceConfig['timeout'] ?? 30,
        ];

        // Use your existing adapter class
        return new MyCustomRedisAdapter($redisClient, $adapterConfig);
    }
    
    public function getAdapterType(): string
    {
        return 'redis'; // Choose a unique identifier
    }
}
```

### Step 3: Update Adapter Class (If Needed)

Your existing adapter class may need minimal changes:

#### Configuration Handling

```php
// Before - hardcoded defaults
class MyCustomRedisAdapter implements PersistenceAdapterInterface
{
    public function __construct(\Redis $redis)
    {
        $this->redis = $redis;
        $this->ttl = 3600; // Hardcoded
        $this->prefix = 'edgebinder:'; // Hardcoded
    }
}

// After - configurable
class MyCustomRedisAdapter implements PersistenceAdapterInterface
{
    public function __construct(\Redis $redis, array $config = [])
    {
        $this->redis = $redis;
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }
    
    private function getDefaultConfig(): array
    {
        return [
            'ttl' => 3600,
            'prefix' => 'edgebinder:',
            'timeout' => 30,
        ];
    }
}
```

#### Error Handling Updates

```php
// Before - generic exceptions
public function store(BindingInterface $binding): void
{
    $result = $this->redis->setex($key, $this->ttl, $data);
    if (!$result) {
        throw new \RuntimeException('Redis operation failed');
    }
}

// After - EdgeBinder exceptions
use EdgeBinder\Exception\PersistenceException;

public function store(BindingInterface $binding): void
{
    try {
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
```

### Step 4: Update Service Registration

#### Laminas/Mezzio

```php
// Before - direct service registration
'dependencies' => [
    'factories' => [
        'edgebinder' => function($container) {
            $redis = new \Redis();
            $redis->connect('localhost', 6379);
            $adapter = new MyCustomRedisAdapter($redis);
            return new EdgeBinder($adapter);
        },
    ],
],

// After - registry-based
'dependencies' => [
    'factories' => [
        'redis.client.default' => function($container) {
            $redis = new \Redis();
            $redis->connect('localhost', 6379);
            return $redis;
        },
        'edgebinder' => function($container) {
            $config = $container->get('config')['edgebinder'];
            return EdgeBinder::fromConfiguration($config, $container);
        },
    ],
],
'edgebinder' => [
    'adapter' => 'redis',
    'redis_client' => 'redis.client.default',
    'ttl' => 3600,
],
```

#### Symfony

```yaml
# Before - direct service
services:
    edgebinder:
        class: EdgeBinder\EdgeBinder
        arguments:
            - '@my_custom_redis_adapter'
    
    my_custom_redis_adapter:
        class: MyVendor\RedisAdapter\MyCustomRedisAdapter
        arguments:
            - '@redis_client'

# After - registry-based
services:
    redis.client.default:
        class: Redis
        calls:
            - [connect, ['localhost', 6379]]
    
    edgebinder:
        class: EdgeBinder\EdgeBinder
        factory: ['EdgeBinder\EdgeBinder', 'fromConfiguration']
        arguments:
            - '%edgebinder_config%'
            - '@service_container'

parameters:
    edgebinder_config:
        adapter: 'redis'
        redis_client: 'redis.client.default'
        ttl: 3600
```

#### Laravel

```php
// Before - direct binding
public function register()
{
    $this->app->singleton(EdgeBinder::class, function ($app) {
        $redis = new \Redis();
        $redis->connect('localhost', 6379);
        $adapter = new MyCustomRedisAdapter($redis);
        return new EdgeBinder($adapter);
    });
}

// After - registry-based
public function register()
{
    $this->app->singleton('redis.client.default', function ($app) {
        $redis = new \Redis();
        $redis->connect('localhost', 6379);
        return $redis;
    });
    
    $this->app->singleton(EdgeBinder::class, function ($app) {
        $config = config('edgebinder');
        return EdgeBinder::fromConfiguration($config, $app);
    });
}

public function boot()
{
    AdapterRegistry::register(new RedisAdapterFactory());
}
```

### Step 5: Update Configuration

#### Before - Framework-Specific Configuration

```php
// Laminas
'my_adapter' => [
    'host' => 'localhost',
    'port' => 6379,
    'ttl' => 3600,
],

// Symfony
my_adapter:
    host: 'localhost'
    port: 6379
    ttl: 3600

// Laravel
'my_adapter' => [
    'host' => env('REDIS_HOST', 'localhost'),
    'port' => env('REDIS_PORT', 6379),
    'ttl' => env('CACHE_TTL', 3600),
],
```

#### After - Standardized Configuration

```php
// Works across all frameworks
'edgebinder' => [
    'adapter' => 'redis',
    'redis_client' => 'redis.client.default',
    'ttl' => 3600,
    'prefix' => 'edgebinder:',
],
```

### Step 6: Update Tests

#### Before - Direct Testing

```php
public function testAdapter(): void
{
    $redis = $this->createMock(\Redis::class);
    $adapter = new MyCustomRedisAdapter($redis);
    $edgeBinder = new EdgeBinder($adapter);
    
    // Test EdgeBinder operations
}
```

#### After - Registry Testing

```php
public function setUp(): void
{
    parent::setUp();
    AdapterRegistry::clear(); // Clean state for each test
}

public function testAdapterThroughRegistry(): void
{
    // Register adapter
    AdapterRegistry::register(new RedisAdapterFactory());
    
    // Mock container
    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')->willReturn($this->createMock(\Redis::class));
    
    // Test through registry
    $config = [
        'adapter' => 'redis',
        'redis_client' => 'redis.client.test',
    ];
    
    $edgeBinder = EdgeBinder::fromConfiguration($config, $container);
    
    // Test EdgeBinder operations
}
```

## Breaking Changes

### Configuration Structure

The configuration structure has changed from nested to flatter:

```php
// Before - nested configuration
$config = [
    'adapter' => [
        'type' => 'redis',
        'options' => [
            'client' => 'redis.client.default',
            'ttl' => 3600,
        ],
    ],
];

// After - flatter configuration
$config = [
    'adapter' => 'redis',
    'redis_client' => 'redis.client.default',
    'ttl' => 3600,
];
```

### Factory Method Changes

EdgeBinder instantiation has changed:

```php
// Before - direct constructor
$edgeBinder = new EdgeBinder($adapter);

// After - factory methods
$edgeBinder = EdgeBinder::fromConfiguration($config, $container);
// OR (for backward compatibility)
$edgeBinder = EdgeBinder::fromAdapter($adapter);
```

### Exception Handling

Use EdgeBinder's exception hierarchy:

```php
// Before - generic exceptions
throw new \RuntimeException('Operation failed');

// After - specific exceptions
throw PersistenceException::operationFailed('store', 'Redis operation failed');
```

## Compatibility Layer

For gradual migration, you can create a compatibility layer:

```php
// Wrapper for backward compatibility
class LegacyEdgeBinderFactory
{
    public static function create(string $adapterType, array $config): EdgeBinder
    {
        // Convert old config format to new format
        $newConfig = [
            'adapter' => $adapterType,
            // Map old config keys to new format
        ];
        
        // Create container with legacy services
        $container = new LegacyContainer($config);
        
        return EdgeBinder::fromConfiguration($newConfig, $container);
    }
}
```

## Migration Checklist

- [ ] Create `AdapterFactoryInterface` implementation
- [ ] Update adapter class for configuration flexibility
- [ ] Register adapter factory in application bootstrap
- [ ] Update service container configuration
- [ ] Convert configuration to new flatter format
- [ ] Update EdgeBinder instantiation to use factory methods
- [ ] Update exception handling to use EdgeBinder exceptions
- [ ] Update tests to use registry system
- [ ] Test with your specific framework
- [ ] Update documentation and examples

## Common Migration Issues

### Issue: Service Not Found

```php
// Problem: Container doesn't have the service
$client = $container->get('redis.client.default'); // Throws exception

// Solution: Ensure service is registered
$container->set('redis.client.default', function() {
    $redis = new \Redis();
    $redis->connect('localhost', 6379);
    return $redis;
});
```

### Issue: Configuration Mismatch

```php
// Problem: Old nested configuration
$config = [
    'adapter' => [
        'type' => 'redis',
        'options' => ['ttl' => 3600],
    ],
];

// Solution: Use flatter configuration
$config = [
    'adapter' => 'redis',
    'ttl' => 3600,
];
```

### Issue: Adapter Not Registered

```php
// Problem: Adapter factory not registered
$edgeBinder = EdgeBinder::fromConfiguration($config, $container);
// Throws: AdapterException: Adapter factory for type 'redis' not found

// Solution: Register adapter factory
AdapterRegistry::register(new RedisAdapterFactory());
```

## Testing Your Migration

### Unit Tests

```php
public function testMigrationCompatibility(): void
{
    // Test that old and new approaches produce equivalent results
    
    // Old approach
    $oldAdapter = new MyCustomRedisAdapter($this->redis, ['ttl' => 3600]);
    $oldEdgeBinder = EdgeBinder::fromAdapter($oldAdapter);
    
    // New approach
    AdapterRegistry::register(new RedisAdapterFactory());
    $config = ['adapter' => 'redis', 'redis_client' => 'test.redis', 'ttl' => 3600];
    $newEdgeBinder = EdgeBinder::fromConfiguration($config, $this->container);
    
    // Both should behave identically
    $binding1 = $oldEdgeBinder->bind($user, $project, 'test');
    $binding2 = $newEdgeBinder->bind($user, $project, 'test');
    
    $this->assertEquals($binding1->getType(), $binding2->getType());
}
```

### Integration Tests

```php
public function testFrameworkIntegration(): void
{
    // Test that migration works in your specific framework
    $container = $this->getFrameworkContainer();
    
    // Register services as they would be in production
    $this->registerProductionServices($container);
    
    // Test EdgeBinder creation and usage
    $config = $this->getProductionConfig();
    $edgeBinder = EdgeBinder::fromConfiguration($config, $container);
    
    // Test actual operations
    $binding = $edgeBinder->bind($user, $project, 'test');
    $this->assertNotNull($binding);
}
```

## Support

If you encounter issues during migration:

1. Check the [Extensible Adapters Guide](EXTENSIBLE_ADAPTERS.md) for implementation details
2. Review [Framework Integration Guide](FRAMEWORK_INTEGRATION.md) for framework-specific patterns
3. Examine the [Redis Adapter Example](../examples/RedisAdapter/) for a complete reference
4. Open an issue on the [GitHub repository](https://github.com/EdgeBinder/edgebinder/issues) with migration questions

## Next Steps

After completing the migration:

1. Update your adapter's documentation to reflect the new usage patterns
2. Consider publishing your adapter as a separate package for community use
3. Add framework-specific integration examples to your adapter's documentation
4. Update any tutorials or guides that reference the old usage patterns

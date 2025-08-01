# Redis Adapter Example

This is a complete reference implementation of a Redis adapter for EdgeBinder using the extensible adapter system. It demonstrates best practices for creating third-party adapters that work across all PHP frameworks.

## Features

- **Framework Agnostic**: Works with Laminas, Symfony, Laravel, Slim, and any PSR-11 framework
- **Configurable TTL**: Set expiration times for cached bindings
- **Key Prefixing**: Namespace your bindings with custom prefixes
- **Error Handling**: Comprehensive error handling with EdgeBinder exceptions
- **Query Support**: Basic query operations with Redis pattern matching
- **Type Safety**: Full PHP 8.3+ type safety with PHPStan level 8

## Installation

This is an example implementation. In a real package, you would install via Composer:

```bash
composer require myvendor/edgebinder-redis-adapter
```

## Quick Start

### 1. Register the Adapter

```php
use EdgeBinder\Registry\AdapterRegistry;
use MyVendor\RedisAdapter\RedisAdapterFactory;

// Register during application bootstrap
AdapterRegistry::register(new RedisAdapterFactory());
```

### 2. Configure Your Services

```php
// Register Redis client in your container
$container->set('redis.client.cache', function() {
    $redis = new \Redis();
    $redis->connect('localhost', 6379);
    return $redis;
});
```

### 3. Create EdgeBinder Instance

```php
use EdgeBinder\EdgeBinder;

$config = [
    'adapter' => 'redis',
    'redis_client' => 'redis.client.cache',
    'ttl' => 3600,
    'prefix' => 'edgebinder:',
];

$edgeBinder = EdgeBinder::fromConfiguration($config, $container);
```

### 4. Use EdgeBinder

```php
// Create bindings
$binding = $edgeBinder->bind(
    from: $user,
    to: $project,
    type: 'has_access',
    metadata: [
        'access_level' => 'write',
        'granted_at' => new DateTimeImmutable(),
    ]
);

// Query bindings
$projects = $edgeBinder->query()
    ->from($user)
    ->type('has_access')
    ->where('access_level', 'write')
    ->get();
```

## Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `adapter` | string | - | Must be 'redis' |
| `redis_client` | string | 'redis.client.default' | Container service name for Redis client |
| `ttl` | int | 3600 | Time-to-live for cached bindings (seconds) |
| `prefix` | string | 'edgebinder:' | Key prefix for Redis keys |
| `timeout` | int | 30 | Connection timeout (seconds) |

## Framework Integration Examples

### Laminas/Mezzio

```php
// config/autoload/edgebinder.global.php
return [
    'dependencies' => [
        'factories' => [
            'redis.client.cache' => function($container) {
                $redis = new \Redis();
                $redis->connect('localhost', 6379);
                return $redis;
            },
            'edgebinder.cache' => function($container) {
                $config = $container->get('config')['edgebinder']['cache'];
                return EdgeBinder::fromConfiguration($config, $container);
            },
        ],
    ],
    'edgebinder' => [
        'cache' => [
            'adapter' => 'redis',
            'redis_client' => 'redis.client.cache',
            'ttl' => 3600,
            'prefix' => 'edgebinder:cache:',
        ],
    ],
];

// Module.php
public function onBootstrap($e)
{
    AdapterRegistry::register(new RedisAdapterFactory());
}
```

### Symfony

```yaml
# config/services.yaml
services:
    redis.client.cache:
        class: Redis
        calls:
            - [connect, ['localhost', 6379]]
    
    edgebinder.cache:
        class: EdgeBinder\EdgeBinder
        factory: ['EdgeBinder\EdgeBinder', 'fromConfiguration']
        arguments:
            - '%edgebinder.cache%'
            - '@service_container'

# config/packages/edgebinder.yaml
parameters:
    edgebinder.cache:
        adapter: 'redis'
        redis_client: 'redis.client.cache'
        ttl: 3600
        prefix: 'edgebinder:cache:'
```

### Laravel

```php
// config/edgebinder.php
return [
    'cache' => [
        'adapter' => 'redis',
        'redis_client' => 'redis.client.cache',
        'ttl' => env('EDGEBINDER_CACHE_TTL', 3600),
        'prefix' => env('EDGEBINDER_CACHE_PREFIX', 'edgebinder:cache:'),
    ],
];

// app/Providers/EdgeBinderServiceProvider.php
public function register()
{
    $this->app->singleton('redis.client.cache', function ($app) {
        $redis = new \Redis();
        $redis->connect(config('database.redis.default.host'), config('database.redis.default.port'));
        return $redis;
    });
    
    $this->app->singleton('edgebinder.cache', function ($app) {
        $config = config('edgebinder.cache');
        return EdgeBinder::fromConfiguration($config, $app);
    });
}

public function boot()
{
    AdapterRegistry::register(new RedisAdapterFactory());
}
```

## Implementation Details

### Key Structure

The adapter uses the following Redis key structure:

```
{prefix}{binding_id}
```

For example:
- `edgebinder:01234567-89ab-cdef-0123-456789abcdef`
- `myapp:cache:01234567-89ab-cdef-0123-456789abcdef`

### Data Format

Bindings are stored as JSON-encoded arrays:

```json
{
    "id": "01234567-89ab-cdef-0123-456789abcdef",
    "fromType": "User",
    "fromId": "user123",
    "toType": "Project",
    "toId": "project456",
    "type": "has_access",
    "metadata": {
        "access_level": "write",
        "granted_at": "2024-01-15T10:30:00+00:00"
    },
    "createdAt": "2024-01-15T10:30:00+00:00",
    "updatedAt": "2024-01-15T10:30:00+00:00"
}
```

### Query Implementation

The adapter supports basic query operations using Redis pattern matching:

- **Type queries**: Uses key patterns to find bindings by type
- **Metadata queries**: Loads and filters bindings in memory (suitable for small result sets)
- **Entity queries**: Uses key patterns to find bindings by entity

## Limitations

This Redis adapter is designed for caching and simple use cases. For production use, consider:

1. **Query Performance**: Complex queries load data into memory for filtering
2. **Memory Usage**: Large result sets may consume significant memory
3. **Consistency**: Redis doesn't provide ACID transactions like SQL databases
4. **Persistence**: Configure Redis persistence based on your durability requirements

For complex relationship queries, consider using a graph database adapter like Neo4j or JanusGraph.

## Testing

The example includes comprehensive tests:

```bash
# Run tests (if this were a real package)
composer test

# Run with coverage
composer test-coverage

# Static analysis
composer phpstan
```

## Files

- `src/RedisAdapter.php` - Main adapter implementation
- `src/RedisAdapterFactory.php` - Factory for creating adapter instances
- `tests/RedisAdapterTest.php` - Unit tests
- `tests/RedisAdapterFactoryTest.php` - Factory tests
- `tests/Integration/RedisIntegrationTest.php` - Integration tests

## Contributing

This is a reference implementation. For a real Redis adapter package:

1. Fork the repository
2. Create a feature branch
3. Write tests for your changes
4. Ensure all tests pass
5. Submit a pull request

## License

This example is provided under the same license as EdgeBinder Core (Apache 2.0).

# Framework Integration Guide

This guide provides specific examples for integrating EdgeBinder's extensible adapter system with popular PHP frameworks. Each framework has its own patterns for service registration and dependency injection, but EdgeBinder's adapter system works consistently across all of them.

## Overview

The integration process follows the same pattern across all frameworks:

1. **Install adapter packages** - Adapters auto-register when packages are loaded
2. **Configure your services** (database clients, etc.) in the container
3. **Create EdgeBinder instances** using `EdgeBinder::fromConfiguration()`
4. **Use consistent configuration** that works across frameworks

## Laminas/Mezzio Integration

### Service Configuration

```php
// config/autoload/edgebinder.global.php
return [
    'dependencies' => [
        'factories' => [
            'redis.client.cache' => function($container) {
                $config = $container->get('config')['redis']['cache'];
                $redis = new \Redis();
                $redis->connect($config['host'], $config['port']);
                return $redis;
            },
            
            'edgebinder.cache' => function($container) {
                $config = $container->get('config')['edgebinder']['cache'];
                return \EdgeBinder\EdgeBinder::fromConfiguration($config, $container);
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
    
    'redis' => [
        'cache' => [
            'host' => 'localhost',
            'port' => 6379,
        ],
    ],
];
```

### Module Registration

```php
// src/App/Module.php
<?php
namespace App;

use EdgeBinder\Registry\AdapterRegistry;
use MyVendor\RedisAdapter\RedisAdapterFactory;

class Module
{
    public function onBootstrap($e)
    {
        // Adapters auto-register when packages are loaded
        // No manual registration needed
    }
    
    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }
}
```

### ConfigProvider Pattern (Mezzio)

```php
// src/App/ConfigProvider.php
<?php
namespace App;

use EdgeBinder\Registry\AdapterRegistry;
use MyVendor\RedisAdapter\RedisAdapterFactory;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
        ];
    }
    
    public function getDependencies(): array
    {
        return [
            'factories' => [
                'edgebinder.cache' => function($container) {
                    // Adapter auto-registers when package is loaded
                    $config = $container->get('config')['edgebinder']['cache'];
                    return \EdgeBinder\EdgeBinder::fromConfiguration($config, $container);
                },
            ],
        ];
    }
}
```

## Symfony Integration

### Service Configuration

```yaml
# config/services.yaml
services:
    # Redis client service
    redis.client.cache:
        class: Redis
        calls:
            - [connect, ['%redis_host%', '%redis_port%']]
    
    # EdgeBinder service
    edgebinder.cache:
        class: EdgeBinder\EdgeBinder
        factory: ['EdgeBinder\EdgeBinder', 'fromConfiguration']
        arguments:
            - '%edgebinder.cache%'
            - '@service_container'

# config/packages/edgebinder.yaml
parameters:
    redis_host: 'localhost'
    redis_port: 6379
    
    edgebinder.cache:
        adapter: 'redis'
        redis_client: 'redis.client.cache'
        ttl: 3600
        prefix: 'edgebinder:cache:'
```

### Bundle Integration

```php
// src/EdgeBinderBundle/EdgeBinderBundle.php
<?php
namespace App\EdgeBinderBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use EdgeBinder\Registry\AdapterRegistry;
use MyVendor\RedisAdapter\RedisAdapterFactory;

class EdgeBinderBundle extends Bundle
{
    public function boot()
    {
        parent::boot();

        // Adapters auto-register when packages are loaded
        // No manual registration needed
    }
}
```

### Package-Based Auto-Registration

With modern EdgeBinder adapters, no compiler pass is needed. Adapters auto-register when their packages are loaded via Composer's autoload files mechanism.

```yaml
# Tag your adapter factories
services:
    MyVendor\RedisAdapter\RedisAdapterFactory:
        tags: ['edgebinder.adapter_factory']
```

## Laravel Integration

### Service Provider

```php
// app/Providers/EdgeBinderServiceProvider.php
<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use EdgeBinder\Registry\AdapterRegistry;
use MyVendor\RedisAdapter\RedisAdapterFactory;
use EdgeBinder\EdgeBinder;

class EdgeBinderServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Register Redis client
        $this->app->singleton('redis.client.cache', function ($app) {
            $config = config('database.redis.cache');
            $redis = new \Redis();
            $redis->connect($config['host'], $config['port']);
            return $redis;
        });
        
        // Register EdgeBinder instances
        $this->app->singleton('edgebinder.cache', function ($app) {
            $config = config('edgebinder.cache');
            return EdgeBinder::fromConfiguration($config, $app);
        });
    }
    
    public function boot()
    {
        // Adapters auto-register when packages are loaded

        // Publish configuration
        $this->publishes([
            __DIR__.'/../../config/edgebinder.php' => config_path('edgebinder.php'),
        ], 'edgebinder-config');
    }
}
```

### Configuration

```php
// config/edgebinder.php
<?php
return [
    'cache' => [
        'adapter' => 'redis',
        'redis_client' => 'redis.client.cache',
        'ttl' => env('EDGEBINDER_CACHE_TTL', 3600),
        'prefix' => env('EDGEBINDER_CACHE_PREFIX', 'edgebinder:cache:'),
    ],
    
    'social' => [
        'adapter' => 'neo4j',
        'neo4j_client' => 'neo4j.client.social',
        'database' => env('NEO4J_DATABASE', 'social'),
    ],
];
```

### Usage in Controllers

```php
// app/Http/Controllers/UserController.php
<?php
namespace App\Http\Controllers;

use Illuminate\Http\Controller;
use EdgeBinder\EdgeBinder;

class UserController extends Controller
{
    private EdgeBinder $edgeBinder;
    
    public function __construct()
    {
        $this->edgeBinder = app('edgebinder.cache');
    }
    
    public function addFriend(Request $request)
    {
        $user = auth()->user();
        $friend = User::findOrFail($request->friend_id);
        
        $this->edgeBinder->bind(
            from: $user,
            to: $friend,
            type: 'friend',
            metadata: [
                'created_at' => now(),
                'status' => 'pending',
            ]
        );
        
        return response()->json(['status' => 'success']);
    }
}
```

## Slim Framework Integration

### Container Configuration

```php
// config/container.php
<?php
use DI\ContainerBuilder;
use EdgeBinder\Registry\AdapterRegistry;
use MyVendor\RedisAdapter\RedisAdapterFactory;

$containerBuilder = new ContainerBuilder();

$containerBuilder->addDefinitions([
    // Redis client
    'redis.client.cache' => function() {
        $redis = new \Redis();
        $redis->connect('localhost', 6379);
        return $redis;
    },
    
    // EdgeBinder
    'edgebinder.cache' => function($container) {
        $config = [
            'adapter' => 'redis',
            'redis_client' => 'redis.client.cache',
            'ttl' => 3600,
            'prefix' => 'edgebinder:cache:',
        ];
        
        return \EdgeBinder\EdgeBinder::fromConfiguration($config, $container);
    },
]);

// Adapters auto-register when packages are loaded

return $containerBuilder->build();
```

### Application Bootstrap

```php
// public/index.php
<?php
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

// Create container
$container = require __DIR__ . '/../config/container.php';

// Create app
AppFactory::setContainer($container);
$app = AppFactory::create();

// Routes
$app->post('/users/{id}/friends', function($request, $response, $args) {
    $edgeBinder = $this->get('edgebinder.cache');
    
    // Use EdgeBinder...
    
    return $response->withJson(['status' => 'success']);
});

$app->run();
```

## Generic PHP Integration

For applications not using a specific framework:

```php
// bootstrap.php
<?php
require_once 'vendor/autoload.php';

use EdgeBinder\Registry\AdapterRegistry;
use MyVendor\RedisAdapter\RedisAdapterFactory;
use EdgeBinder\EdgeBinder;

// Create a simple PSR-11 container
class SimpleContainer implements \Psr\Container\ContainerInterface
{
    private array $services = [];
    
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
    
    public function set(string $id, $service): void
    {
        $this->services[$id] = $service;
    }
}

// Setup container
$container = new SimpleContainer();

// Register services
$redis = new \Redis();
$redis->connect('localhost', 6379);
$container->set('redis.client.cache', $redis);

// Adapters auto-register when packages are loaded

// Create EdgeBinder
$config = [
    'adapter' => 'redis',
    'redis_client' => 'redis.client.cache',
    'ttl' => 3600,
    'prefix' => 'myapp:',
];

$edgeBinder = EdgeBinder::fromConfiguration($config, $container);

// Use EdgeBinder
$edgeBinder->bind($user, $project, 'has_access', ['level' => 'admin']);
```

## Configuration Best Practices

### Environment-Specific Configuration

```php
// Use environment variables for sensitive data
$config = [
    'adapter' => 'redis',
    'redis_client' => 'redis.client.cache',
    'ttl' => (int) ($_ENV['CACHE_TTL'] ?? 3600),
    'prefix' => $_ENV['CACHE_PREFIX'] ?? 'edgebinder:',
];
```

### Multiple Adapter Instances

```php
// Support multiple EdgeBinder instances with different adapters
$configs = [
    'cache' => [
        'adapter' => 'redis',
        'redis_client' => 'redis.client.cache',
        'ttl' => 3600,
    ],
    'social' => [
        'adapter' => 'neo4j',
        'neo4j_client' => 'neo4j.client.social',
        'database' => 'social_graph',
    ],
    'analytics' => [
        'adapter' => 'weaviate',
        'weaviate_client' => 'weaviate.client.analytics',
        'collection_name' => 'AnalyticsBindings',
    ],
];

foreach ($configs as $name => $config) {
    $container->set("edgebinder.{$name}", 
        EdgeBinder::fromConfiguration($config, $container)
    );
}
```

## Testing Framework Integration

### Unit Testing

```php
// Test adapter registration in framework context
public function testFrameworkIntegration(): void
{
    // Adapter auto-registers when package is loaded

    // Verify adapter is available
    $this->assertTrue(AdapterRegistry::hasAdapter('redis'));

    // Test EdgeBinder creation
    $container = $this->createMockContainer();
    $config = ['adapter' => 'redis', 'redis_client' => 'test.redis'];

    $edgeBinder = EdgeBinder::fromConfiguration($config, $container);
    $this->assertInstanceOf(EdgeBinder::class, $edgeBinder);
}
```

### Integration Testing

```php
// Test with real framework components
public function testWithRealFramework(): void
{
    // Use framework's test container
    $container = $this->getFrameworkContainer();
    
    // Register real services
    $container->set('redis.client.test', new \Redis());
    
    // Test EdgeBinder creation and usage
    $config = ['adapter' => 'redis', 'redis_client' => 'redis.client.test'];
    $edgeBinder = EdgeBinder::fromConfiguration($config, $container);
    
    // Test actual operations
    $binding = $edgeBinder->bind($user, $project, 'test');
    $this->assertNotNull($binding);
}
```

## Troubleshooting

### Common Framework Issues

1. **Service Not Found**: Ensure client services are properly registered in the container
2. **Adapter Not Found**: Ensure adapter package is installed and auto-registration bootstrap is working
3. **Configuration Mismatch**: Verify configuration structure matches framework patterns
4. **Container Scope**: Ensure container has access to required services

### Debug Helpers

```php
// Check what's registered in the container
foreach ($container->getKnownEntryNames() as $name) {
    echo "Service: {$name}\n";
}

// Check registered adapters
$types = AdapterRegistry::getRegisteredTypes();
echo "Registered adapters: " . implode(', ', $types) . "\n";
```

## Next Steps

1. Choose your framework and follow the specific integration pattern
2. Review the [Extensible Adapters Guide](EXTENSIBLE_ADAPTERS.md) for adapter development
3. Check the [Redis Adapter Example](../examples/RedisAdapter/) for a complete implementation
4. See the [Migration Guide](MIGRATION_GUIDE.md) for converting existing adapters

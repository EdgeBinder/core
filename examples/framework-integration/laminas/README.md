# Laminas/Mezzio Integration Example

This example demonstrates how to integrate EdgeBinder's extensible adapter system with a Laminas/Mezzio application.

## Project Structure

```
laminas-example/
├── config/
│   ├── autoload/
│   │   ├── dependencies.global.php
│   │   ├── edgebinder.global.php
│   │   └── redis.global.php
│   └── config.php
├── src/
│   ├── App/
│   │   ├── ConfigProvider.php
│   │   ├── Handler/
│   │   │   └── UserRelationshipHandler.php
│   │   └── Service/
│   │       └── UserService.php
│   └── Module.php
├── public/
│   └── index.php
└── composer.json
```

## Installation

1. Install dependencies:
```bash
composer require edgebinder/core
composer require myvendor/redis-adapter
composer require laminas/laminas-diactoros
composer require mezzio/mezzio
composer require mezzio/mezzio-router
composer require mezzio/mezzio-fastroute
```

2. Configure Redis client and EdgeBinder services
3. Register adapter factories in application bootstrap
4. Use EdgeBinder in your handlers and services

## Configuration

### Redis Configuration (`config/autoload/redis.global.php`)

```php
<?php
return [
    'redis' => [
        'cache' => [
            'host' => $_ENV['REDIS_HOST'] ?? 'localhost',
            'port' => (int) ($_ENV['REDIS_PORT'] ?? 6379),
            'database' => (int) ($_ENV['REDIS_DB'] ?? 0),
        ],
        'session' => [
            'host' => $_ENV['REDIS_SESSION_HOST'] ?? 'localhost',
            'port' => (int) ($_ENV['REDIS_SESSION_PORT'] ?? 6379),
            'database' => (int) ($_ENV['REDIS_SESSION_DB'] ?? 1),
        ],
    ],
];
```

### EdgeBinder Configuration (`config/autoload/edgebinder.global.php`)

```php
<?php
return [
    'edgebinder' => [
        'user_relationships' => [
            'adapter' => 'redis',
            'redis_client' => 'redis.client.cache',
            'ttl' => 3600,
            'prefix' => 'user_relationships:',
        ],
        'content_relationships' => [
            'adapter' => 'redis',
            'redis_client' => 'redis.client.session',
            'ttl' => 7200,
            'prefix' => 'content_relationships:',
        ],
    ],
];
```

### Service Dependencies (`config/autoload/dependencies.global.php`)

```php
<?php
use MyVendor\RedisAdapter\RedisAdapterFactory;

return [
    'dependencies' => [
        'factories' => [
            // Redis clients
            'redis.client.cache' => function ($container) {
                $config = $container->get('config')['redis']['cache'];
                $redis = new \Redis();
                $redis->connect($config['host'], $config['port']);
                $redis->select($config['database']);
                return $redis;
            },
            
            'redis.client.session' => function ($container) {
                $config = $container->get('config')['redis']['session'];
                $redis = new \Redis();
                $redis->connect($config['host'], $config['port']);
                $redis->select($config['database']);
                return $redis;
            },
            
            // EdgeBinder instances
            'edgebinder.user_relationships' => function ($container) {
                $config = $container->get('config')['edgebinder']['user_relationships'];
                return \EdgeBinder\EdgeBinder::fromConfiguration($config, $container);
            },
            
            'edgebinder.content_relationships' => function ($container) {
                $config = $container->get('config')['edgebinder']['content_relationships'];
                return \EdgeBinder\EdgeBinder::fromConfiguration($config, $container);
            },
            
            // Application services
            \App\Service\UserService::class => function ($container) {
                return new \App\Service\UserService(
                    $container->get('edgebinder.user_relationships')
                );
            },
            
            \App\Handler\UserRelationshipHandler::class => function ($container) {
                return new \App\Handler\UserRelationshipHandler(
                    $container->get(\App\Service\UserService::class)
                );
            },
        ],
    ],
];
```

## Application Bootstrap

### Module Registration (`src/Module.php`)

```php
<?php
namespace App;

use EdgeBinder\Registry\AdapterRegistry;
use MyVendor\RedisAdapter\RedisAdapterFactory;

class Module
{
    public function onBootstrap($e)
    {
        // Register EdgeBinder adapter factories
        AdapterRegistry::register(new RedisAdapterFactory());
    }
    
    public function getConfig()
    {
        return [
            'dependencies' => (new ConfigProvider())->getDependencies(),
        ];
    }
}
```

### ConfigProvider (`src/App/ConfigProvider.php`)

```php
<?php
namespace App;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
            'routes' => $this->getRoutes(),
        ];
    }
    
    public function getDependencies(): array
    {
        return [
            'factories' => [
                Handler\UserRelationshipHandler::class => function ($container) {
                    return new Handler\UserRelationshipHandler(
                        $container->get(Service\UserService::class)
                    );
                },
            ],
        ];
    }
    
    public function getRoutes(): array
    {
        return [
            [
                'name' => 'user.relationships.create',
                'path' => '/users/{userId}/relationships',
                'middleware' => Handler\UserRelationshipHandler::class,
                'allowed_methods' => ['POST'],
            ],
            [
                'name' => 'user.relationships.list',
                'path' => '/users/{userId}/relationships',
                'middleware' => Handler\UserRelationshipHandler::class,
                'allowed_methods' => ['GET'],
            ],
        ];
    }
}
```

## Usage Examples

### Service Layer (`src/App/Service/UserService.php`)

```php
<?php
namespace App\Service;

use EdgeBinder\EdgeBinder;

class UserService
{
    private EdgeBinder $edgeBinder;
    
    public function __construct(EdgeBinder $edgeBinder)
    {
        $this->edgeBinder = $edgeBinder;
    }
    
    public function addFriend(string $userId, string $friendId): void
    {
        $user = $this->createUserEntity($userId);
        $friend = $this->createUserEntity($friendId);
        
        $this->edgeBinder->bind(
            from: $user,
            to: $friend,
            type: 'friend',
            metadata: [
                'created_at' => new \DateTimeImmutable(),
                'status' => 'pending',
            ]
        );
    }
    
    public function getFriends(string $userId): array
    {
        $user = $this->createUserEntity($userId);
        
        return $this->edgeBinder->query()
            ->from($user)
            ->type('friend')
            ->where('status', 'accepted')
            ->get();
    }
    
    public function acceptFriendRequest(string $userId, string $friendId): void
    {
        $user = $this->createUserEntity($userId);
        $friend = $this->createUserEntity($friendId);
        
        $bindings = $this->edgeBinder->query()
            ->from($friend)
            ->to($user)
            ->type('friend')
            ->where('status', 'pending')
            ->get();
            
        foreach ($bindings as $binding) {
            $metadata = $binding->getMetadata();
            $metadata['status'] = 'accepted';
            $metadata['accepted_at'] = new \DateTimeImmutable();
            
            // Update binding with new metadata
            $this->edgeBinder->unbind($binding->getId());
            $this->edgeBinder->bind($friend, $user, 'friend', $metadata);
        }
    }
    
    private function createUserEntity(string $userId): object
    {
        return new class($userId) {
            public function __construct(private string $id) {}
            public function getId(): string { return $this->id; }
        };
    }
}
```

### HTTP Handler (`src/App/Handler/UserRelationshipHandler.php`)

```php
<?php
namespace App\Handler;

use App\Service\UserService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Laminas\Diactoros\Response\JsonResponse;

class UserRelationshipHandler implements RequestHandlerInterface
{
    private UserService $userService;
    
    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }
    
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $method = $request->getMethod();
        $userId = $request->getAttribute('userId');
        
        switch ($method) {
            case 'POST':
                return $this->createRelationship($request, $userId);
            case 'GET':
                return $this->listRelationships($request, $userId);
            default:
                return new JsonResponse(['error' => 'Method not allowed'], 405);
        }
    }
    
    private function createRelationship(ServerRequestInterface $request, string $userId): ResponseInterface
    {
        $body = json_decode($request->getBody()->getContents(), true);
        $friendId = $body['friend_id'] ?? null;
        
        if (!$friendId) {
            return new JsonResponse(['error' => 'friend_id is required'], 400);
        }
        
        try {
            $this->userService->addFriend($userId, $friendId);
            return new JsonResponse(['status' => 'Friend request sent'], 201);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }
    
    private function listRelationships(ServerRequestInterface $request, string $userId): ResponseInterface
    {
        try {
            $friends = $this->userService->getFriends($userId);
            
            $friendData = array_map(function ($binding) {
                return [
                    'friend_id' => $binding->getToId(),
                    'status' => $binding->getMetadata()['status'] ?? 'unknown',
                    'created_at' => $binding->getMetadata()['created_at'] ?? null,
                ];
            }, $friends);
            
            return new JsonResponse(['friends' => $friendData]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }
}
```

## Testing

### Integration Test

```php
<?php
namespace AppTest\Integration;

use PHPUnit\Framework\TestCase;
use EdgeBinder\Registry\AdapterRegistry;
use MyVendor\RedisAdapter\RedisAdapterFactory;
use App\Service\UserService;

class LaminasIntegrationTest extends TestCase
{
    private $container;
    
    protected function setUp(): void
    {
        AdapterRegistry::clear();
        AdapterRegistry::register(new RedisAdapterFactory());
        
        // Setup test container with mock Redis
        $this->container = $this->createTestContainer();
    }
    
    protected function tearDown(): void
    {
        AdapterRegistry::clear();
    }
    
    public function testUserServiceIntegration(): void
    {
        $userService = $this->container->get(UserService::class);
        
        // Test adding a friend
        $userService->addFriend('user1', 'user2');
        
        // Test getting friends
        $friends = $userService->getFriends('user1');
        $this->assertCount(1, $friends);
        
        // Test accepting friend request
        $userService->acceptFriendRequest('user1', 'user2');
        
        $acceptedFriends = $userService->getFriends('user1');
        $this->assertCount(1, $acceptedFriends);
        $this->assertEquals('accepted', $acceptedFriends[0]->getMetadata()['status']);
    }
    
    private function createTestContainer()
    {
        // Create a test container with mock services
        // Implementation details...
    }
}
```

## Production Deployment

### Environment Variables

```bash
# .env
REDIS_HOST=redis.example.com
REDIS_PORT=6379
REDIS_DB=0
REDIS_SESSION_HOST=redis-session.example.com
REDIS_SESSION_PORT=6379
REDIS_SESSION_DB=1
```

### Docker Configuration

```dockerfile
# Dockerfile
FROM php:8.3-fpm

RUN docker-php-ext-install redis
COPY . /var/www/html
WORKDIR /var/www/html

RUN composer install --no-dev --optimize-autoloader
```

This example demonstrates a complete Laminas/Mezzio integration with EdgeBinder's extensible adapter system, showing real-world usage patterns and best practices.

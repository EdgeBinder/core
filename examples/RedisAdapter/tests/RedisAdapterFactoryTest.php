<?php
declare(strict_types=1);

namespace MyVendor\RedisAdapter\Tests;

use PHPUnit\Framework\TestCase;
use MyVendor\RedisAdapter\RedisAdapterFactory;
use MyVendor\RedisAdapter\RedisAdapter;
use EdgeBinder\Exception\AdapterException;
use EdgeBinder\Registry\AdapterRegistry;
use EdgeBinder\EdgeBinder;
use Psr\Container\ContainerInterface;

/**
 * Unit tests for RedisAdapterFactory.
 */
class RedisAdapterFactoryTest extends TestCase
{
    private RedisAdapterFactory $factory;
    private ContainerInterface $mockContainer;
    private \Redis $mockRedis;

    protected function setUp(): void
    {
        $this->factory = new RedisAdapterFactory();
        $this->mockContainer = $this->createMock(ContainerInterface::class);
        $this->mockRedis = $this->createMock(\Redis::class);
        
        // Clear registry for clean tests
        AdapterRegistry::clear();
    }

    protected function tearDown(): void
    {
        AdapterRegistry::clear();
    }

    public function testGetAdapterType(): void
    {
        $this->assertEquals('redis', $this->factory->getAdapterType());
    }

    public function testCreateAdapterWithMinimalConfig(): void
    {
        $this->mockRedis->method('ping')->willReturn(true);
        
        $this->mockContainer
            ->method('has')
            ->with('redis.client.default')
            ->willReturn(true);
            
        $this->mockContainer
            ->method('get')
            ->with('redis.client.default')
            ->willReturn($this->mockRedis);

        $config = [
            'container' => $this->mockContainer,
            'instance' => [
                'redis_client' => 'redis.client.default',
            ],
            'global' => [],
        ];

        $adapter = $this->factory->createAdapter($config);
        
        $this->assertInstanceOf(RedisAdapter::class, $adapter);
    }

    public function testCreateAdapterWithFullConfig(): void
    {
        $this->mockRedis->method('ping')->willReturn(true);
        
        $this->mockContainer
            ->method('has')
            ->with('redis.client.cache')
            ->willReturn(true);
            
        $this->mockContainer
            ->method('get')
            ->with('redis.client.cache')
            ->willReturn($this->mockRedis);

        $config = [
            'container' => $this->mockContainer,
            'instance' => [
                'redis_client' => 'redis.client.cache',
                'ttl' => 7200,
                'prefix' => 'myapp:',
                'timeout' => 60,
                'max_metadata_size' => 2097152,
            ],
            'global' => [
                'debug' => true,
            ],
        ];

        $adapter = $this->factory->createAdapter($config);
        
        $this->assertInstanceOf(RedisAdapter::class, $adapter);
    }

    public function testCreateAdapterMissingContainer(): void
    {
        $config = [
            'instance' => [],
            'global' => [],
        ];

        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage('Missing required configuration for adapter type \'redis\': container');

        $this->factory->createAdapter($config);
    }

    public function testCreateAdapterMissingInstance(): void
    {
        $config = [
            'container' => $this->mockContainer,
            'global' => [],
        ];

        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage('Missing required configuration for adapter type \'redis\': instance');

        $this->factory->createAdapter($config);
    }

    public function testCreateAdapterMissingGlobal(): void
    {
        $config = [
            'container' => $this->mockContainer,
            'instance' => [],
        ];

        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage('Missing required configuration for adapter type \'redis\': global');

        $this->factory->createAdapter($config);
    }

    public function testCreateAdapterInvalidContainer(): void
    {
        $config = [
            'container' => 'not a container',
            'instance' => [],
            'global' => [],
        ];

        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage('Container must implement Psr\Container\ContainerInterface');

        $this->factory->createAdapter($config);
    }

    public function testCreateAdapterInvalidInstanceConfig(): void
    {
        $config = [
            'container' => $this->mockContainer,
            'instance' => 'not an array',
            'global' => [],
        ];

        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage('Instance configuration must be an array');

        $this->factory->createAdapter($config);
    }

    public function testCreateAdapterRedisServiceNotFound(): void
    {
        $this->mockContainer
            ->method('has')
            ->with('redis.client.missing')
            ->willReturn(false);

        $config = [
            'container' => $this->mockContainer,
            'instance' => [
                'redis_client' => 'redis.client.missing',
            ],
            'global' => [],
        ];

        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage('Redis client service \'redis.client.missing\' not found in container');

        $this->factory->createAdapter($config);
    }

    public function testCreateAdapterInvalidRedisClient(): void
    {
        $this->mockContainer
            ->method('has')
            ->with('redis.client.invalid')
            ->willReturn(true);
            
        $this->mockContainer
            ->method('get')
            ->with('redis.client.invalid')
            ->willReturn(new \stdClass()); // Not a Redis instance

        $config = [
            'container' => $this->mockContainer,
            'instance' => [
                'redis_client' => 'redis.client.invalid',
            ],
            'global' => [],
        ];

        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage('Service \'redis.client.invalid\' must return a Redis instance, got stdClass');

        $this->factory->createAdapter($config);
    }

    public function testCreateAdapterRedisNotConnected(): void
    {
        $this->mockRedis->method('ping')->willReturn(false);
        
        $this->mockContainer
            ->method('has')
            ->with('redis.client.disconnected')
            ->willReturn(true);
            
        $this->mockContainer
            ->method('get')
            ->with('redis.client.disconnected')
            ->willReturn($this->mockRedis);

        $config = [
            'container' => $this->mockContainer,
            'instance' => [
                'redis_client' => 'redis.client.disconnected',
            ],
            'global' => [],
        ];

        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage('Redis client is not connected');

        $this->factory->createAdapter($config);
    }

    public function testCreateAdapterInvalidTtl(): void
    {
        $this->mockRedis->method('ping')->willReturn(true);
        
        $this->mockContainer
            ->method('has')
            ->with('redis.client.default')
            ->willReturn(true);
            
        $this->mockContainer
            ->method('get')
            ->with('redis.client.default')
            ->willReturn($this->mockRedis);

        $config = [
            'container' => $this->mockContainer,
            'instance' => [
                'redis_client' => 'redis.client.default',
                'ttl' => -1, // Invalid TTL
            ],
            'global' => [],
        ];

        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage('TTL must be a positive integer');

        $this->factory->createAdapter($config);
    }

    public function testCreateAdapterInvalidPrefix(): void
    {
        $this->mockRedis->method('ping')->willReturn(true);
        
        $this->mockContainer
            ->method('has')
            ->with('redis.client.default')
            ->willReturn(true);
            
        $this->mockContainer
            ->method('get')
            ->with('redis.client.default')
            ->willReturn($this->mockRedis);

        $config = [
            'container' => $this->mockContainer,
            'instance' => [
                'redis_client' => 'redis.client.default',
                'prefix' => 123, // Invalid prefix (not string)
            ],
            'global' => [],
        ];

        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage('Prefix must be a string');

        $this->factory->createAdapter($config);
    }

    public function testRegistryIntegration(): void
    {
        // Register the factory
        AdapterRegistry::register($this->factory);
        
        // Verify registration
        $this->assertTrue(AdapterRegistry::hasAdapter('redis'));
        $this->assertContains('redis', AdapterRegistry::getRegisteredTypes());
        
        // Setup mock container
        $this->mockRedis->method('ping')->willReturn(true);
        $this->mockContainer
            ->method('has')
            ->with('redis.client.test')
            ->willReturn(true);
        $this->mockContainer
            ->method('get')
            ->with('redis.client.test')
            ->willReturn($this->mockRedis);

        // Test adapter creation through registry
        $config = [
            'container' => $this->mockContainer,
            'instance' => [
                'redis_client' => 'redis.client.test',
                'ttl' => 1800,
            ],
            'global' => [],
        ];

        $adapter = AdapterRegistry::create('redis', $config);
        $this->assertInstanceOf(RedisAdapter::class, $adapter);
    }

    public function testEdgeBinderIntegration(): void
    {
        // Register the factory
        AdapterRegistry::register($this->factory);
        
        // Setup mock container
        $this->mockRedis->method('ping')->willReturn(true);
        $this->mockContainer
            ->method('has')
            ->with('redis.client.integration')
            ->willReturn(true);
        $this->mockContainer
            ->method('get')
            ->with('redis.client.integration')
            ->willReturn($this->mockRedis);

        // Test EdgeBinder creation
        $config = [
            'adapter' => 'redis',
            'redis_client' => 'redis.client.integration',
            'ttl' => 3600,
            'prefix' => 'test:',
        ];

        $edgeBinder = EdgeBinder::fromConfiguration($config, $this->mockContainer);
        $this->assertInstanceOf(EdgeBinder::class, $edgeBinder);
    }

    public function testCreateAdapterWithContainerException(): void
    {
        $this->mockContainer
            ->method('has')
            ->with('redis.client.error')
            ->willReturn(true);
            
        $this->mockContainer
            ->method('get')
            ->with('redis.client.error')
            ->willThrowException(new \Exception('Container error'));

        $config = [
            'container' => $this->mockContainer,
            'instance' => [
                'redis_client' => 'redis.client.error',
            ],
            'global' => [],
        ];

        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage('Failed to get Redis client \'redis.client.error\': Container error');

        $this->factory->createAdapter($config);
    }

    public function testCreateAdapterWithRedisException(): void
    {
        $this->mockRedis->method('ping')->willThrowException(new \RedisException('Redis error'));
        
        $this->mockContainer
            ->method('has')
            ->with('redis.client.error')
            ->willReturn(true);
            
        $this->mockContainer
            ->method('get')
            ->with('redis.client.error')
            ->willReturn($this->mockRedis);

        $config = [
            'container' => $this->mockContainer,
            'instance' => [
                'redis_client' => 'redis.client.error',
            ],
            'global' => [],
        ];

        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage('Failed to create adapter of type \'redis\'');

        $this->factory->createAdapter($config);
    }
}

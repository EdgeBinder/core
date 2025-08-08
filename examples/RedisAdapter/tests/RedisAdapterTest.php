<?php
declare(strict_types=1);

namespace MyVendor\RedisAdapter\Tests;

use PHPUnit\Framework\TestCase;
use MyVendor\RedisAdapter\RedisAdapter;
use EdgeBinder\Binding;
use EdgeBinder\Exception\PersistenceException;
use EdgeBinder\Exception\EntityExtractionException;
use EdgeBinder\Exception\InvalidMetadataException;

/**
 * Unit tests for RedisAdapter.
 */
class RedisAdapterTest extends TestCase
{
    private \Redis $mockRedis;
    private RedisAdapter $adapter;

    protected function setUp(): void
    {
        $this->mockRedis = $this->createMock(\Redis::class);
        $this->adapter = new RedisAdapter($this->mockRedis);
    }

    public function testStoreBinding(): void
    {
        $binding = $this->createTestBinding();
        
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

    public function testStoreBindingWithCustomConfig(): void
    {
        $adapter = new RedisAdapter($this->mockRedis, [
            'ttl' => 7200,
            'prefix' => 'myapp:',
        ]);
        
        $binding = $this->createTestBinding();
        
        $this->mockRedis
            ->expects($this->once())
            ->method('setex')
            ->with(
                'myapp:' . $binding->getId(),
                7200,
                $this->isType('string')
            )
            ->willReturn(true);

        $adapter->store($binding);
    }

    public function testStoreBindingFailure(): void
    {
        $binding = $this->createTestBinding();
        
        $this->mockRedis
            ->expects($this->once())
            ->method('setex')
            ->willReturn(false);

        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage('Redis setex returned false');

        $this->adapter->store($binding);
    }

    public function testStoreBindingRedisException(): void
    {
        $binding = $this->createTestBinding();
        
        $this->mockRedis
            ->expects($this->once())
            ->method('setex')
            ->willThrowException(new \RedisException('Connection lost'));

        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage('Redis error: Connection lost');

        $this->adapter->store($binding);
    }

    public function testFindBinding(): void
    {
        $binding = $this->createTestBinding();
        $bindingData = json_encode($binding->toArray());
        
        $this->mockRedis
            ->expects($this->once())
            ->method('get')
            ->with('edgebinder:' . $binding->getId())
            ->willReturn($bindingData);

        $result = $this->adapter->find($binding->getId());
        
        $this->assertInstanceOf(Binding::class, $result);
        $this->assertEquals($binding->getId(), $result->getId());
        $this->assertEquals($binding->getType(), $result->getType());
    }

    public function testFindBindingNotFound(): void
    {
        $this->mockRedis
            ->expects($this->once())
            ->method('get')
            ->with('edgebinder:nonexistent')
            ->willReturn(false);

        $result = $this->adapter->find('nonexistent');
        
        $this->assertNull($result);
    }

    public function testFindBindingInvalidJson(): void
    {
        $this->mockRedis
            ->expects($this->once())
            ->method('get')
            ->with('edgebinder:test')
            ->willReturn('invalid json');

        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage('JSON decoding failed');

        $this->adapter->find('test');
    }

    public function testDeleteBinding(): void
    {
        $this->mockRedis
            ->expects($this->once())
            ->method('del')
            ->with('edgebinder:test-id')
            ->willReturn(1);

        $this->adapter->delete('test-id');
    }

    public function testDeleteBindingNotFound(): void
    {
        $this->mockRedis
            ->expects($this->once())
            ->method('del')
            ->with('edgebinder:nonexistent')
            ->willReturn(0);

        // Should not throw exception for non-existent keys
        $this->adapter->delete('nonexistent');
    }

    public function testDeleteBindingRedisException(): void
    {
        $this->mockRedis
            ->expects($this->once())
            ->method('del')
            ->willThrowException(new \RedisException('Connection lost'));

        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage('Redis error: Connection lost');

        $this->adapter->delete('test-id');
    }

    public function testExecuteQueryBasic(): void
    {
        $binding1 = $this->createTestBinding();
        $binding2 = $this->createTestBinding(['type' => 'different_type']);
        
        $this->mockRedis
            ->expects($this->once())
            ->method('keys')
            ->with('edgebinder:*')
            ->willReturn([
                'edgebinder:' . $binding1->getId(),
                'edgebinder:' . $binding2->getId(),
            ]);

        $this->mockRedis
            ->expects($this->exactly(2))
            ->method('get')
            ->willReturnOnConsecutiveCalls(
                json_encode($binding1->toArray()),
                json_encode($binding2->toArray())
            );

        $criteria = new \EdgeBinder\Query\QueryCriteria();
        $criteria->bindingType = 'has_access';

        $results = $this->adapter->executeQuery($criteria);

        $this->assertInstanceOf(\EdgeBinder\Contracts\QueryResultInterface::class, $results);
        $this->assertCount(1, $results);
        $this->assertEquals($binding1->getId(), $results->first()->getId());
    }

    public function testExecuteQueryNoResults(): void
    {
        $this->mockRedis
            ->expects($this->once())
            ->method('keys')
            ->with('edgebinder:*')
            ->willReturn([]);

        $criteria = new \EdgeBinder\Query\QueryCriteria();

        $results = $this->adapter->executeQuery($criteria);

        $this->assertInstanceOf(\EdgeBinder\Contracts\QueryResultInterface::class, $results);
        $this->assertTrue($results->isEmpty());
    }

    public function testExtractEntityIdFromEntityInterface(): void
    {
        $entity = $this->createMock(\EdgeBinder\Contracts\EntityInterface::class);
        $entity->method('getId')->willReturn('entity-123');

        $id = $this->adapter->extractEntityId($entity);
        
        $this->assertEquals('entity-123', $id);
    }

    public function testExtractEntityIdFromGetIdMethod(): void
    {
        $entity = new class {
            public function getId(): string
            {
                return 'entity-456';
            }
        };

        $id = $this->adapter->extractEntityId($entity);
        
        $this->assertEquals('entity-456', $id);
    }

    public function testExtractEntityIdFromProperty(): void
    {
        $entity = new class {
            public string $id = 'entity-789';
        };

        $id = $this->adapter->extractEntityId($entity);
        
        $this->assertEquals('entity-789', $id);
    }

    public function testExtractEntityIdFailure(): void
    {
        $entity = new class {
            // No id property or method
        };

        $this->expectException(EntityExtractionException::class);
        $this->expectExceptionMessage('Cannot extract entity ID');

        $this->adapter->extractEntityId($entity);
    }

    public function testExtractEntityTypeFromEntityInterface(): void
    {
        $entity = $this->createMock(\EdgeBinder\Contracts\EntityInterface::class);
        $entity->method('getType')->willReturn('User');

        $type = $this->adapter->extractEntityType($entity);
        
        $this->assertEquals('User', $type);
    }

    public function testExtractEntityTypeFromGetTypeMethod(): void
    {
        $entity = new class {
            public function getType(): string
            {
                return 'Project';
            }
        };

        $type = $this->adapter->extractEntityType($entity);
        
        $this->assertEquals('Project', $type);
    }

    public function testExtractEntityTypeFromClassName(): void
    {
        $entity = new \stdClass();

        $type = $this->adapter->extractEntityType($entity);
        
        $this->assertEquals('stdClass', $type);
    }

    public function testValidateAndNormalizeMetadata(): void
    {
        $metadata = [
            'string_value' => 'test',
            'int_value' => 123,
            'bool_value' => true,
            'array_value' => ['nested' => 'data'],
            'datetime_value' => new \DateTimeImmutable('2024-01-15T10:30:00Z'),
        ];

        $normalized = $this->adapter->validateAndNormalizeMetadata($metadata);
        
        $this->assertEquals('test', $normalized['string_value']);
        $this->assertEquals(123, $normalized['int_value']);
        $this->assertTrue($normalized['bool_value']);
        $this->assertEquals(['nested' => 'data'], $normalized['array_value']);
        $this->assertEquals('2024-01-15T10:30:00+00:00', $normalized['datetime_value']);
    }

    public function testValidateAndNormalizeMetadataInvalidKey(): void
    {
        $metadata = ['' => 'value'];

        $this->expectException(InvalidMetadataException::class);
        $this->expectExceptionMessage('Metadata keys must be non-empty strings');

        $this->adapter->validateAndNormalizeMetadata($metadata);
    }

    public function testValidateAndNormalizeMetadataResource(): void
    {
        $metadata = ['resource' => fopen('php://memory', 'r')];

        $this->expectException(InvalidMetadataException::class);
        $this->expectExceptionMessage('cannot be a resource');

        $this->adapter->validateAndNormalizeMetadata($metadata);
    }

    public function testConfigurationValidation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TTL must be a positive integer');

        new RedisAdapter($this->mockRedis, ['ttl' => -1]);
    }

    private function createTestBinding(array $overrides = []): Binding
    {
        $user = new class {
            public string $id = 'user-123';
        };
        
        $project = new class {
            public string $id = 'project-456';
        };

        $metadata = array_merge([
            'access_level' => 'write',
            'granted_at' => '2024-01-15T10:30:00+00:00',
        ], $overrides['metadata'] ?? []);

        return Binding::create(
            $user,
            $project,
            $overrides['type'] ?? 'has_access',
            $metadata
        );
    }
}

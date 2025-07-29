<?php

declare(strict_types=1);

namespace EdgeBinder\Tests;

use EdgeBinder\Binding;
use EdgeBinder\Contracts\BindingInterface;
use EdgeBinder\Contracts\EdgeBinderInterface;
use EdgeBinder\Contracts\PersistenceAdapterInterface;
use EdgeBinder\Contracts\QueryBuilderInterface;
use EdgeBinder\EdgeBinder;
use EdgeBinder\Exception\BindingNotFoundException;
use EdgeBinder\Exception\PersistenceException;
use PHPUnit\Framework\TestCase;

class EdgeBinderTest extends TestCase
{
    private PersistenceAdapterInterface $persistenceAdapter;
    private EdgeBinder $edgeBinder;
    private object $fromEntity;
    private object $toEntity;

    protected function setUp(): void
    {
        $this->persistenceAdapter = $this->createMock(PersistenceAdapterInterface::class);
        $this->edgeBinder = new EdgeBinder($this->persistenceAdapter);
        $this->fromEntity = new \stdClass();
        $this->toEntity = new \stdClass();
    }

    public function testImplementsEdgeBinderInterface(): void
    {
        $this->assertInstanceOf(EdgeBinderInterface::class, $this->edgeBinder);
    }

    public function testBind(): void
    {
        $metadata = ['access_level' => 'admin'];
        $normalizedMetadata = ['access_level' => 'admin', 'normalized' => true];

        $this->persistenceAdapter->expects($this->once())
            ->method('extractEntityType')
            ->with($this->fromEntity)
            ->willReturn('User');

        $this->persistenceAdapter->expects($this->once())
            ->method('extractEntityId')
            ->with($this->fromEntity)
            ->willReturn('user-123');

        $this->persistenceAdapter->expects($this->once())
            ->method('extractEntityType')
            ->with($this->toEntity)
            ->willReturn('Project');

        $this->persistenceAdapter->expects($this->once())
            ->method('extractEntityId')
            ->with($this->toEntity)
            ->willReturn('project-456');

        $this->persistenceAdapter->expects($this->once())
            ->method('validateAndNormalizeMetadata')
            ->with($metadata)
            ->willReturn($normalizedMetadata);

        $this->persistenceAdapter->expects($this->once())
            ->method('store')
            ->with($this->callback(function (BindingInterface $binding) use ($normalizedMetadata) {
                return $binding->getFromType() === 'User'
                    && $binding->getFromId() === 'user-123'
                    && $binding->getToType() === 'Project'
                    && $binding->getToId() === 'project-456'
                    && $binding->getType() === 'has_access'
                    && $binding->getMetadata() === $normalizedMetadata;
            }));

        $result = $this->edgeBinder->bind(
            from: $this->fromEntity,
            to: $this->toEntity,
            type: 'has_access',
            metadata: $metadata
        );

        $this->assertInstanceOf(BindingInterface::class, $result);
        $this->assertEquals('User', $result->getFromType());
        $this->assertEquals('user-123', $result->getFromId());
        $this->assertEquals('Project', $result->getToType());
        $this->assertEquals('project-456', $result->getToId());
        $this->assertEquals('has_access', $result->getType());
        $this->assertEquals($normalizedMetadata, $result->getMetadata());
    }

    public function testBindWithoutMetadata(): void
    {
        $this->persistenceAdapter->method('extractEntityType')->willReturnOnConsecutiveCalls('User', 'Project');
        $this->persistenceAdapter->method('extractEntityId')->willReturnOnConsecutiveCalls('user-1', 'project-1');
        $this->persistenceAdapter->method('validateAndNormalizeMetadata')->willReturn([]);
        $this->persistenceAdapter->expects($this->once())->method('store');

        $result = $this->edgeBinder->bind($this->fromEntity, $this->toEntity, 'owns');

        $this->assertEquals([], $result->getMetadata());
    }

    public function testUnbind(): void
    {
        $bindingId = 'binding-123';

        $this->persistenceAdapter->expects($this->once())
            ->method('delete')
            ->with($bindingId);

        $this->edgeBinder->unbind($bindingId);
    }

    public function testUnbindEntities(): void
    {
        $binding1 = $this->createMock(BindingInterface::class);
        $binding1->method('getId')->willReturn('binding-1');
        $binding2 = $this->createMock(BindingInterface::class);
        $binding2->method('getId')->willReturn('binding-2');

        $this->persistenceAdapter->method('extractEntityType')->willReturnOnConsecutiveCalls('User', 'Project');
        $this->persistenceAdapter->method('extractEntityId')->willReturnOnConsecutiveCalls('user-1', 'project-1');

        $this->persistenceAdapter->expects($this->once())
            ->method('findBetweenEntities')
            ->with('User', 'user-1', 'Project', 'project-1', 'has_access')
            ->willReturn([$binding1, $binding2]);

        $this->persistenceAdapter->expects($this->exactly(2))
            ->method('delete')
            ->withConsecutive(['binding-1'], ['binding-2']);

        $result = $this->edgeBinder->unbindEntities($this->fromEntity, $this->toEntity, 'has_access');

        $this->assertEquals(2, $result);
    }

    public function testUnbindEntitiesWithoutType(): void
    {
        $this->persistenceAdapter->method('extractEntityType')->willReturnOnConsecutiveCalls('User', 'Project');
        $this->persistenceAdapter->method('extractEntityId')->willReturnOnConsecutiveCalls('user-1', 'project-1');

        $this->persistenceAdapter->expects($this->once())
            ->method('findBetweenEntities')
            ->with('User', 'user-1', 'Project', 'project-1', null)
            ->willReturn([]);

        $result = $this->edgeBinder->unbindEntities($this->fromEntity, $this->toEntity);

        $this->assertEquals(0, $result);
    }

    public function testUnbindEntity(): void
    {
        $this->persistenceAdapter->expects($this->once())
            ->method('extractEntityType')
            ->with($this->fromEntity)
            ->willReturn('User');

        $this->persistenceAdapter->expects($this->once())
            ->method('extractEntityId')
            ->with($this->fromEntity)
            ->willReturn('user-123');

        $this->persistenceAdapter->expects($this->once())
            ->method('deleteByEntity')
            ->with('User', 'user-123')
            ->willReturn(5);

        $result = $this->edgeBinder->unbindEntity($this->fromEntity);

        $this->assertEquals(5, $result);
    }

    public function testQuery(): void
    {
        $result = $this->edgeBinder->query();

        $this->assertInstanceOf(QueryBuilderInterface::class, $result);
    }

    public function testFindBinding(): void
    {
        $binding = $this->createMock(BindingInterface::class);
        $bindingId = 'binding-123';

        $this->persistenceAdapter->expects($this->once())
            ->method('find')
            ->with($bindingId)
            ->willReturn($binding);

        $result = $this->edgeBinder->findBinding($bindingId);

        $this->assertSame($binding, $result);
    }

    public function testFindBindingReturnsNullWhenNotFound(): void
    {
        $this->persistenceAdapter->expects($this->once())
            ->method('find')
            ->with('nonexistent')
            ->willReturn(null);

        $result = $this->edgeBinder->findBinding('nonexistent');

        $this->assertNull($result);
    }

    public function testFindBindingsFor(): void
    {
        $bindings = [$this->createMock(BindingInterface::class)];

        $this->persistenceAdapter->expects($this->once())
            ->method('extractEntityType')
            ->with($this->fromEntity)
            ->willReturn('User');

        $this->persistenceAdapter->expects($this->once())
            ->method('extractEntityId')
            ->with($this->fromEntity)
            ->willReturn('user-123');

        $this->persistenceAdapter->expects($this->once())
            ->method('findByEntity')
            ->with('User', 'user-123')
            ->willReturn($bindings);

        $result = $this->edgeBinder->findBindingsFor($this->fromEntity);

        $this->assertSame($bindings, $result);
    }

    public function testFindBindingsBetween(): void
    {
        $bindings = [$this->createMock(BindingInterface::class)];

        $this->persistenceAdapter->method('extractEntityType')->willReturnOnConsecutiveCalls('User', 'Project');
        $this->persistenceAdapter->method('extractEntityId')->willReturnOnConsecutiveCalls('user-1', 'project-1');

        $this->persistenceAdapter->expects($this->once())
            ->method('findBetweenEntities')
            ->with('User', 'user-1', 'Project', 'project-1', 'has_access')
            ->willReturn($bindings);

        $result = $this->edgeBinder->findBindingsBetween($this->fromEntity, $this->toEntity, 'has_access');

        $this->assertSame($bindings, $result);
    }

    public function testAreBoundReturnsTrueWhenBindingsExist(): void
    {
        $bindings = [$this->createMock(BindingInterface::class)];

        $this->persistenceAdapter->method('extractEntityType')->willReturnOnConsecutiveCalls('User', 'Project');
        $this->persistenceAdapter->method('extractEntityId')->willReturnOnConsecutiveCalls('user-1', 'project-1');
        $this->persistenceAdapter->method('findBetweenEntities')->willReturn($bindings);

        $result = $this->edgeBinder->areBound($this->fromEntity, $this->toEntity);

        $this->assertTrue($result);
    }

    public function testAreBoundReturnsFalseWhenNoBindingsExist(): void
    {
        $this->persistenceAdapter->method('extractEntityType')->willReturnOnConsecutiveCalls('User', 'Project');
        $this->persistenceAdapter->method('extractEntityId')->willReturnOnConsecutiveCalls('user-1', 'project-1');
        $this->persistenceAdapter->method('findBetweenEntities')->willReturn([]);

        $result = $this->edgeBinder->areBound($this->fromEntity, $this->toEntity);

        $this->assertFalse($result);
    }

    public function testUpdateMetadata(): void
    {
        $bindingId = 'binding-123';
        $existingMetadata = ['existing' => 'value'];
        $newMetadata = ['new' => 'data'];
        $normalizedMetadata = ['new' => 'normalized_data'];
        $mergedMetadata = ['existing' => 'value', 'new' => 'normalized_data'];

        $existingBinding = $this->createMock(BindingInterface::class);
        $existingBinding->method('getMetadata')->willReturn($existingMetadata);

        $updatedBinding = $this->createMock(BindingInterface::class);

        $this->persistenceAdapter->expects($this->exactly(2))
            ->method('find')
            ->with($bindingId)
            ->willReturnOnConsecutiveCalls($existingBinding, $updatedBinding);

        $this->persistenceAdapter->expects($this->once())
            ->method('validateAndNormalizeMetadata')
            ->with($newMetadata)
            ->willReturn($normalizedMetadata);

        $this->persistenceAdapter->expects($this->once())
            ->method('updateMetadata')
            ->with($bindingId, $mergedMetadata);

        $result = $this->edgeBinder->updateMetadata($bindingId, $newMetadata);

        $this->assertSame($updatedBinding, $result);
    }

    public function testUpdateMetadataThrowsExceptionWhenBindingNotFound(): void
    {
        $this->persistenceAdapter->expects($this->once())
            ->method('find')
            ->with('nonexistent')
            ->willReturn(null);

        $this->expectException(BindingNotFoundException::class);

        $this->edgeBinder->updateMetadata('nonexistent', ['new' => 'data']);
    }

    public function testReplaceMetadata(): void
    {
        $bindingId = 'binding-123';
        $newMetadata = ['new' => 'data'];
        $normalizedMetadata = ['new' => 'normalized_data'];

        $existingBinding = $this->createMock(BindingInterface::class);
        $updatedBinding = $this->createMock(BindingInterface::class);

        $this->persistenceAdapter->expects($this->exactly(2))
            ->method('find')
            ->with($bindingId)
            ->willReturnOnConsecutiveCalls($existingBinding, $updatedBinding);

        $this->persistenceAdapter->expects($this->once())
            ->method('validateAndNormalizeMetadata')
            ->with($newMetadata)
            ->willReturn($normalizedMetadata);

        $this->persistenceAdapter->expects($this->once())
            ->method('updateMetadata')
            ->with($bindingId, $normalizedMetadata);

        $result = $this->edgeBinder->replaceMetadata($bindingId, $newMetadata);

        $this->assertSame($updatedBinding, $result);
    }

    public function testGetMetadata(): void
    {
        $bindingId = 'binding-123';
        $metadata = ['key' => 'value'];

        $binding = $this->createMock(BindingInterface::class);
        $binding->method('getMetadata')->willReturn($metadata);

        $this->persistenceAdapter->expects($this->once())
            ->method('find')
            ->with($bindingId)
            ->willReturn($binding);

        $result = $this->edgeBinder->getMetadata($bindingId);

        $this->assertEquals($metadata, $result);
    }

    public function testGetMetadataThrowsExceptionWhenBindingNotFound(): void
    {
        $this->persistenceAdapter->expects($this->once())
            ->method('find')
            ->with('nonexistent')
            ->willReturn(null);

        $this->expectException(BindingNotFoundException::class);

        $this->edgeBinder->getMetadata('nonexistent');
    }

    public function testGetStorageAdapter(): void
    {
        $result = $this->edgeBinder->getStorageAdapter();

        $this->assertSame($this->persistenceAdapter, $result);
    }

    public function testBindMany(): void
    {
        $entity1 = new \stdClass();
        $entity2 = new \stdClass();
        $entity3 = new \stdClass();

        $bindingSpecs = [
            [
                'from' => $entity1,
                'to' => $entity2,
                'type' => 'relates_to',
                'metadata' => ['strength' => 'strong'],
            ],
            [
                'from' => $entity2,
                'to' => $entity3,
                'type' => 'connects_to',
            ],
        ];

        // Mock the persistence adapter calls for both bindings
        $this->persistenceAdapter->method('extractEntityType')->willReturn('Entity');
        $this->persistenceAdapter->method('extractEntityId')->willReturnOnConsecutiveCalls(
            'entity-1', 'entity-2', 'entity-2', 'entity-3'
        );
        $this->persistenceAdapter->method('validateAndNormalizeMetadata')->willReturnArgument(0);
        $this->persistenceAdapter->expects($this->exactly(2))->method('store');

        $result = $this->edgeBinder->bindMany($bindingSpecs);

        $this->assertCount(2, $result);
        $this->assertContainsOnlyInstancesOf(BindingInterface::class, $result);
        $this->assertEquals('relates_to', $result[0]->getType());
        $this->assertEquals('connects_to', $result[1]->getType());
        $this->assertEquals(['strength' => 'strong'], $result[0]->getMetadata());
        $this->assertEquals([], $result[1]->getMetadata());
    }

    public function testHasBindingsReturnsTrueWhenBindingsExist(): void
    {
        $bindings = [$this->createMock(BindingInterface::class)];

        $this->persistenceAdapter->expects($this->once())
            ->method('extractEntityType')
            ->with($this->fromEntity)
            ->willReturn('User');

        $this->persistenceAdapter->expects($this->once())
            ->method('extractEntityId')
            ->with($this->fromEntity)
            ->willReturn('user-123');

        $this->persistenceAdapter->expects($this->once())
            ->method('findByEntity')
            ->with('User', 'user-123')
            ->willReturn($bindings);

        $result = $this->edgeBinder->hasBindings($this->fromEntity);

        $this->assertTrue($result);
    }

    public function testHasBindingsReturnsFalseWhenNoBindingsExist(): void
    {
        $this->persistenceAdapter->method('extractEntityType')->willReturn('User');
        $this->persistenceAdapter->method('extractEntityId')->willReturn('user-123');
        $this->persistenceAdapter->method('findByEntity')->willReturn([]);

        $result = $this->edgeBinder->hasBindings($this->fromEntity);

        $this->assertFalse($result);
    }

    public function testCountBindingsFor(): void
    {
        $queryBuilder = $this->createMock(QueryBuilderInterface::class);
        $queryBuilder->expects($this->once())
            ->method('from')
            ->with($this->fromEntity)
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('type')
            ->with('has_access')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('count')
            ->willReturn(42);

        // We need to mock the query builder creation
        $edgeBinder = $this->getMockBuilder(EdgeBinder::class)
            ->setConstructorArgs([$this->persistenceAdapter])
            ->onlyMethods(['query'])
            ->getMock();

        $edgeBinder->expects($this->once())
            ->method('query')
            ->willReturn($queryBuilder);

        $result = $edgeBinder->countBindingsFor($this->fromEntity, 'has_access');

        $this->assertEquals(42, $result);
    }

    public function testCountBindingsForWithoutType(): void
    {
        $queryBuilder = $this->createMock(QueryBuilderInterface::class);
        $queryBuilder->expects($this->once())
            ->method('from')
            ->with($this->fromEntity)
            ->willReturnSelf();
        $queryBuilder->expects($this->never())
            ->method('type');
        $queryBuilder->expects($this->once())
            ->method('count')
            ->willReturn(15);

        $edgeBinder = $this->getMockBuilder(EdgeBinder::class)
            ->setConstructorArgs([$this->persistenceAdapter])
            ->onlyMethods(['query'])
            ->getMock();

        $edgeBinder->expects($this->once())
            ->method('query')
            ->willReturn($queryBuilder);

        $result = $edgeBinder->countBindingsFor($this->fromEntity);

        $this->assertEquals(15, $result);
    }
}

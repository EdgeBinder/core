<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Unit;

use EdgeBinder\Contracts\BindingInterface;
use EdgeBinder\Contracts\EdgeBinderInterface;
use EdgeBinder\Contracts\EntityInterface;
use EdgeBinder\Contracts\QueryBuilderInterface;
use EdgeBinder\EdgeBinder;
use EdgeBinder\Exception\BindingNotFoundException;
use EdgeBinder\Persistence\InMemory\InMemoryAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Test entity that implements EntityInterface for consistent testing.
 */
class TestEntity implements EntityInterface
{
    public function __construct(
        private readonly string $id,
        private readonly string $type
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }
}

/**
 * Simple test entity with just getId method.
 */
class SimpleTestEntity
{
    public function __construct(private readonly string $id)
    {
    }

    public function getId(): string
    {
        return $this->id;
    }
}

class EdgeBinderTest extends TestCase
{
    private InMemoryAdapter $persistenceAdapter;
    private EdgeBinder $edgeBinder;
    private TestEntity $fromEntity;
    private TestEntity $toEntity;

    protected function setUp(): void
    {
        $this->persistenceAdapter = new InMemoryAdapter();
        $this->edgeBinder = new EdgeBinder($this->persistenceAdapter);
        $this->fromEntity = new TestEntity('user-123', 'User');
        $this->toEntity = new TestEntity('project-456', 'Project');
    }

    public function testImplementsEdgeBinderInterface(): void
    {
        $this->assertInstanceOf(EdgeBinderInterface::class, $this->edgeBinder);
    }

    public function testBind(): void
    {
        $metadata = ['access_level' => 'admin'];

        $result = $this->edgeBinder->bind(
            from: $this->fromEntity,
            to: $this->toEntity,
            type: 'has_access',
            metadata: $metadata
        );

        // Verify the binding was created correctly
        $this->assertInstanceOf(BindingInterface::class, $result);
        $this->assertEquals('User', $result->getFromType());
        $this->assertEquals('user-123', $result->getFromId());
        $this->assertEquals('Project', $result->getToType());
        $this->assertEquals('project-456', $result->getToId());
        $this->assertEquals('has_access', $result->getType());
        $this->assertEquals($metadata, $result->getMetadata());

        // Verify the binding was stored in the adapter
        $storedBinding = $this->persistenceAdapter->find($result->getId());
        $this->assertSame($result, $storedBinding);

        // Verify it can be found by entity
        $userBindings = $this->persistenceAdapter->findByEntity('User', 'user-123');
        $this->assertCount(1, $userBindings);
        $this->assertContains($result, $userBindings);

        $projectBindings = $this->persistenceAdapter->findByEntity('Project', 'project-456');
        $this->assertCount(1, $projectBindings);
        $this->assertContains($result, $projectBindings);
    }

    public function testBindWithoutMetadata(): void
    {
        $result = $this->edgeBinder->bind($this->fromEntity, $this->toEntity, 'owns');

        $this->assertEquals([], $result->getMetadata());
        $this->assertEquals('User', $result->getFromType());
        $this->assertEquals('user-123', $result->getFromId());
        $this->assertEquals('Project', $result->getToType());
        $this->assertEquals('project-456', $result->getToId());
        $this->assertEquals('owns', $result->getType());

        // Verify it was stored
        $storedBinding = $this->persistenceAdapter->find($result->getId());
        $this->assertSame($result, $storedBinding);
    }

    public function testUnbind(): void
    {
        // First create a binding to unbind
        $binding = $this->edgeBinder->bind($this->fromEntity, $this->toEntity, 'test_relation');
        $bindingId = $binding->getId();

        // Verify it exists
        $this->assertNotNull($this->persistenceAdapter->find($bindingId));

        // Unbind it
        $this->edgeBinder->unbind($bindingId);

        // Verify it's gone
        $this->assertNull($this->persistenceAdapter->find($bindingId));
    }

    public function testUnbindEntities(): void
    {
        // Create some bindings between the entities
        $binding1 = $this->edgeBinder->bind($this->fromEntity, $this->toEntity, 'has_access');
        $binding2 = $this->edgeBinder->bind($this->fromEntity, $this->toEntity, 'has_access');

        // Verify they exist
        $existingBindings = $this->persistenceAdapter->findBetweenEntities(
            'User',
            'user-123',
            'Project',
            'project-456',
            'has_access'
        );
        $this->assertCount(2, $existingBindings);

        // Unbind them
        $result = $this->edgeBinder->unbindEntities($this->fromEntity, $this->toEntity, 'has_access');

        $this->assertEquals(2, $result);

        // Verify they're gone
        $remainingBindings = $this->persistenceAdapter->findBetweenEntities(
            'User',
            'user-123',
            'Project',
            'project-456',
            'has_access'
        );
        $this->assertCount(0, $remainingBindings);
    }

    public function testUnbindEntitiesWithoutType(): void
    {
        // Create bindings of different types
        $binding1 = $this->edgeBinder->bind($this->fromEntity, $this->toEntity, 'has_access');
        $binding2 = $this->edgeBinder->bind($this->fromEntity, $this->toEntity, 'owns');

        // Unbind all bindings between entities (regardless of type)
        $result = $this->edgeBinder->unbindEntities($this->fromEntity, $this->toEntity);

        $this->assertEquals(2, $result);

        // Verify they're all gone
        $remainingBindings = $this->persistenceAdapter->findBetweenEntities(
            'User',
            'user-123',
            'Project',
            'project-456'
        );
        $this->assertCount(0, $remainingBindings);
    }

    public function testUnbindEntity(): void
    {
        // Create some bindings involving the entity
        $otherEntity1 = new TestEntity('project-1', 'Project');
        $otherEntity2 = new TestEntity('project-2', 'Project');

        $this->edgeBinder->bind($this->fromEntity, $otherEntity1, 'has_access');
        $this->edgeBinder->bind($this->fromEntity, $otherEntity2, 'owns');
        $this->edgeBinder->bind($otherEntity1, $this->fromEntity, 'managed_by');

        // Verify bindings exist
        $userBindings = $this->persistenceAdapter->findByEntity('User', 'user-123');
        $this->assertCount(3, $userBindings);

        // Unbind all bindings for the entity
        $result = $this->edgeBinder->unbindEntity($this->fromEntity);

        $this->assertEquals(3, $result);

        // Verify all bindings involving the entity are gone
        $remainingBindings = $this->persistenceAdapter->findByEntity('User', 'user-123');
        $this->assertCount(0, $remainingBindings);
    }

    public function testQuery(): void
    {
        $result = $this->edgeBinder->query();

        $this->assertInstanceOf(QueryBuilderInterface::class, $result);
    }

    public function testFindBinding(): void
    {
        // Create a binding first
        $binding = $this->edgeBinder->bind($this->fromEntity, $this->toEntity, 'test_relation');
        $bindingId = $binding->getId();

        $result = $this->edgeBinder->findBinding($bindingId);

        $this->assertSame($binding, $result);
    }

    public function testFindBindingReturnsNullWhenNotFound(): void
    {
        $result = $this->edgeBinder->findBinding('nonexistent');

        $this->assertNull($result);
    }

    public function testFindBindingsFor(): void
    {
        // Create some bindings involving the entity
        $otherEntity1 = new TestEntity('project-1', 'Project');
        $otherEntity2 = new TestEntity('project-2', 'Project');

        $binding1 = $this->edgeBinder->bind($this->fromEntity, $otherEntity1, 'has_access');
        $binding2 = $this->edgeBinder->bind($otherEntity2, $this->fromEntity, 'managed_by');

        $result = $this->edgeBinder->findBindingsFor($this->fromEntity);

        $this->assertCount(2, $result);
        $this->assertContains($binding1, $result);
        $this->assertContains($binding2, $result);
    }

    public function testFindBindingsBetween(): void
    {
        // Create some bindings between the entities
        $binding1 = $this->edgeBinder->bind($this->fromEntity, $this->toEntity, 'has_access');
        $binding2 = $this->edgeBinder->bind($this->fromEntity, $this->toEntity, 'owns');

        $result = $this->edgeBinder->findBindingsBetween($this->fromEntity, $this->toEntity, 'has_access');

        $this->assertCount(1, $result);
        $this->assertContains($binding1, $result);
        $this->assertNotContains($binding2, $result);
    }

    public function testAreBoundReturnsTrueWhenBindingsExist(): void
    {
        // Create a binding between the entities
        $this->edgeBinder->bind($this->fromEntity, $this->toEntity, 'has_access');

        $result = $this->edgeBinder->areBound($this->fromEntity, $this->toEntity);

        $this->assertTrue($result);
    }

    public function testAreBoundReturnsFalseWhenNoBindingsExist(): void
    {
        // No bindings created, so they should not be bound
        $result = $this->edgeBinder->areBound($this->fromEntity, $this->toEntity);

        $this->assertFalse($result);
    }

    public function testUpdateMetadata(): void
    {
        // Create a binding with initial metadata
        $existingMetadata = ['existing' => 'value'];
        $binding = $this->edgeBinder->bind($this->fromEntity, $this->toEntity, 'test_relation', $existingMetadata);
        $bindingId = $binding->getId();

        // Update with new metadata
        $newMetadata = ['new' => 'data'];
        $result = $this->edgeBinder->updateMetadata($bindingId, $newMetadata);

        // Verify the metadata was merged
        $expectedMetadata = ['existing' => 'value', 'new' => 'data'];
        $this->assertEquals($expectedMetadata, $result->getMetadata());

        // Verify the binding was updated in storage
        $storedBinding = $this->persistenceAdapter->find($bindingId);
        $this->assertNotNull($storedBinding);
        $this->assertEquals($expectedMetadata, $storedBinding->getMetadata());
    }

    public function testUpdateMetadataThrowsExceptionWhenBindingNotFound(): void
    {
        $this->expectException(BindingNotFoundException::class);

        $this->edgeBinder->updateMetadata('nonexistent', ['new' => 'data']);
    }

    public function testReplaceMetadata(): void
    {
        // Create a binding with initial metadata
        $existingMetadata = ['existing' => 'value', 'other' => 'data'];
        $binding = $this->edgeBinder->bind($this->fromEntity, $this->toEntity, 'test_relation', $existingMetadata);
        $bindingId = $binding->getId();

        // Replace with completely new metadata
        $newMetadata = ['new' => 'data'];
        $result = $this->edgeBinder->replaceMetadata($bindingId, $newMetadata);

        // Verify the metadata was replaced (not merged)
        $this->assertEquals($newMetadata, $result->getMetadata());

        // Verify the binding was updated in storage
        $storedBinding = $this->persistenceAdapter->find($bindingId);
        $this->assertNotNull($storedBinding);
        $this->assertEquals($newMetadata, $storedBinding->getMetadata());
    }

    public function testGetMetadata(): void
    {
        // Create a binding with metadata
        $metadata = ['key' => 'value', 'level' => 'admin'];
        $binding = $this->edgeBinder->bind($this->fromEntity, $this->toEntity, 'test_relation', $metadata);
        $bindingId = $binding->getId();

        $result = $this->edgeBinder->getMetadata($bindingId);

        $this->assertEquals($metadata, $result);
    }

    public function testGetMetadataThrowsExceptionWhenBindingNotFound(): void
    {
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
        $entity1 = new TestEntity('entity-1', 'Entity');
        $entity2 = new TestEntity('entity-2', 'Entity');
        $entity3 = new TestEntity('entity-3', 'Entity');

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

        $result = $this->edgeBinder->bindMany($bindingSpecs);

        $this->assertCount(2, $result);
        $this->assertContainsOnlyInstancesOf(BindingInterface::class, $result);
        $this->assertEquals('relates_to', $result[0]->getType());
        $this->assertEquals('connects_to', $result[1]->getType());
        $this->assertEquals(['strength' => 'strong'], $result[0]->getMetadata());
        $this->assertEquals([], $result[1]->getMetadata());

        // Verify they were stored
        $this->assertNotNull($this->persistenceAdapter->find($result[0]->getId()));
        $this->assertNotNull($this->persistenceAdapter->find($result[1]->getId()));
    }

    public function testHasBindingsReturnsTrueWhenBindingsExist(): void
    {
        // Create a binding for the entity
        $this->edgeBinder->bind($this->fromEntity, $this->toEntity, 'test_relation');

        $result = $this->edgeBinder->hasBindings($this->fromEntity);

        $this->assertTrue($result);
    }

    public function testHasBindingsReturnsFalseWhenNoBindingsExist(): void
    {
        // No bindings created, so should return false
        $result = $this->edgeBinder->hasBindings($this->fromEntity);

        $this->assertFalse($result);
    }

    public function testCountBindingsFor(): void
    {
        // Create some bindings for the entity
        $otherEntity1 = new TestEntity('project-1', 'Project');
        $otherEntity2 = new TestEntity('project-2', 'Project');
        $otherEntity3 = new TestEntity('project-3', 'Project');

        $this->edgeBinder->bind($this->fromEntity, $otherEntity1, 'has_access');
        $this->edgeBinder->bind($this->fromEntity, $otherEntity2, 'has_access');
        $this->edgeBinder->bind($this->fromEntity, $otherEntity3, 'owns'); // Different type

        $result = $this->edgeBinder->countBindingsFor($this->fromEntity, 'has_access');

        $this->assertEquals(2, $result);
    }

    public function testCountBindingsForWithoutType(): void
    {
        // Create some bindings for the entity with different types
        $otherEntity1 = new TestEntity('project-1', 'Project');
        $otherEntity2 = new TestEntity('project-2', 'Project');
        $otherEntity3 = new TestEntity('project-3', 'Project');

        $this->edgeBinder->bind($this->fromEntity, $otherEntity1, 'has_access');
        $this->edgeBinder->bind($this->fromEntity, $otherEntity2, 'has_access');
        $this->edgeBinder->bind($this->fromEntity, $otherEntity3, 'owns');

        $result = $this->edgeBinder->countBindingsFor($this->fromEntity);

        $this->assertEquals(3, $result);
    }
}

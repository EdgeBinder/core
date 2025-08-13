<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Unit;

use EdgeBinder\Contracts\BindingInterface;
use EdgeBinder\Contracts\EdgeBinderInterface;
use EdgeBinder\Contracts\EntityInterface;
use EdgeBinder\Contracts\PersistenceAdapterInterface;
use EdgeBinder\Contracts\QueryBuilderInterface;
use EdgeBinder\EdgeBinder;
use EdgeBinder\Exception\BindingNotFoundException;
use EdgeBinder\Persistence\InMemory\InMemoryAdapter;
use EdgeBinder\Persistence\InMemory\InMemoryAdapterFactory;
use EdgeBinder\Registry\AdapterRegistry;
use EdgeBinder\Session\SessionInterface;
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
        $this->edgeBinder->bind($this->fromEntity, $this->toEntity, 'has_access');
        $this->edgeBinder->bind($this->fromEntity, $this->toEntity, 'has_access');

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
        $this->edgeBinder->bind($this->fromEntity, $this->toEntity, 'has_access');
        $this->edgeBinder->bind($this->fromEntity, $this->toEntity, 'owns');

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

    public function testFromConfiguration(): void
    {
        // Register the InMemory adapter factory (required for fromConfiguration)
        AdapterRegistry::register(new InMemoryAdapterFactory());

        $config = [
            'adapter' => 'inmemory',
            'validateMetadata' => true,
        ];

        $container = $this->createMock(\Psr\Container\ContainerInterface::class);

        $edgeBinder = EdgeBinder::fromConfiguration($config, $container);

        $this->assertInstanceOf(EdgeBinder::class, $edgeBinder);
        $this->assertInstanceOf(PersistenceAdapterInterface::class, $edgeBinder->getStorageAdapter());

        // Clean up registry for other tests
        AdapterRegistry::clear();
    }

    public function testFromAdapter(): void
    {
        $adapter = new InMemoryAdapter();

        $edgeBinder = EdgeBinder::fromAdapter($adapter);

        $this->assertInstanceOf(EdgeBinder::class, $edgeBinder);
        $this->assertSame($adapter, $edgeBinder->getStorageAdapter());
    }

    public function testHasBindings(): void
    {
        $this->assertFalse($this->edgeBinder->hasBindings($this->fromEntity));

        $this->edgeBinder->bind($this->fromEntity, $this->toEntity, 'test_relation');

        $this->assertTrue($this->edgeBinder->hasBindings($this->fromEntity));
    }

    public function testSession(): void
    {
        // First call should create a new session
        $session1 = $this->edgeBinder->session();
        $this->assertInstanceOf(SessionInterface::class, $session1);

        // Second call should return the same session
        $session2 = $this->edgeBinder->session();
        $this->assertSame($session1, $session2);
    }

    public function testWithSession(): void
    {
        $result = $this->edgeBinder->withSession(function ($session) {
            $this->assertInstanceOf(SessionInterface::class, $session);

            $binding = $session->bind($this->fromEntity, $this->toEntity, 'test_relation');

            return $binding->getId();
        });

        $this->assertIsString($result);

        // Verify the binding was created
        $binding = $this->edgeBinder->findBinding($result);
        $this->assertNotNull($binding);
        $this->assertEquals('test_relation', $binding->getType());
    }

    public function testWithSessionAutoFlush(): void
    {
        $this->edgeBinder->withSession(function ($session) {
            $binding = $session->bind($this->fromEntity, $this->toEntity, 'test_relation');

            // With auto-flush, binding should be immediately queryable via direct EdgeBinder
            $found = $this->edgeBinder->findBinding($binding->getId());
            $this->assertNotNull($found);
        }, autoFlush: true);
    }

    public function testCreateSessionWithAutoFlush(): void
    {
        $session = $this->edgeBinder->createSession(autoFlush: true);

        $this->assertInstanceOf(SessionInterface::class, $session);

        // Test that the session was created with auto-flush enabled
        $session->bind($this->fromEntity, $this->toEntity, 'test_relation');

        // With auto-flush, session should not be dirty after operations
        $this->assertFalse($session->isDirty());
    }

    public function testCreateSessionWithoutAutoFlush(): void
    {
        $session = $this->edgeBinder->createSession(autoFlush: false);

        $this->assertInstanceOf(SessionInterface::class, $session);

        // Test that the session was created without auto-flush
        $session->bind($this->fromEntity, $this->toEntity, 'test_relation');

        // Without auto-flush, session should be dirty after operations
        $this->assertTrue($session->isDirty());
    }

    public function testFromConfigurationWithGlobalConfig(): void
    {
        // Register the InMemory adapter factory
        AdapterRegistry::register(new InMemoryAdapterFactory());

        $config = [
            'adapter' => 'inmemory',
            'validateMetadata' => true,
        ];

        $globalConfig = [
            'debug' => true,
            'timeout' => 30,
        ];

        $container = $this->createMock(\Psr\Container\ContainerInterface::class);

        $edgeBinder = EdgeBinder::fromConfiguration($config, $container, $globalConfig);

        $this->assertInstanceOf(EdgeBinder::class, $edgeBinder);
        $this->assertInstanceOf(PersistenceAdapterInterface::class, $edgeBinder->getStorageAdapter());

        // Clean up registry
        AdapterRegistry::clear();
    }

    public function testFromConfigurationMissingAdapterKey(): void
    {
        // Register some adapters to test the error message includes available types
        AdapterRegistry::register(new InMemoryAdapterFactory());

        $config = [
            'validateMetadata' => true,
            // Missing 'adapter' key
        ];

        $container = $this->createMock(\Psr\Container\ContainerInterface::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Configuration must contain 'adapter' key specifying the adapter type. Available types: inmemory");

        try {
            EdgeBinder::fromConfiguration($config, $container);
        } finally {
            AdapterRegistry::clear();
        }
    }

    public function testFromConfigurationInvalidAdapterType(): void
    {
        $config = [
            'adapter' => '', // Empty string
        ];

        $container = $this->createMock(\Psr\Container\ContainerInterface::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Adapter type must be a non-empty string');

        EdgeBinder::fromConfiguration($config, $container);
    }

    public function testFromConfigurationNonStringAdapterType(): void
    {
        $config = [
            'adapter' => 123, // Not a string
        ];

        $container = $this->createMock(\Psr\Container\ContainerInterface::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Adapter type must be a non-empty string, got: integer');

        EdgeBinder::fromConfiguration($config, $container);
    }

    public function testFromConfigurationUnregisteredAdapterType(): void
    {
        // Clear registry to ensure no adapters are registered
        AdapterRegistry::clear();

        $config = [
            'adapter' => 'nonexistent',
        ];

        $container = $this->createMock(\Psr\Container\ContainerInterface::class);

        $this->expectException(\EdgeBinder\Exception\AdapterException::class);
        $this->expectExceptionMessage("Adapter factory for type 'nonexistent' not found");

        EdgeBinder::fromConfiguration($config, $container);
    }

    public function testWithSessionExceptionHandling(): void
    {
        $exceptionThrown = false;

        try {
            $this->edgeBinder->withSession(function ($session) {
                $session->bind($this->fromEntity, $this->toEntity, 'test_relation');

                throw new \RuntimeException('Test exception');
            });
        } catch (\RuntimeException $e) {
            $exceptionThrown = true;
            $this->assertEquals('Test exception', $e->getMessage());
        }

        $this->assertTrue($exceptionThrown, 'Exception should have been thrown and caught');
    }

    public function testSessionPersistence(): void
    {
        // First call creates session
        $session1 = $this->edgeBinder->session();

        // Add some state to the session
        $session1->bind($this->fromEntity, $this->toEntity, 'test_relation');
        $this->assertTrue($session1->isDirty());

        // Second call should return the same session with same state
        $session2 = $this->edgeBinder->session();
        $this->assertSame($session1, $session2);
        $this->assertTrue($session2->isDirty()); // Should still be dirty
        $this->assertCount(1, $session2->getTrackedBindings());
    }

    public function testConstructorWithAdapter(): void
    {
        $adapter = new InMemoryAdapter();
        $edgeBinder = new EdgeBinder($adapter);

        $this->assertInstanceOf(EdgeBinder::class, $edgeBinder);
        $this->assertSame($adapter, $edgeBinder->getStorageAdapter());
    }

    public function testWithSessionClosesSessionOnException(): void
    {
        // Create a mock session to verify close() is called
        $mockSession = $this->createMock(SessionInterface::class);
        $mockSession->expects($this->once())
            ->method('close');

        // Mock the createSession method to return our mock
        $edgeBinderMock = $this->getMockBuilder(EdgeBinder::class)
            ->setConstructorArgs([new InMemoryAdapter()])
            ->onlyMethods(['createSession'])
            ->getMock();

        $edgeBinderMock->expects($this->once())
            ->method('createSession')
            ->willReturn($mockSession);

        try {
            $edgeBinderMock->withSession(function () {
                throw new \RuntimeException('Test exception');
            });
        } catch (\RuntimeException) {
            // Expected exception
        }

        // The mock expectation for close() will verify it was called
    }

    public function testWithSessionReturnsCallbackResult(): void
    {
        $expectedResult = 'test-result';

        $result = $this->edgeBinder->withSession(function () use ($expectedResult) {
            return $expectedResult;
        });

        $this->assertEquals($expectedResult, $result);
    }

    public function testGetStorageAdapterReturnsCorrectAdapter(): void
    {
        $adapter = $this->edgeBinder->getStorageAdapter();

        $this->assertInstanceOf(PersistenceAdapterInterface::class, $adapter);
        $this->assertInstanceOf(InMemoryAdapter::class, $adapter);
    }

    public function testCountBindingsForWithType(): void
    {
        // Create bindings with different types
        $otherEntity1 = new TestEntity('project-1', 'Project');
        $otherEntity2 = new TestEntity('project-2', 'Project');

        $this->edgeBinder->bind($this->fromEntity, $otherEntity1, 'has_access');
        $this->edgeBinder->bind($this->fromEntity, $otherEntity2, 'has_access');
        $this->edgeBinder->bind($this->fromEntity, $otherEntity1, 'owns');

        // Count only 'has_access' bindings
        $result = $this->edgeBinder->countBindingsFor($this->fromEntity, 'has_access');
        $this->assertEquals(2, $result);

        // Count only 'owns' bindings
        $ownsResult = $this->edgeBinder->countBindingsFor($this->fromEntity, 'owns');
        $this->assertEquals(1, $ownsResult);

        // Count non-existent type
        $noneResult = $this->edgeBinder->countBindingsFor($this->fromEntity, 'nonexistent');
        $this->assertEquals(0, $noneResult);
    }

    public function testBindManyWithMissingMetadata(): void
    {
        $otherEntity1 = new TestEntity('project-1', 'Project');
        $otherEntity2 = new TestEntity('project-2', 'Project');

        $bindingSpecs = [
            [
                'from' => $this->fromEntity,
                'to' => $otherEntity1,
                'type' => 'has_access',
                // No metadata key - should default to empty array
            ],
            [
                'from' => $this->fromEntity,
                'to' => $otherEntity2,
                'type' => 'owns',
                'metadata' => ['level' => 'admin'],
            ],
        ];

        $results = $this->edgeBinder->bindMany($bindingSpecs);

        $this->assertCount(2, $results);
        $this->assertEquals([], $results[0]->getMetadata());
        $this->assertEquals(['level' => 'admin'], $results[1]->getMetadata());
    }

    public function testBindManyEmptyArray(): void
    {
        $results = $this->edgeBinder->bindMany([]);

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    public function testUnbindEntityWithNoBindings(): void
    {
        $entity = new TestEntity('empty-entity', 'EmptyType');

        $result = $this->edgeBinder->unbindEntity($entity);

        $this->assertEquals(0, $result);
    }

    public function testFindBindingsBetweenWithNoMatches(): void
    {
        $entity1 = new TestEntity('entity-1', 'Type1');
        $entity2 = new TestEntity('entity-2', 'Type2');

        $results = $this->edgeBinder->findBindingsBetween($entity1, $entity2);

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    public function testAreBoundWithNoBindings(): void
    {
        $entity1 = new TestEntity('entity-1', 'Type1');
        $entity2 = new TestEntity('entity-2', 'Type2');

        $result = $this->edgeBinder->areBound($entity1, $entity2);

        $this->assertFalse($result);
    }

    public function testAreBoundWithSpecificType(): void
    {
        $this->edgeBinder->bind($this->fromEntity, $this->toEntity, 'has_access');
        $this->edgeBinder->bind($this->fromEntity, $this->toEntity, 'owns');

        // Should find 'has_access' binding
        $hasAccess = $this->edgeBinder->areBound($this->fromEntity, $this->toEntity, 'has_access');
        $this->assertTrue($hasAccess);

        // Should find 'owns' binding
        $owns = $this->edgeBinder->areBound($this->fromEntity, $this->toEntity, 'owns');
        $this->assertTrue($owns);

        // Should not find 'admin' binding
        $admin = $this->edgeBinder->areBound($this->fromEntity, $this->toEntity, 'admin');
        $this->assertFalse($admin);
    }

    public function testVersionConstant(): void
    {
        $version = EdgeBinder::VERSION;

        $this->assertIsString($version);
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $version);
        $this->assertEquals('0.7.3', $version);
    }

    public function testUpdateMetadataFailureToRetrieveUpdated(): void
    {
        // Create a binding
        $binding = $this->edgeBinder->bind($this->fromEntity, $this->toEntity, 'test_relation');
        $bindingId = $binding->getId();

        // Create a mock adapter that will fail on the second find call
        $mockAdapter = $this->createMock(PersistenceAdapterInterface::class);
        $mockAdapter->method('extractEntityType')->willReturn('TestType');
        $mockAdapter->method('extractEntityId')->willReturn('test-id');
        $mockAdapter->method('find')
            ->willReturnOnConsecutiveCalls($binding, null); // First call succeeds, second fails
        $mockAdapter->method('validateAndNormalizeMetadata')
            ->willReturn(['new' => 'metadata']);
        $mockAdapter->expects($this->once())
            ->method('updateMetadata');

        $edgeBinderWithMock = new EdgeBinder($mockAdapter);

        $this->expectException(\EdgeBinder\Exception\PersistenceException::class);
        $this->expectExceptionMessage('Failed to retrieve updated binding');

        $edgeBinderWithMock->updateMetadata($bindingId, ['new' => 'metadata']);
    }

    public function testReplaceMetadataFailureToRetrieveUpdated(): void
    {
        // Create a binding
        $binding = $this->edgeBinder->bind($this->fromEntity, $this->toEntity, 'test_relation');
        $bindingId = $binding->getId();

        // Create a mock adapter that will fail on the second find call
        $mockAdapter = $this->createMock(PersistenceAdapterInterface::class);
        $mockAdapter->method('extractEntityType')->willReturn('TestType');
        $mockAdapter->method('extractEntityId')->willReturn('test-id');
        $mockAdapter->method('find')
            ->willReturnOnConsecutiveCalls($binding, null); // First call succeeds, second fails
        $mockAdapter->method('validateAndNormalizeMetadata')
            ->willReturn(['new' => 'metadata']);
        $mockAdapter->expects($this->once())
            ->method('updateMetadata');

        $edgeBinderWithMock = new EdgeBinder($mockAdapter);

        $this->expectException(\EdgeBinder\Exception\PersistenceException::class);
        $this->expectExceptionMessage('Failed to retrieve updated binding');

        $edgeBinderWithMock->replaceMetadata($bindingId, ['new' => 'metadata']);
    }

    public function testBindWithComplexMetadata(): void
    {
        $complexMetadata = [
            'string' => 'value',
            'int' => 42,
            'float' => 3.14,
            'bool' => true,
            'null' => null,
            'array' => ['nested' => 'value'],
            'datetime' => new \DateTimeImmutable(),
        ];

        $binding = $this->edgeBinder->bind($this->fromEntity, $this->toEntity, 'test_relation', $complexMetadata);

        $this->assertInstanceOf(BindingInterface::class, $binding);
        $this->assertEquals('test_relation', $binding->getType());

        // Verify metadata was normalized and stored correctly
        $storedMetadata = $binding->getMetadata();
        $this->assertArrayHasKey('string', $storedMetadata);
        $this->assertArrayHasKey('datetime', $storedMetadata);
    }

    public function testUnbindEntitiesWithExceptionDuringDelete(): void
    {
        // Create bindings
        $binding1 = $this->edgeBinder->bind($this->fromEntity, $this->toEntity, 'has_access');
        $binding2 = $this->edgeBinder->bind($this->fromEntity, $this->toEntity, 'owns');

        // Create a mock adapter that will throw exception on second delete
        $mockAdapter = $this->createMock(PersistenceAdapterInterface::class);
        $mockAdapter->method('extractEntityType')->willReturn('TestType');
        $mockAdapter->method('extractEntityId')->willReturn('test-id');
        $mockAdapter->method('findBetweenEntities')
            ->willReturn([$binding1, $binding2]);
        $mockAdapter->method('delete')
            ->willReturnCallback(function ($id) use ($binding1) {
                if ($id === $binding1->getId()) {
                    return; // First delete succeeds
                }

                throw new \EdgeBinder\Exception\PersistenceException('delete', 'Simulated failure');
            });

        $edgeBinderWithMock = new EdgeBinder($mockAdapter);

        // Should throw exception on second delete
        $this->expectException(\EdgeBinder\Exception\PersistenceException::class);
        $this->expectExceptionMessage('Simulated failure');

        $edgeBinderWithMock->unbindEntities($this->fromEntity, $this->toEntity);
    }

    public function testSessionCreationAndReuse(): void
    {
        // Test that session() creates a new session on first call
        $session1 = $this->edgeBinder->session();
        $this->assertInstanceOf(SessionInterface::class, $session1);

        // Test that session() reuses the same session on subsequent calls
        $session2 = $this->edgeBinder->session();
        $this->assertSame($session1, $session2);

        // Test that createSession() always creates a new session
        $session3 = $this->edgeBinder->createSession();
        $this->assertInstanceOf(SessionInterface::class, $session3);
        $this->assertNotSame($session1, $session3);

        // Test createSession with different autoFlush values
        $autoFlushSession = $this->edgeBinder->createSession(autoFlush: true);
        $this->assertInstanceOf(SessionInterface::class, $autoFlushSession);
        $this->assertNotSame($session1, $autoFlushSession);
    }
}

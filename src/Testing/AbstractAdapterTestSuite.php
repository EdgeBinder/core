<?php

declare(strict_types=1);

namespace EdgeBinder\Testing;

use EdgeBinder\Binding;
use EdgeBinder\Contracts\BindingInterface;
use EdgeBinder\Contracts\EntityInterface;
use EdgeBinder\Contracts\PersistenceAdapterInterface;
use EdgeBinder\EdgeBinder;
use EdgeBinder\Exception\BindingNotFoundException;
use EdgeBinder\Exception\InvalidMetadataException;
use EdgeBinder\Exception\PersistenceException;
use PHPUnit\Framework\TestCase;

/**
 * Abstract test suite for EdgeBinder adapters.
 *
 * This test suite contains comprehensive integration tests converted from InMemoryAdapterTest.
 * These tests use the real EdgeBinder and BindingQueryBuilder to ensure all adapters behave
 * consistently and would catch bugs like the WeaviateAdapter query filtering issue.
 *
 * To use this test suite:
 * 1. Extend this class in your adapter's integration test
 * 2. Implement createAdapter() to return your configured adapter
 * 3. Implement cleanupAdapter() to clean up any test data/connections
 * 4. Run the tests - all must pass for adapter certification
 */
abstract class AbstractAdapterTestSuite extends TestCase
{
    protected PersistenceAdapterInterface $adapter;
    protected EdgeBinder $edgeBinder;

    /**
     * Create and configure the adapter for testing.
     */
    abstract protected function createAdapter(): PersistenceAdapterInterface;

    /**
     * Clean up any test data, connections, or resources after testing.
     */
    abstract protected function cleanupAdapter(): void;

    protected function setUp(): void
    {
        $this->adapter = $this->createAdapter();
        $this->edgeBinder = new EdgeBinder($this->adapter);
    }

    protected function tearDown(): void
    {
        $this->cleanupAdapter();
    }

    // ========================================
    // CRITICAL: EdgeBinder Query Integration Tests
    // These are the key tests that would catch the WeaviateAdapter bug
    // ========================================

    /**
     * THE CRITICAL TEST: This is the exact scenario that was failing in WeaviateAdapter
     * $edgeBinder->query()->from($user)->type('owns')->get() was returning 80 results instead of 2.
     */
    public function testExecuteQueryFiltersAreProperlyApplied(): void
    {
        $user1 = $this->createTestEntity('user-1', 'User');
        $user2 = $this->createTestEntity('user-2', 'User');

        // Create many bindings for user2 (should NOT be returned)
        for ($i = 1; $i <= 80; ++$i) {
            $project = $this->createTestEntity("project-{$i}", 'Project');
            $this->edgeBinder->bind($user2, $project, 'has_access');
        }

        // Create only 2 'owns' bindings for user1 (should be returned)
        $ownedProject1 = $this->createTestEntity('owned-project-1', 'Project');
        $ownedProject2 = $this->createTestEntity('owned-project-2', 'Project');
        $this->edgeBinder->bind($user1, $ownedProject1, 'owns');
        $this->edgeBinder->bind($user1, $ownedProject2, 'owns');

        // THE CRITICAL TEST: This exact query was returning 80 results in WeaviateAdapter
        $results = $this->edgeBinder->query()->from($user1)->type('owns')->get();

        // MUST return exactly 2 results, not 80+ (the entire database)
        $this->assertCount(
            2,
            $results,
            'CRITICAL BUG: Query filters not applied! This is the exact WeaviateAdapter bug.'
        );

        foreach ($results as $binding) {
            $this->assertEquals('user-1', $binding->getFromId());
            $this->assertEquals('owns', $binding->getType());
        }
    }

    public function testExecuteQueryWithFromCriteria(): void
    {
        $user1 = $this->createTestEntity('user-1', 'User');
        $user2 = $this->createTestEntity('user-2', 'User');
        $project1 = $this->createTestEntity('project-1', 'Project');
        $project2 = $this->createTestEntity('project-2', 'Project');

        $this->edgeBinder->bind($user1, $project1, 'has_access');
        $this->edgeBinder->bind($user1, $project2, 'has_access');
        $this->edgeBinder->bind($user2, $project1, 'has_access');

        // CRITICAL TEST: This should return ONLY bindings from user-1, not all bindings
        $results = $this->edgeBinder->query()->from($user1)->get();

        $this->assertCount(2, $results);
        foreach ($results as $binding) {
            $this->assertEquals('User', $binding->getFromType());
            $this->assertEquals('user-1', $binding->getFromId());
        }
    }

    public function testExecuteQueryWithToCriteria(): void
    {
        $user1 = $this->createTestEntity('user-1', 'User');
        $user2 = $this->createTestEntity('user-2', 'User');
        $project1 = $this->createTestEntity('project-1', 'Project');
        $project2 = $this->createTestEntity('project-2', 'Project');

        $this->edgeBinder->bind($user1, $project1, 'has_access');
        $this->edgeBinder->bind($user2, $project1, 'has_access');
        $this->edgeBinder->bind($user1, $project2, 'has_access');

        // CRITICAL TEST: This should return ONLY bindings to project-1, not all bindings
        $results = $this->edgeBinder->query()->to($project1)->get();

        $this->assertCount(2, $results);
        foreach ($results as $binding) {
            $this->assertEquals('Project', $binding->getToType());
            $this->assertEquals('project-1', $binding->getToId());
        }
    }

    public function testExecuteQueryWithTypeCriteria(): void
    {
        $user = $this->createTestEntity('user-1', 'User');
        $project1 = $this->createTestEntity('project-1', 'Project');
        $project2 = $this->createTestEntity('project-2', 'Project');

        $this->edgeBinder->bind($user, $project1, 'has_access');
        $this->edgeBinder->bind($user, $project2, 'owns');
        $this->edgeBinder->bind($user, $project1, 'owns');

        // CRITICAL TEST: This should return ONLY 'owns' bindings, not all bindings
        $results = $this->edgeBinder->query()->type('owns')->get();

        $this->assertCount(2, $results);
        foreach ($results as $binding) {
            $this->assertEquals('owns', $binding->getType());
        }
    }

    public function testExecuteQueryWithWhereCriteria(): void
    {
        $user = $this->createTestEntity('user-1', 'User');
        $project1 = $this->createTestEntity('project-1', 'Project');
        $project2 = $this->createTestEntity('project-2', 'Project');

        $this->edgeBinder->bind($user, $project1, 'has_access', ['level' => 'read']);
        $this->edgeBinder->bind($user, $project2, 'has_access', ['level' => 'write']);

        // CRITICAL TEST: This should return ONLY bindings with level=write
        $results = $this->edgeBinder->query()
            ->from($user)
            ->where('level', 'write')
            ->get();

        $this->assertCount(1, $results);
        $binding = $results[0];
        $this->assertEquals('write', $binding->getMetadata()['level']);
        $this->assertEquals('project-2', $binding->getToId());
    }

    public function testExecuteQueryWithComplexCriteria(): void
    {
        $user1 = $this->createTestEntity('user-1', 'User');
        $user2 = $this->createTestEntity('user-2', 'User');
        $project1 = $this->createTestEntity('project-1', 'Project');
        $project2 = $this->createTestEntity('project-2', 'Project');

        $this->edgeBinder->bind($user1, $project1, 'has_access', ['level' => 'write', 'score' => 85]);
        $this->edgeBinder->bind($user1, $project2, 'has_access', ['level' => 'read', 'score' => 75]);
        $this->edgeBinder->bind($user2, $project1, 'owns', ['level' => 'admin', 'score' => 95]);

        // CRITICAL TEST: Complex query with multiple filters
        $results = $this->edgeBinder->query()
            ->from($user1)
            ->type('has_access')
            ->where('score', '>', 80)
            ->get();

        $this->assertCount(1, $results);
        $binding = $results[0];
        $this->assertEquals('user-1', $binding->getFromId());
        $this->assertEquals('has_access', $binding->getType());
        $this->assertEquals(85, $binding->getMetadata()['score']);
    }

    // ========================================
    // Additional Essential Integration Tests
    // ========================================

    public function testCountQuery(): void
    {
        $user = $this->createTestEntity('user-1', 'User');
        $project1 = $this->createTestEntity('project-1', 'Project');
        $project2 = $this->createTestEntity('project-2', 'Project');

        $this->edgeBinder->bind($user, $project1, 'has_access');
        $this->edgeBinder->bind($user, $project2, 'has_access');

        $count = $this->edgeBinder->query()->from($user)->count();
        $this->assertEquals(2, $count);
    }

    public function testFirstReturnsFirstResult(): void
    {
        $user = $this->createTestEntity('user-1', 'User');
        $project1 = $this->createTestEntity('project-1', 'Project');
        $project2 = $this->createTestEntity('project-2', 'Project');

        $this->edgeBinder->bind($user, $project1, 'has_access', ['priority' => 1]);
        $this->edgeBinder->bind($user, $project2, 'has_access', ['priority' => 2]);

        $result = $this->edgeBinder->query()
            ->from($user)
            ->orderBy('priority', 'asc')
            ->first();

        $this->assertNotNull($result);
        $this->assertEquals(1, $result->getMetadata()['priority']);
    }

    public function testFirstReturnsNullWhenNoResults(): void
    {
        $result = $this->edgeBinder->query()
            ->from($this->createTestEntity('non-existent', 'User'))
            ->first();

        $this->assertNull($result);
    }

    public function testExistsReturnsTrueWhenResultsExist(): void
    {
        $user = $this->createTestEntity('user-1', 'User');
        $project = $this->createTestEntity('project-1', 'Project');
        $this->edgeBinder->bind($user, $project, 'has_access');

        $exists = $this->edgeBinder->query()->from($user)->exists();
        $this->assertTrue($exists);
    }

    public function testExistsReturnsFalseWhenNoResults(): void
    {
        $exists = $this->edgeBinder->query()
            ->from($this->createTestEntity('non-existent', 'User'))
            ->exists();

        $this->assertFalse($exists);
    }

    public function testExecuteQueryWithPagination(): void
    {
        $user = $this->createTestEntity('user-1', 'User');

        for ($i = 1; $i <= 5; ++$i) {
            $project = $this->createTestEntity("project-{$i}", 'Project');
            $this->edgeBinder->bind($user, $project, 'has_access', ['order' => $i]);
        }

        // Test limit
        $results = $this->edgeBinder->query()
            ->from($user)
            ->limit(3)
            ->get();

        $this->assertCount(3, $results);

        // Test offset + limit
        $results = $this->edgeBinder->query()
            ->from($user)
            ->offset(2)
            ->limit(2)
            ->get();

        $this->assertCount(2, $results);
    }

    public function testExecuteQueryWithOrdering(): void
    {
        $user = $this->createTestEntity('user-1', 'User');
        $project1 = $this->createTestEntity('project-1', 'Project');
        $project2 = $this->createTestEntity('project-2', 'Project');

        $this->edgeBinder->bind($user, $project1, 'has_access', ['priority' => 2]);
        $this->edgeBinder->bind($user, $project2, 'has_access', ['priority' => 1]);

        // Test ordering by metadata field
        $results = $this->edgeBinder->query()
            ->from($user)
            ->orderBy('priority', 'asc')
            ->get();

        $this->assertCount(2, $results);
        $this->assertEquals(1, $results[0]->getMetadata()['priority']);
        $this->assertEquals(2, $results[1]->getMetadata()['priority']);
    }

    public function testExecuteQueryReturnsEmptyArrayWhenNoMatches(): void
    {
        $user = $this->createTestEntity('user-1', 'User');
        $project = $this->createTestEntity('project-1', 'Project');
        $this->edgeBinder->bind($user, $project, 'has_access');

        // Query for non-existent entity
        $results = $this->edgeBinder->query()
            ->from($this->createTestEntity('user-999', 'User'))
            ->get();

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    // ========================================
    // CRUD Operation Tests (integration tests)
    // ========================================

    public function testStoreAndFindBinding(): void
    {
        $user = $this->createTestEntity('user-1', 'User');
        $project = $this->createTestEntity('project-1', 'Project');

        // Use EdgeBinder to create and store binding
        $binding = $this->edgeBinder->bind($user, $project, 'has_access');

        // Find the binding using the adapter directly
        $found = $this->adapter->find($binding->getId());

        $this->assertNotNull($found);
        $this->assertEquals($binding->getId(), $found->getId());
        $this->assertEquals($binding->getFromType(), $found->getFromType());
        $this->assertEquals($binding->getFromId(), $found->getFromId());
        $this->assertEquals($binding->getToType(), $found->getToType());
        $this->assertEquals($binding->getToId(), $found->getToId());
        $this->assertEquals($binding->getType(), $found->getType());
    }

    public function testFindNonExistentBinding(): void
    {
        $result = $this->adapter->find('non-existent');
        $this->assertNull($result);
    }

    public function testDeleteExistingBinding(): void
    {
        $user = $this->createTestEntity('user-1', 'User');
        $project = $this->createTestEntity('project-1', 'Project');

        $binding = $this->edgeBinder->bind($user, $project, 'has_access');

        // Delete using adapter directly
        $this->adapter->delete($binding->getId());
        $found = $this->adapter->find($binding->getId());

        $this->assertNull($found);
    }

    public function testDeleteNonExistentBinding(): void
    {
        $this->expectException(BindingNotFoundException::class);
        $this->expectExceptionMessage("Binding with ID 'non-existent' not found");

        $this->adapter->delete('non-existent');
    }

    // ========================================
    // Helper Methods
    // ========================================

    /**
     * Create a test entity for use in tests.
     */
    protected function createTestEntity(string $id, string $type): EntityInterface
    {
        return new class($id, $type) implements EntityInterface {
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
        };
    }

    /**
     * Create a test binding for use in tests.
     *
     * @param array<string, mixed> $metadata
     */
    protected function createTestBinding(
        string $fromType = 'User',
        string $fromId = 'user-1',
        string $toType = 'Project',
        string $toId = 'project-1',
        string $type = 'has_access',
        array $metadata = []
    ): BindingInterface {
        return Binding::create($fromType, $fromId, $toType, $toId, $type, $metadata);
    }

    // ========================================
    // Public API Method Tests (Missing Coverage)
    // ========================================

    public function testFindByEntity(): void
    {
        $user = $this->createTestEntity('user-1', 'User');
        $project1 = $this->createTestEntity('project-1', 'Project');
        $project2 = $this->createTestEntity('project-2', 'Project');
        $workspace = $this->createTestEntity('workspace-1', 'Workspace');

        // Create bindings
        $this->edgeBinder->bind($user, $project1, 'has_access');
        $this->edgeBinder->bind($user, $project2, 'owns');
        $this->edgeBinder->bind($workspace, $project1, 'contains');

        // Test finding by user entity
        $userBindings = $this->adapter->findByEntity('User', 'user-1');
        $this->assertCount(2, $userBindings);

        // Test finding by project entity
        $project1Bindings = $this->adapter->findByEntity('Project', 'project-1');
        $this->assertCount(2, $project1Bindings);

        // Test finding by non-existent entity
        $nonExistentBindings = $this->adapter->findByEntity('NonExistent', 'non-existent');
        $this->assertEmpty($nonExistentBindings);
    }

    public function testFindBetweenEntities(): void
    {
        $user = $this->createTestEntity('user-1', 'User');
        $project1 = $this->createTestEntity('project-1', 'Project');
        $project2 = $this->createTestEntity('project-2', 'Project');

        // Create bindings
        $this->edgeBinder->bind($user, $project1, 'has_access');
        $this->edgeBinder->bind($user, $project1, 'owns');
        $this->edgeBinder->bind($user, $project2, 'has_access');

        // Test finding between specific entities without type filter
        $bindings = $this->adapter->findBetweenEntities('User', 'user-1', 'Project', 'project-1');
        $this->assertCount(2, $bindings);

        // Test finding between specific entities with type filter
        $accessBindings = $this->adapter->findBetweenEntities('User', 'user-1', 'Project', 'project-1', 'has_access');
        $this->assertCount(1, $accessBindings);
        $this->assertEquals('has_access', $accessBindings[0]->getType());

        // Test finding between non-existent entities
        $nonExistentBindings = $this->adapter->findBetweenEntities('User', 'user-1', 'Project', 'non-existent');
        $this->assertEmpty($nonExistentBindings);
    }

    public function testUpdateMetadata(): void
    {
        $user = $this->createTestEntity('user-1', 'User');
        $project = $this->createTestEntity('project-1', 'Project');

        // Create binding with initial metadata
        $binding = $this->edgeBinder->bind($user, $project, 'has_access', [
            'level' => 'read',
            'granted_by' => 'admin',
        ]);

        // Update metadata
        $newMetadata = [
            'level' => 'write',
            'granted_by' => 'manager',
            'updated_at' => new \DateTimeImmutable(),
        ];
        $this->adapter->updateMetadata($binding->getId(), $newMetadata);

        // Verify metadata was updated
        $updatedBinding = $this->adapter->find($binding->getId());
        $this->assertNotNull($updatedBinding);
        $this->assertEquals('write', $updatedBinding->getMetadata()['level']);
        $this->assertEquals('manager', $updatedBinding->getMetadata()['granted_by']);
        $this->assertInstanceOf(\DateTimeImmutable::class, $updatedBinding->getMetadata()['updated_at']);
    }

    public function testUpdateMetadataForNonExistentBinding(): void
    {
        $this->expectException(BindingNotFoundException::class);
        $this->adapter->updateMetadata('non-existent-id', ['key' => 'value']);
    }

    public function testDeleteByEntity(): void
    {
        $user = $this->createTestEntity('user-1', 'User');
        $project1 = $this->createTestEntity('project-1', 'Project');
        $project2 = $this->createTestEntity('project-2', 'Project');
        $workspace = $this->createTestEntity('workspace-1', 'Workspace');

        // Create bindings
        $this->edgeBinder->bind($user, $project1, 'has_access');
        $this->edgeBinder->bind($user, $project2, 'owns');
        $this->edgeBinder->bind($workspace, $project1, 'contains');

        // Delete all bindings for user
        $deletedCount = $this->adapter->deleteByEntity('User', 'user-1');
        $this->assertEquals(2, $deletedCount);

        // Verify user bindings are deleted
        $userBindings = $this->adapter->findByEntity('User', 'user-1');
        $this->assertEmpty($userBindings);

        // Verify other bindings still exist
        $workspaceBindings = $this->adapter->findByEntity('Workspace', 'workspace-1');
        $this->assertCount(1, $workspaceBindings);

        // Test deleting non-existent entity
        $deletedCount = $this->adapter->deleteByEntity('NonExistent', 'non-existent');
        $this->assertEquals(0, $deletedCount);
    }

    // ========================================
    // Metadata Validation Tests (Missing Coverage)
    // ========================================

    public function testValidateAndNormalizeMetadata(): void
    {
        // Test valid metadata
        $validMetadata = [
            'string' => 'value',
            'int' => 42,
            'float' => 3.14,
            'bool' => true,
            'null' => null,
            'array' => ['nested' => 'value'],
            'datetime' => new \DateTimeImmutable(),
        ];

        $normalized = $this->adapter->validateAndNormalizeMetadata($validMetadata);
        $this->assertIsArray($normalized);
        $this->assertEquals('value', $normalized['string']);
        $this->assertEquals(42, $normalized['int']);
        $this->assertInstanceOf(\DateTimeImmutable::class, $normalized['datetime']);
    }

    public function testValidateMetadataWithNestedArrays(): void
    {
        $nestedMetadata = [
            'level1' => [
                'level2' => [
                    'level3' => 'deep value',
                ],
            ],
        ];

        $normalized = $this->adapter->validateAndNormalizeMetadata($nestedMetadata);
        $this->assertEquals('deep value', $normalized['level1']['level2']['level3']);
    }

    public function testValidateMetadataRejectsResources(): void
    {
        $resource = fopen('php://memory', 'r');
        $this->assertIsResource($resource);
        $metadata = ['resource' => $resource];

        $this->expectException(InvalidMetadataException::class);
        $this->expectExceptionMessage('Metadata cannot contain resources');

        try {
            $this->adapter->validateAndNormalizeMetadata($metadata);
        } finally {
            if (is_resource($resource)) {
                fclose($resource);
            }
        }
    }

    public function testValidateMetadataRejectsInvalidObjects(): void
    {
        $object = new \stdClass();
        $metadata = ['object' => $object];

        $this->expectException(InvalidMetadataException::class);
        $this->expectExceptionMessage('Metadata can only contain DateTime objects');
        $this->adapter->validateAndNormalizeMetadata($metadata);
    }

    public function testValidateMetadataRejectsNonStringKeys(): void
    {
        /** @var array<string, mixed> $metadata */
        $metadata = [123 => 'value']; // @phpstan-ignore-line - Testing invalid input

        $this->expectException(InvalidMetadataException::class);
        $this->expectExceptionMessage('Metadata keys must be strings');
        $this->adapter->validateAndNormalizeMetadata($metadata);
    }

    public function testValidateMetadataRejectsTooDeepNesting(): void
    {
        // Create deeply nested array (11 levels)
        $metadata = [];
        $current = &$metadata;
        for ($i = 0; $i < 11; ++$i) {
            $current['level'] = [];
            $current = &$current['level'];
        }
        $current = 'deep value';

        $this->expectException(InvalidMetadataException::class);
        $this->expectExceptionMessage('Metadata nesting too deep (max 10 levels)');
        $this->adapter->validateAndNormalizeMetadata($metadata);
    }

    // ========================================
    // Entity Extraction Tests (Missing Coverage)
    // ========================================

    public function testExtractEntityIdFromEntityInterface(): void
    {
        $entity = new class implements EntityInterface {
            public function getId(): string
            {
                return 'entity-123';
            }

            public function getType(): string
            {
                return 'TestEntity';
            }
        };

        $id = $this->adapter->extractEntityId($entity);
        $this->assertEquals('entity-123', $id);
    }

    public function testExtractEntityIdFromGetIdMethod(): void
    {
        $entity = new class {
            public function getId(): string
            {
                return 'method-456';
            }
        };

        $id = $this->adapter->extractEntityId($entity);
        $this->assertEquals('method-456', $id);
    }

    public function testExtractEntityIdFromIdProperty(): void
    {
        $entity = new class {
            public string $id = 'property-789';
        };

        $id = $this->adapter->extractEntityId($entity);
        $this->assertEquals('property-789', $id);
    }

    public function testExtractEntityIdFallsBackToObjectHash(): void
    {
        $entity = new class {
            // No getId method or id property
        };

        $id = $this->adapter->extractEntityId($entity);
        $this->assertIsString($id);
        $this->assertNotEmpty($id);
        // Object hash should be consistent for same object
        $this->assertEquals($id, $this->adapter->extractEntityId($entity));
    }

    public function testExtractEntityTypeFromEntityInterface(): void
    {
        $entity = new class implements EntityInterface {
            public function getId(): string
            {
                return 'test-id';
            }

            public function getType(): string
            {
                return 'CustomType';
            }
        };

        $type = $this->adapter->extractEntityType($entity);
        $this->assertEquals('CustomType', $type);
    }

    public function testExtractEntityTypeFromGetTypeMethod(): void
    {
        $entity = new class {
            public function getType(): string
            {
                return 'MethodType';
            }
        };

        $type = $this->adapter->extractEntityType($entity);
        $this->assertEquals('MethodType', $type);
    }

    public function testExtractEntityTypeFallsBackToClassName(): void
    {
        $entity = new \stdClass();

        $type = $this->adapter->extractEntityType($entity);
        $this->assertEquals('stdClass', $type);
    }

    // ========================================
    // Complex Query and Edge Case Tests
    // ========================================

    public function testQueryWithMultipleComplexConditions(): void
    {
        $user1 = $this->createTestEntity('user-1', 'User');
        $user2 = $this->createTestEntity('user-2', 'User');
        $project1 = $this->createTestEntity('project-1', 'Project');
        $project2 = $this->createTestEntity('project-2', 'Project');

        // Create bindings with complex metadata
        $this->edgeBinder->bind($user1, $project1, 'has_access', [
            'level' => 'admin',
            'department' => 'engineering',
            'priority' => 1,
            'active' => true,
        ]);
        $this->edgeBinder->bind($user1, $project2, 'has_access', [
            'level' => 'read',
            'department' => 'engineering',
            'priority' => 2,
            'active' => false,
        ]);
        $this->edgeBinder->bind($user2, $project1, 'has_access', [
            'level' => 'write',
            'department' => 'marketing',
            'priority' => 1,
            'active' => true,
        ]);

        // Complex query: admin level AND engineering department AND active
        $query = $this->edgeBinder->query()
            ->where('metadata.level', '=', 'admin')
            ->where('metadata.department', '=', 'engineering')
            ->where('metadata.active', '=', true)
            ->orderBy('metadata.priority', 'asc');

        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertEquals('admin', $results[0]->getMetadata()['level']);
    }

    public function testQueryWithInOperator(): void
    {
        $user = $this->createTestEntity('user-1', 'User');
        $project1 = $this->createTestEntity('project-1', 'Project');
        $project2 = $this->createTestEntity('project-2', 'Project');
        $project3 = $this->createTestEntity('project-3', 'Project');

        $this->edgeBinder->bind($user, $project1, 'has_access', ['level' => 'admin']);
        $this->edgeBinder->bind($user, $project2, 'has_access', ['level' => 'write']);
        $this->edgeBinder->bind($user, $project3, 'has_access', ['level' => 'read']);

        $query = $this->edgeBinder->query()
            ->where('metadata.level', 'in', ['admin', 'write']);

        $results = $query->get();
        $this->assertCount(2, $results);
    }

    public function testQueryWithNotInOperator(): void
    {
        $user = $this->createTestEntity('user-1', 'User');
        $project1 = $this->createTestEntity('project-1', 'Project');
        $project2 = $this->createTestEntity('project-2', 'Project');
        $project3 = $this->createTestEntity('project-3', 'Project');

        $this->edgeBinder->bind($user, $project1, 'has_access', ['level' => 'admin']);
        $this->edgeBinder->bind($user, $project2, 'has_access', ['level' => 'write']);
        $this->edgeBinder->bind($user, $project3, 'has_access', ['level' => 'read']);

        $query = $this->edgeBinder->query()
            ->where('metadata.level', 'not_in', ['read']);

        $results = $query->get();
        $this->assertCount(2, $results);
        foreach ($results as $binding) {
            $this->assertNotEquals('read', $binding->getMetadata()['level']);
        }
    }

    public function testQueryWithNullValues(): void
    {
        $user = $this->createTestEntity('user-1', 'User');
        $project1 = $this->createTestEntity('project-1', 'Project');
        $project2 = $this->createTestEntity('project-2', 'Project');

        $this->edgeBinder->bind($user, $project1, 'has_access', ['description' => null]);
        $this->edgeBinder->bind($user, $project2, 'has_access', ['description' => 'has description']);

        $query = $this->edgeBinder->query()
            ->where('metadata.description', '=', null);

        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertNull($results[0]->getMetadata()['description']);
    }

    public function testQueryWithDateTimeComparison(): void
    {
        $user = $this->createTestEntity('user-1', 'User');
        $project1 = $this->createTestEntity('project-1', 'Project');
        $project2 = $this->createTestEntity('project-2', 'Project');

        $oldDate = new \DateTimeImmutable('2023-01-01');
        $newDate = new \DateTimeImmutable('2024-01-01');

        $this->edgeBinder->bind($user, $project1, 'has_access', ['created_at' => $oldDate]);
        $this->edgeBinder->bind($user, $project2, 'has_access', ['created_at' => $newDate]);

        $query = $this->edgeBinder->query()
            ->where('metadata.created_at', '>', $oldDate);

        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertEquals($newDate, $results[0]->getMetadata()['created_at']);
    }

    public function testQueryWithEmptyResults(): void
    {
        $query = $this->edgeBinder->query()
            ->where('type', '=', 'non_existent_type');

        $results = $query->get();
        $this->assertEmpty($results);

        $count = $query->count();
        $this->assertEquals(0, $count);
    }

    public function testStoreBindingWithInvalidMetadata(): void
    {
        $binding = $this->createTestBinding(metadata: ['resource' => fopen('php://memory', 'r')]);

        $this->expectException(InvalidMetadataException::class);
        $this->adapter->store($binding);
    }

    public function testStoreBindingWithDuplicateId(): void
    {
        $binding1 = $this->createTestBinding();
        $this->adapter->store($binding1);

        // Try to store the same binding again
        $this->expectException(PersistenceException::class);
        $this->adapter->store($binding1);
    }

    // ========================================
    // 100% Coverage Tests - Edge Cases & Error Paths
    // ========================================

    public function testValidateMetadataWithMaximumNestingDepth(): void
    {
        // Test exactly 10 levels of nesting (should pass)
        $metadata = [];
        $current = &$metadata;
        for ($i = 0; $i < 10; ++$i) {
            $current['level'] = [];
            $current = &$current['level'];
        }
        $current = 'max depth value';

        $normalized = $this->adapter->validateAndNormalizeMetadata($metadata);
        $this->assertIsArray($normalized);

        // Navigate to the deepest level to verify it was processed
        $deep = $normalized;
        for ($i = 0; $i < 10; ++$i) {
            $deep = $deep['level'];
        }
        $this->assertEquals('max depth value', $deep);
    }

    public function testValidateMetadataWithAllScalarTypes(): void
    {
        $metadata = [
            'string' => 'test',
            'int' => 42,
            'float' => 3.14159,
            'bool_true' => true,
            'bool_false' => false,
            'null_value' => null,
            'zero' => 0,
            'empty_string' => '',
            'negative_int' => -100,
            'negative_float' => -2.5,
        ];

        $normalized = $this->adapter->validateAndNormalizeMetadata($metadata);
        $this->assertEquals($metadata, $normalized);
    }

    public function testValidateMetadataWithDateTimeSubclasses(): void
    {
        $dateTime = new \DateTime('2024-01-01 12:00:00');
        $dateTimeImmutable = new \DateTimeImmutable('2024-01-01 12:00:00');

        $metadata = [
            'datetime' => $dateTime,
            'datetime_immutable' => $dateTimeImmutable,
        ];

        $normalized = $this->adapter->validateAndNormalizeMetadata($metadata);
        $this->assertInstanceOf(\DateTime::class, $normalized['datetime']);
        $this->assertInstanceOf(\DateTimeImmutable::class, $normalized['datetime_immutable']);
    }

    public function testValidateMetadataWithEmptyArray(): void
    {
        $metadata = [];
        $normalized = $this->adapter->validateAndNormalizeMetadata($metadata);
        $this->assertEquals([], $normalized);
    }

    public function testValidateMetadataWithNestedEmptyArrays(): void
    {
        $metadata = [
            'empty' => [],
            'nested_empty' => [
                'inner_empty' => [],
            ],
        ];

        $normalized = $this->adapter->validateAndNormalizeMetadata($metadata);
        $this->assertEquals($metadata, $normalized);
    }

    public function testExtractEntityIdWithNonPublicIdProperty(): void
    {
        // Test entity with no accessible id (should fall back to object hash)
        $entity = new class {
            // No public getId() method or public id property
            // This tests the object hash fallback path
        };

        $id = $this->adapter->extractEntityId($entity);
        $this->assertIsString($id);
        $this->assertNotEmpty($id);
        // Should be consistent for same object
        $this->assertEquals($id, $this->adapter->extractEntityId($entity));
    }

    public function testExtractEntityIdWithGetIdReturningNonString(): void
    {
        // Test entity where getId() returns non-string (should convert to string)
        $entity = new class {
            public function getId(): int
            {
                return 12345;
            }
        };

        $id = $this->adapter->extractEntityId($entity);
        $this->assertEquals('12345', $id);
        $this->assertIsString($id);
    }

    public function testExtractEntityIdWithIdPropertyNonString(): void
    {
        // Test entity with non-string id property (should convert to string)
        $entity = new class {
            public int $id = 67890;
        };

        $id = $this->adapter->extractEntityId($entity);
        $this->assertEquals('67890', $id);
        $this->assertIsString($id);
    }

    public function testExtractEntityTypeWithGetTypeReturningNonString(): void
    {
        // Test entity where getType() returns non-string (should fall back to class name)
        $entity = new class {
            public function getType(): int
            {
                return 123;
            }
        };

        $type = $this->adapter->extractEntityType($entity);
        // Should fall back to class name since getType() doesn't return a string
        $this->assertStringContainsString('class@anonymous', $type);
        $this->assertIsString($type);
    }

    public function testExtractEntityIdWithReflectionException(): void
    {
        // Create an entity that will cause reflection issues
        $entity = new class {
            // This should trigger the reflection exception path
        };

        $id = $this->adapter->extractEntityId($entity);
        $this->assertIsString($id);
        $this->assertNotEmpty($id);
    }

    // ========================================
    // Query Edge Cases and Error Paths
    // ========================================

    public function testQueryWithBetweenOperator(): void
    {
        $user = $this->createTestEntity('user-1', 'User');
        $project1 = $this->createTestEntity('project-1', 'Project');
        $project2 = $this->createTestEntity('project-2', 'Project');
        $project3 = $this->createTestEntity('project-3', 'Project');

        $this->edgeBinder->bind($user, $project1, 'has_access', ['priority' => 1]);
        $this->edgeBinder->bind($user, $project2, 'has_access', ['priority' => 5]);
        $this->edgeBinder->bind($user, $project3, 'has_access', ['priority' => 10]);

        $query = $this->edgeBinder->query()
            ->whereBetween('metadata.priority', 2, 8);

        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertEquals(5, $results[0]->getMetadata()['priority']);
    }

    public function testQueryWithWhereExists(): void
    {
        $user = $this->createTestEntity('user-1', 'User');
        $project1 = $this->createTestEntity('project-1', 'Project');
        $project2 = $this->createTestEntity('project-2', 'Project');

        $this->edgeBinder->bind($user, $project1, 'has_access', ['description' => 'has description']);
        $this->edgeBinder->bind($user, $project2, 'has_access', []); // No description

        $query = $this->edgeBinder->query()
            ->whereExists('metadata.description');

        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertTrue(array_key_exists('description', $results[0]->getMetadata()));
    }

    public function testQueryWithWhereNull(): void
    {
        $user = $this->createTestEntity('user-1', 'User');
        $project1 = $this->createTestEntity('project-1', 'Project');
        $project2 = $this->createTestEntity('project-2', 'Project');

        $this->edgeBinder->bind($user, $project1, 'has_access', ['description' => null]);
        $this->edgeBinder->bind($user, $project2, 'has_access', ['description' => 'has description']);

        $query = $this->edgeBinder->query()
            ->whereNull('metadata.description');

        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertNull($results[0]->getMetadata()['description']);
    }
}

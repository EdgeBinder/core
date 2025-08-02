<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Unit\Persistence\InMemory;

use EdgeBinder\Binding;
use EdgeBinder\Contracts\BindingInterface;
use EdgeBinder\Contracts\EntityInterface;
use EdgeBinder\Contracts\QueryBuilderInterface;
use EdgeBinder\Exception\BindingNotFoundException;
use EdgeBinder\Exception\InvalidMetadataException;
use EdgeBinder\Exception\PersistenceException;
use EdgeBinder\Persistence\InMemory\InMemoryAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Comprehensive tests for InMemoryAdapter.
 *
 * Tests all methods with various scenarios, edge cases, and error conditions
 * to achieve close to 100% code coverage.
 */
final class InMemoryAdapterTest extends TestCase
{
    private InMemoryAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new InMemoryAdapter();
    }

    // ========================================
    // Entity Extraction Tests
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
        $this->assertSame('entity-123', $id);
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
        $this->assertSame('method-456', $id);
    }

    public function testExtractEntityIdFromGetIdMethodWithIntId(): void
    {
        $entity = new class {
            public function getId(): int
            {
                return 789;
            }
        };

        $id = $this->adapter->extractEntityId($entity);
        $this->assertSame('789', $id);
    }

    public function testExtractEntityIdFromGetIdMethodWithFloatId(): void
    {
        $entity = new class {
            public function getId(): float
            {
                return 123.45;
            }
        };

        $id = $this->adapter->extractEntityId($entity);
        $this->assertSame('123.45', $id);
    }

    public function testExtractEntityIdFromIdProperty(): void
    {
        $entity = new class {
            public string $id = 'property-789';
        };

        $id = $this->adapter->extractEntityId($entity);
        $this->assertSame('property-789', $id);
    }

    public function testExtractEntityIdFromIntIdProperty(): void
    {
        $entity = new class {
            public int $id = 999;
        };

        $id = $this->adapter->extractEntityId($entity);
        $this->assertSame('999', $id);
    }

    public function testExtractEntityIdFromPrivateIdProperty(): void
    {
        $entity = new class {
            private string $id = 'private-123';

            public function getId(): string
            {
                return $this->id;
            }
        };

        $id = $this->adapter->extractEntityId($entity);
        $this->assertSame('private-123', $id);
    }

    public function testExtractEntityIdFallsBackToObjectHash(): void
    {
        $entity = new class {};

        $id = $this->adapter->extractEntityId($entity);
        $this->assertSame(spl_object_hash($entity), $id);
    }

    public function testExtractEntityIdIgnoresEmptyStringFromGetId(): void
    {
        $entity = new class {
            public function getId(): string
            {
                return '';
            }
        };

        $id = $this->adapter->extractEntityId($entity);
        $this->assertSame(spl_object_hash($entity), $id);
    }

    public function testExtractEntityIdIgnoresNullFromGetId(): void
    {
        $entity = new class {
            public function getId(): ?string
            {
                return null;
            }
        };

        $id = $this->adapter->extractEntityId($entity);
        $this->assertSame(spl_object_hash($entity), $id);
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
        $this->assertSame('CustomType', $type);
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
        $this->assertSame('MethodType', $type);
    }

    public function testExtractEntityTypeFallsBackToClassName(): void
    {
        $entity = new class {};

        $type = $this->adapter->extractEntityType($entity);
        $this->assertStringContainsString('class@anonymous', $type);
    }

    public function testExtractEntityTypeIgnoresEmptyStringFromGetType(): void
    {
        $entity = new class {
            public function getType(): string
            {
                return '';
            }
        };

        $type = $this->adapter->extractEntityType($entity);
        $this->assertStringContainsString('class@anonymous', $type);
    }

    // ========================================
    // Metadata Validation Tests
    // ========================================

    public function testValidateAndNormalizeMetadataWithValidData(): void
    {
        $metadata = [
            'string' => 'value',
            'int' => 123,
            'float' => 45.67,
            'bool' => true,
            'null' => null,
            'array' => ['nested' => 'value'],
        ];

        $result = $this->adapter->validateAndNormalizeMetadata($metadata);
        $this->assertSame($metadata, $result);
    }

    public function testValidateAndNormalizeMetadataWithDateTime(): void
    {
        $dateTime = new \DateTimeImmutable('2023-01-01T12:00:00Z');
        $metadata = ['created_at' => $dateTime];

        $result = $this->adapter->validateAndNormalizeMetadata($metadata);
        $this->assertSame(['created_at' => '2023-01-01T12:00:00+00:00'], $result);
    }

    public function testValidateAndNormalizeMetadataWithDateTime2(): void
    {
        $dateTime = new \DateTime('2023-06-15T15:30:45+02:00');
        $metadata = ['updated_at' => $dateTime];

        $result = $this->adapter->validateAndNormalizeMetadata($metadata);
        $this->assertSame(['updated_at' => '2023-06-15T15:30:45+02:00'], $result);
    }

    public function testValidateAndNormalizeMetadataThrowsOnResource(): void
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

    public function testValidateAndNormalizeMetadataThrowsOnInvalidObject(): void
    {
        $object = new \stdClass();
        $metadata = ['object' => $object];

        $this->expectException(InvalidMetadataException::class);
        $this->expectExceptionMessage('Metadata can only contain DateTime objects');

        $this->adapter->validateAndNormalizeMetadata($metadata);
    }

    public function testValidateAndNormalizeMetadataThrowsOnNonStringKey(): void
    {
        /** @var array<string, mixed> $metadata */
        $metadata = [123 => 'value'];

        $this->expectException(InvalidMetadataException::class);
        $this->expectExceptionMessage('Metadata keys must be strings');

        $this->adapter->validateAndNormalizeMetadata($metadata);
    }

    public function testValidateAndNormalizeMetadataThrowsOnTooDeepNesting(): void
    {
        // Create deeply nested array (11 levels deep)
        $metadata = [];
        $current = &$metadata;
        for ($i = 0; $i < 11; ++$i) {
            $current['level'] = [];
            $current = &$current['level'];
        }
        $current = 'deep_value';

        $this->expectException(InvalidMetadataException::class);
        $this->expectExceptionMessage('Metadata nesting too deep (max 10 levels)');

        $this->adapter->validateAndNormalizeMetadata($metadata);
    }

    public function testValidateAndNormalizeMetadataWithMaxAllowedNesting(): void
    {
        // Create 10 levels deep (should be allowed)
        $metadata = [];
        $current = &$metadata;
        for ($i = 0; $i < 10; ++$i) {
            $current['level'] = [];
            $current = &$current['level'];
        }
        $current = 'deep_value';

        $result = $this->adapter->validateAndNormalizeMetadata($metadata);
        $this->assertIsArray($result);
    }

    // ========================================
    // CRUD Operation Tests
    // ========================================

    public function testStoreAndFindBinding(): void
    {
        $binding = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access');

        $this->adapter->store($binding);
        $found = $this->adapter->find($binding->getId());

        $this->assertSame($binding, $found);
    }

    public function testFindNonExistentBinding(): void
    {
        $result = $this->adapter->find('non-existent');
        $this->assertNull($result);
    }

    public function testStoreBindingWithInvalidMetadata(): void
    {
        $resource = fopen('php://memory', 'r');
        $this->assertIsResource($resource);
        $binding = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access', ['resource' => $resource]);

        $this->expectException(InvalidMetadataException::class);

        try {
            $this->adapter->store($binding);
        } finally {
            if (is_resource($resource)) {
                fclose($resource);
            }
        }
    }

    public function testDeleteExistingBinding(): void
    {
        $binding = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access');
        $this->adapter->store($binding);

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

    public function testUpdateMetadataExistingBinding(): void
    {
        $binding = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access', ['level' => 'read']);
        $this->adapter->store($binding);

        $newMetadata = ['level' => 'write', 'granted_by' => 'admin'];
        $this->adapter->updateMetadata($binding->getId(), $newMetadata);

        $updated = $this->adapter->find($binding->getId());
        $this->assertNotNull($updated);
        $this->assertSame($newMetadata, $updated->getMetadata());
    }

    public function testUpdateMetadataNonExistentBinding(): void
    {
        $this->expectException(BindingNotFoundException::class);
        $this->expectExceptionMessage("Binding with ID 'non-existent' not found");

        $this->adapter->updateMetadata('non-existent', ['level' => 'write']);
    }

    public function testUpdateMetadataWithInvalidData(): void
    {
        $binding = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access');
        $this->adapter->store($binding);

        $resource = fopen('php://memory', 'r');
        $this->assertIsResource($resource);
        $invalidMetadata = ['resource' => $resource];

        $this->expectException(InvalidMetadataException::class);

        try {
            $this->adapter->updateMetadata($binding->getId(), $invalidMetadata);
        } finally {
            if (is_resource($resource)) {
                fclose($resource);
            }
        }
    }

    // ========================================
    // Entity-based Query Tests
    // ========================================

    public function testFindByEntity(): void
    {
        $binding1 = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access');
        $binding2 = Binding::create('User', 'user-1', 'Project', 'project-2', 'has_access');
        $binding3 = Binding::create('User', 'user-2', 'Project', 'project-1', 'has_access');

        $this->adapter->store($binding1);
        $this->adapter->store($binding2);
        $this->adapter->store($binding3);

        $results = $this->adapter->findByEntity('User', 'user-1');
        $this->assertCount(2, $results);
        $this->assertContains($binding1, $results);
        $this->assertContains($binding2, $results);
    }

    public function testFindByEntityNoResults(): void
    {
        $results = $this->adapter->findByEntity('User', 'non-existent');
        $this->assertEmpty($results);
    }

    public function testFindBetweenEntities(): void
    {
        $binding1 = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access');
        $binding2 = Binding::create('User', 'user-1', 'Project', 'project-1', 'owns');
        $binding3 = Binding::create('User', 'user-1', 'Project', 'project-2', 'has_access');

        $this->adapter->store($binding1);
        $this->adapter->store($binding2);
        $this->adapter->store($binding3);

        $results = $this->adapter->findBetweenEntities('User', 'user-1', 'Project', 'project-1');
        $this->assertCount(2, $results);
        $this->assertContains($binding1, $results);
        $this->assertContains($binding2, $results);
    }

    public function testFindBetweenEntitiesWithTypeFilter(): void
    {
        $binding1 = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access');
        $binding2 = Binding::create('User', 'user-1', 'Project', 'project-1', 'owns');

        $this->adapter->store($binding1);
        $this->adapter->store($binding2);

        $results = $this->adapter->findBetweenEntities('User', 'user-1', 'Project', 'project-1', 'owns');
        $this->assertCount(1, $results);
        $this->assertContains($binding2, $results);
    }

    public function testFindBetweenEntitiesNoResults(): void
    {
        $results = $this->adapter->findBetweenEntities('User', 'user-1', 'Project', 'project-1');
        $this->assertEmpty($results);
    }

    public function testDeleteByEntity(): void
    {
        $binding1 = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access');
        $binding2 = Binding::create('User', 'user-1', 'Project', 'project-2', 'has_access');
        $binding3 = Binding::create('User', 'user-2', 'Project', 'project-1', 'has_access');

        $this->adapter->store($binding1);
        $this->adapter->store($binding2);
        $this->adapter->store($binding3);

        $deletedCount = $this->adapter->deleteByEntity('User', 'user-1');
        $this->assertSame(2, $deletedCount);

        $this->assertNull($this->adapter->find($binding1->getId()));
        $this->assertNull($this->adapter->find($binding2->getId()));
        $this->assertNotNull($this->adapter->find($binding3->getId()));
    }

    public function testDeleteByEntityNoMatches(): void
    {
        $deletedCount = $this->adapter->deleteByEntity('User', 'non-existent');
        $this->assertSame(0, $deletedCount);
    }

    // ========================================
    // Query Execution Tests
    // ========================================

    public function testExecuteQueryWithMockQueryBuilder(): void
    {
        $binding1 = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access', ['level' => 'read']);
        $binding2 = Binding::create('User', 'user-2', 'Project', 'project-2', 'has_access', ['level' => 'write']);

        $this->adapter->store($binding1);
        $this->adapter->store($binding2);

        $query = $this->createMockQueryBuilder([
            'from_type' => 'User',
            'from_id' => 'user-1',
        ]);

        $results = $this->adapter->executeQuery($query);
        $this->assertCount(1, $results);
        $this->assertContains($binding1, $results);
    }

    public function testExecuteQueryWithTypeFilter(): void
    {
        $binding1 = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access');
        $binding2 = Binding::create('User', 'user-1', 'Project', 'project-1', 'owns');

        $this->adapter->store($binding1);
        $this->adapter->store($binding2);

        $query = $this->createMockQueryBuilder([
            'type' => 'owns',
        ]);

        $results = $this->adapter->executeQuery($query);
        $this->assertCount(1, $results);
        $this->assertContains($binding2, $results);
    }

    public function testExecuteQueryWithToFilter(): void
    {
        $binding1 = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access');
        $binding2 = Binding::create('User', 'user-2', 'Project', 'project-1', 'has_access');

        $this->adapter->store($binding1);
        $this->adapter->store($binding2);

        $query = $this->createMockQueryBuilder([
            'to' => ['type' => 'Project', 'id' => 'project-1'],
        ]);

        $results = $this->adapter->executeQuery($query);
        $this->assertCount(2, $results);
        $this->assertContains($binding1, $results);
        $this->assertContains($binding2, $results);
    }

    public function testExecuteQueryWithWhereConditions(): void
    {
        $binding1 = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access', ['level' => 'read']);
        $binding2 = Binding::create('User', 'user-2', 'Project', 'project-2', 'has_access', ['level' => 'write']);

        $this->adapter->store($binding1);
        $this->adapter->store($binding2);

        $query = $this->createMockQueryBuilder([
            'where' => [
                ['field' => 'level', 'operator' => '=', 'value' => 'write'],
            ],
        ]);

        $results = $this->adapter->executeQuery($query);
        $this->assertCount(1, $results);
        $this->assertContains($binding2, $results);
    }

    public function testExecuteQueryWithOrdering(): void
    {
        $binding1 = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access');
        $binding2 = Binding::create('User', 'user-2', 'Project', 'project-2', 'has_access');

        $this->adapter->store($binding1);
        $this->adapter->store($binding2);

        $query = $this->createMockQueryBuilder([
            'orderBy' => ['field' => 'fromId', 'direction' => 'desc'],
        ]);

        $results = $this->adapter->executeQuery($query);
        $this->assertCount(2, $results);
        $this->assertSame($binding2, $results[0]); // user-2 comes before user-1 in desc order
        $this->assertSame($binding1, $results[1]);
    }

    public function testExecuteQueryWithPagination(): void
    {
        $binding1 = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access');
        $binding2 = Binding::create('User', 'user-2', 'Project', 'project-2', 'has_access');
        $binding3 = Binding::create('User', 'user-3', 'Project', 'project-3', 'has_access');

        $this->adapter->store($binding1);
        $this->adapter->store($binding2);
        $this->adapter->store($binding3);

        $query = $this->createMockQueryBuilder([
            'offset' => 1,
            'limit' => 1,
        ]);

        $results = $this->adapter->executeQuery($query);
        $this->assertCount(1, $results);
    }

    public function testCountQuery(): void
    {
        $binding1 = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access');
        $binding2 = Binding::create('User', 'user-2', 'Project', 'project-2', 'has_access');

        $this->adapter->store($binding1);
        $this->adapter->store($binding2);

        $query = $this->createMockQueryBuilder([]);
        $count = $this->adapter->count($query);

        $this->assertSame(2, $count);
    }

    // ========================================
    // Advanced Query Tests
    // ========================================

    public function testExecuteQueryWithComplexWhereConditions(): void
    {
        $binding1 = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access', ['level' => 'read', 'score' => 85]);
        $binding2 = Binding::create('User', 'user-2', 'Project', 'project-2', 'has_access', ['level' => 'write', 'score' => 95]);
        $binding3 = Binding::create('User', 'user-3', 'Project', 'project-3', 'has_access', ['level' => 'admin', 'score' => 75]);

        $this->adapter->store($binding1);
        $this->adapter->store($binding2);
        $this->adapter->store($binding3);

        // Test greater than
        $query = $this->createMockQueryBuilder([
            'where' => [
                ['field' => 'score', 'operator' => '>', 'value' => 80],
            ],
        ]);
        $results = $this->adapter->executeQuery($query);
        $this->assertCount(2, $results);

        // Test less than or equal
        $query = $this->createMockQueryBuilder([
            'where' => [
                ['field' => 'score', 'operator' => '<=', 'value' => 85],
            ],
        ]);
        $results = $this->adapter->executeQuery($query);
        $this->assertCount(2, $results);

        // Test not equal
        $query = $this->createMockQueryBuilder([
            'where' => [
                ['field' => 'level', 'operator' => '!=', 'value' => 'read'],
            ],
        ]);
        $results = $this->adapter->executeQuery($query);
        $this->assertCount(2, $results);
    }

    public function testExecuteQueryWithInOperator(): void
    {
        $binding1 = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access', ['level' => 'read']);
        $binding2 = Binding::create('User', 'user-2', 'Project', 'project-2', 'has_access', ['level' => 'write']);
        $binding3 = Binding::create('User', 'user-3', 'Project', 'project-3', 'has_access', ['level' => 'admin']);

        $this->adapter->store($binding1);
        $this->adapter->store($binding2);
        $this->adapter->store($binding3);

        $query = $this->createMockQueryBuilder([
            'where' => [
                ['field' => 'level', 'operator' => 'in', 'value' => ['read', 'write']],
            ],
        ]);

        $results = $this->adapter->executeQuery($query);
        $this->assertCount(2, $results);
        $this->assertContains($binding1, $results);
        $this->assertContains($binding2, $results);
    }

    public function testExecuteQueryWithNotInOperator(): void
    {
        $binding1 = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access', ['level' => 'read']);
        $binding2 = Binding::create('User', 'user-2', 'Project', 'project-2', 'has_access', ['level' => 'write']);
        $binding3 = Binding::create('User', 'user-3', 'Project', 'project-3', 'has_access', ['level' => 'admin']);

        $this->adapter->store($binding1);
        $this->adapter->store($binding2);
        $this->adapter->store($binding3);

        $query = $this->createMockQueryBuilder([
            'where' => [
                ['field' => 'level', 'operator' => 'not_in', 'value' => ['read', 'write']],
            ],
        ]);

        $results = $this->adapter->executeQuery($query);
        $this->assertCount(1, $results);
        $this->assertContains($binding3, $results);
    }

    public function testExecuteQueryWithBetweenOperator(): void
    {
        $binding1 = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access', ['score' => 75]);
        $binding2 = Binding::create('User', 'user-2', 'Project', 'project-2', 'has_access', ['score' => 85]);
        $binding3 = Binding::create('User', 'user-3', 'Project', 'project-3', 'has_access', ['score' => 95]);

        $this->adapter->store($binding1);
        $this->adapter->store($binding2);
        $this->adapter->store($binding3);

        $query = $this->createMockQueryBuilder([
            'where' => [
                ['field' => 'score', 'operator' => 'between', 'value' => [80, 90]],
            ],
        ]);

        $results = $this->adapter->executeQuery($query);
        $this->assertCount(1, $results);
        $this->assertContains($binding2, $results);
    }

    public function testExecuteQueryWithExistsOperator(): void
    {
        $binding1 = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access', ['level' => 'read']);
        $binding2 = Binding::create('User', 'user-2', 'Project', 'project-2', 'has_access', []);

        $this->adapter->store($binding1);
        $this->adapter->store($binding2);

        $query = $this->createMockQueryBuilder([
            'where' => [
                ['field' => 'level', 'operator' => 'exists'],
            ],
        ]);

        $results = $this->adapter->executeQuery($query);
        $this->assertCount(1, $results);
        $this->assertContains($binding1, $results);
    }

    public function testExecuteQueryWithNullOperator(): void
    {
        $binding1 = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access', ['level' => null]);
        $binding2 = Binding::create('User', 'user-2', 'Project', 'project-2', 'has_access', ['level' => 'read']);
        $binding3 = Binding::create('User', 'user-3', 'Project', 'project-3', 'has_access', []);

        $this->adapter->store($binding1);
        $this->adapter->store($binding2);
        $this->adapter->store($binding3);

        $query = $this->createMockQueryBuilder([
            'where' => [
                ['field' => 'level', 'operator' => 'null'],
            ],
        ]);

        $results = $this->adapter->executeQuery($query);
        $this->assertCount(2, $results);
        $this->assertContains($binding1, $results);
        $this->assertContains($binding3, $results);
    }

    public function testExecuteQueryWithNotNullOperator(): void
    {
        $binding1 = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access', ['level' => 'read']);
        $binding2 = Binding::create('User', 'user-2', 'Project', 'project-2', 'has_access', ['level' => null]);
        $binding3 = Binding::create('User', 'user-3', 'Project', 'project-3', 'has_access', []);

        $this->adapter->store($binding1);
        $this->adapter->store($binding2);
        $this->adapter->store($binding3);

        $query = $this->createMockQueryBuilder([
            'where' => [
                ['field' => 'level', 'operator' => 'not_null'],
            ],
        ]);

        $results = $this->adapter->executeQuery($query);
        $this->assertCount(1, $results);
        $this->assertContains($binding1, $results);
    }

    public function testExecuteQueryWithUnsupportedOperator(): void
    {
        $binding = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access');
        $this->adapter->store($binding);

        $query = $this->createMockQueryBuilder([
            'where' => [
                ['field' => 'level', 'operator' => 'unsupported', 'value' => 'test'],
            ],
        ]);

        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage('Query execution failed');

        $this->adapter->executeQuery($query);
    }

    public function testExecuteQueryWithOrConditions(): void
    {
        $binding1 = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access', ['level' => 'read']);
        $binding2 = Binding::create('User', 'user-2', 'Project', 'project-2', 'has_access', ['level' => 'write']);
        $binding3 = Binding::create('User', 'user-3', 'Project', 'project-3', 'has_access', ['level' => 'admin']);

        $this->adapter->store($binding1);
        $this->adapter->store($binding2);
        $this->adapter->store($binding3);

        $query = $this->createMockQueryBuilder([
            'where' => [
                ['field' => 'level', 'operator' => '=', 'value' => 'read'],
            ],
            'orWhere' => [
                [
                    ['field' => 'level', 'operator' => '=', 'value' => 'admin'],
                ],
            ],
        ]);

        $results = $this->adapter->executeQuery($query);
        $this->assertCount(2, $results);
        $this->assertContains($binding1, $results);
        $this->assertContains($binding3, $results);
    }

    // ========================================
    // Ordering Tests
    // ========================================

    public function testOrderingByBindingFields(): void
    {
        $binding1 = Binding::create('User', 'user-2', 'Project', 'project-1', 'has_access');
        $binding2 = Binding::create('User', 'user-1', 'Project', 'project-2', 'has_access');

        $this->adapter->store($binding1);
        $this->adapter->store($binding2);

        // Test ordering by fromId ascending
        $query = $this->createMockQueryBuilder([
            'orderBy' => ['field' => 'fromId', 'direction' => 'asc'],
        ]);
        $results = $this->adapter->executeQuery($query);
        $this->assertSame($binding2, $results[0]); // user-1 comes first
        $this->assertSame($binding1, $results[1]); // user-2 comes second

        // Test ordering by fromId descending
        $query = $this->createMockQueryBuilder([
            'orderBy' => ['field' => 'fromId', 'direction' => 'desc'],
        ]);
        $results = $this->adapter->executeQuery($query);
        $this->assertSame($binding1, $results[0]); // user-2 comes first
        $this->assertSame($binding2, $results[1]); // user-1 comes second
    }

    public function testOrderingByMetadataFields(): void
    {
        $binding1 = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access', ['priority' => 3]);
        $binding2 = Binding::create('User', 'user-2', 'Project', 'project-2', 'has_access', ['priority' => 1]);
        $binding3 = Binding::create('User', 'user-3', 'Project', 'project-3', 'has_access', ['priority' => 2]);

        $this->adapter->store($binding1);
        $this->adapter->store($binding2);
        $this->adapter->store($binding3);

        $query = $this->createMockQueryBuilder([
            'orderBy' => ['field' => 'priority', 'direction' => 'asc'],
        ]);

        $results = $this->adapter->executeQuery($query);
        $this->assertSame(1, $results[0]->getMetadata()['priority']);
        $this->assertSame(2, $results[1]->getMetadata()['priority']);
        $this->assertSame(3, $results[2]->getMetadata()['priority']);
    }

    public function testOrderingByTimestamps(): void
    {
        $binding1 = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access');
        sleep(1); // Ensure different timestamps
        $binding2 = Binding::create('User', 'user-2', 'Project', 'project-2', 'has_access');

        $this->adapter->store($binding1);
        $this->adapter->store($binding2);

        $query = $this->createMockQueryBuilder([
            'orderBy' => ['field' => 'createdAt', 'direction' => 'desc'],
        ]);

        $results = $this->adapter->executeQuery($query);
        $this->assertSame($binding2, $results[0]); // Newer binding first
        $this->assertSame($binding1, $results[1]); // Older binding second
    }

    public function testOrderingWithMissingMetadataField(): void
    {
        $binding1 = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access', ['priority' => 1]);
        $binding2 = Binding::create('User', 'user-2', 'Project', 'project-2', 'has_access', []); // No priority

        $this->adapter->store($binding1);
        $this->adapter->store($binding2);

        $query = $this->createMockQueryBuilder([
            'orderBy' => ['field' => 'priority', 'direction' => 'asc'],
        ]);

        $results = $this->adapter->executeQuery($query);
        $this->assertCount(2, $results); // Should handle missing fields gracefully
    }

    // ========================================
    // Error Handling Tests
    // ========================================

    public function testStoreThrowsPersistenceExceptionOnUnexpectedError(): void
    {
        // Create a binding that will cause an error during metadata validation
        $binding = $this->createMock(BindingInterface::class);
        $binding->method('getId')->willReturn('test-id');
        $binding->method('getMetadata')->willThrowException(new \RuntimeException('Unexpected error'));

        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage('Failed to store binding');

        $this->adapter->store($binding);
    }

    public function testUpdateMetadataThrowsPersistenceExceptionOnUnexpectedError(): void
    {
        $binding = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access');
        $this->adapter->store($binding);

        // Mock the binding to throw an exception during withMetadata
        $mockBinding = $this->createMock(BindingInterface::class);
        $mockBinding->method('withMetadata')->willThrowException(new \RuntimeException('Unexpected error'));

        // Replace the stored binding with our mock
        $reflection = new \ReflectionObject($this->adapter);
        $bindingsProperty = $reflection->getProperty('bindings');
        $bindingsProperty->setAccessible(true);
        $bindings = $bindingsProperty->getValue($this->adapter);
        $bindings[$binding->getId()] = $mockBinding;
        $bindingsProperty->setValue($this->adapter, $bindings);

        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage('Failed to update metadata');

        $this->adapter->updateMetadata($binding->getId(), ['new' => 'metadata']);
    }

    public function testExecuteQueryThrowsPersistenceExceptionOnError(): void
    {
        $query = $this->createMock(QueryBuilderInterface::class);
        $query->method('getCriteria')->willThrowException(new \RuntimeException('Query error'));

        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage('Query execution failed');

        $this->adapter->executeQuery($query);
    }

    public function testCountThrowsPersistenceExceptionOnError(): void
    {
        $query = $this->createMock(QueryBuilderInterface::class);
        $query->method('getCriteria')->willThrowException(new \RuntimeException('Count error'));

        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage('Query count failed');

        $this->adapter->count($query);
    }

    // ========================================
    // Index Management Tests
    // ========================================

    public function testIndexesAreUpdatedOnStore(): void
    {
        $binding = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access');
        $this->adapter->store($binding);

        // Verify entity index is updated
        $results = $this->adapter->findByEntity('User', 'user-1');
        $this->assertCount(1, $results);
        $this->assertContains($binding, $results);

        $results = $this->adapter->findByEntity('Project', 'project-1');
        $this->assertCount(1, $results);
        $this->assertContains($binding, $results);
    }

    public function testIndexesAreCleanedOnDelete(): void
    {
        $binding = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access');
        $this->adapter->store($binding);

        $this->adapter->delete($binding->getId());

        // Verify entity index is cleaned
        $results = $this->adapter->findByEntity('User', 'user-1');
        $this->assertEmpty($results);

        $results = $this->adapter->findByEntity('Project', 'project-1');
        $this->assertEmpty($results);
    }

    // ========================================
    // Additional Coverage Tests
    // ========================================

    public function testExecuteQueryWithFromEntityFilter(): void
    {
        $binding1 = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access');
        $binding2 = Binding::create('User', 'user-2', 'Project', 'project-1', 'has_access');
        $binding3 = Binding::create('Admin', 'admin-1', 'Project', 'project-1', 'manages');

        $this->adapter->store($binding1);
        $this->adapter->store($binding2);
        $this->adapter->store($binding3);

        $query = $this->createMockQueryBuilder([
            'from_type' => 'User',
            'from_id' => 'user-1',
        ]);

        $results = $this->adapter->executeQuery($query);
        $this->assertCount(1, $results);
        $this->assertContains($binding1, $results);
    }

    public function testExecuteQueryWithFromEntityAndToEntityFilter(): void
    {
        $binding1 = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access');
        $binding2 = Binding::create('User', 'user-1', 'Project', 'project-2', 'has_access');
        $binding3 = Binding::create('User', 'user-2', 'Project', 'project-1', 'has_access');

        $this->adapter->store($binding1);
        $this->adapter->store($binding2);
        $this->adapter->store($binding3);

        $query = $this->createMockQueryBuilder([
            'from_type' => 'User',
            'from_id' => 'user-1',
            'to_type' => 'Project',
            'to_id' => 'project-1',
        ]);

        $results = $this->adapter->executeQuery($query);
        $this->assertCount(1, $results);
        $this->assertContains($binding1, $results);
    }

    public function testExecuteQueryWithOnlyFromTypeFilter(): void
    {
        $binding1 = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access');
        $binding2 = Binding::create('User', 'user-2', 'Project', 'project-1', 'has_access');
        $binding3 = Binding::create('Admin', 'admin-1', 'Project', 'project-1', 'manages');

        $this->adapter->store($binding1);
        $this->adapter->store($binding2);
        $this->adapter->store($binding3);

        // Note: from_type alone doesn't filter - need from_type AND from_id together
        // This test actually returns all bindings since from_type without from_id is ignored
        $query = $this->createMockQueryBuilder([
            'from_type' => 'User',
        ]);

        $results = $this->adapter->executeQuery($query);
        $this->assertCount(3, $results); // All bindings returned
        $this->assertContains($binding1, $results);
        $this->assertContains($binding2, $results);
        $this->assertContains($binding3, $results);
    }

    public function testExecuteQueryWithOnlyToTypeFilter(): void
    {
        $binding1 = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access');
        $binding2 = Binding::create('User', 'user-1', 'Task', 'task-1', 'assigned_to');
        $binding3 = Binding::create('User', 'user-2', 'Project', 'project-2', 'has_access');

        $this->adapter->store($binding1);
        $this->adapter->store($binding2);
        $this->adapter->store($binding3);

        // Note: to_type alone doesn't filter - need to_type AND to_id together
        // This test actually returns all bindings since to_type without to_id is ignored
        $query = $this->createMockQueryBuilder([
            'to_type' => 'Project',
        ]);

        $results = $this->adapter->executeQuery($query);
        $this->assertCount(3, $results); // All bindings returned
        $this->assertContains($binding1, $results);
        $this->assertContains($binding2, $results);
        $this->assertContains($binding3, $results);
    }

    public function testGetOrderingValueForAllBindingFields(): void
    {
        $binding = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access', ['priority' => 5]);
        $this->adapter->store($binding);

        // Test ordering by different fields to cover getOrderingValue method
        $fields = ['id', 'fromType', 'fromId', 'toType', 'toId', 'type', 'createdAt', 'updatedAt'];

        foreach ($fields as $field) {
            $query = $this->createMockQueryBuilder([
                'order_by' => [
                    'field' => $field,
                    'direction' => 'asc',
                ],
            ]);

            $results = $this->adapter->executeQuery($query);
            $this->assertCount(1, $results);
            $this->assertSame($binding, $results[0]);
        }
    }

    public function testGetOrderingValueForMetadataField(): void
    {
        $binding1 = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access', ['priority' => 1]);
        $binding2 = Binding::create('User', 'user-2', 'Project', 'project-2', 'has_access', ['priority' => 2]);

        $this->adapter->store($binding1);
        $this->adapter->store($binding2);

        $query = $this->createMockQueryBuilder([
            'order_by' => [
                'field' => 'metadata.priority',
                'direction' => 'desc',
            ],
        ]);

        $results = $this->adapter->executeQuery($query);
        $this->assertCount(2, $results);
        // Check by priority value instead of object identity
        $this->assertEquals(2, $results[0]->getMetadata()['priority']); // priority 2 first
        $this->assertEquals(1, $results[1]->getMetadata()['priority']); // priority 1 second
    }

    public function testExecuteQueryWithComplexFiltering(): void
    {
        $binding1 = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access', ['score' => 85]);
        $binding2 = Binding::create('User', 'user-2', 'Project', 'project-2', 'has_access', ['score' => 95]);
        $binding3 = Binding::create('Admin', 'admin-1', 'Project', 'project-3', 'manages', ['score' => 75]);

        $this->adapter->store($binding1);
        $this->adapter->store($binding2);
        $this->adapter->store($binding3);

        // Test filtering by binding type
        $query = $this->createMockQueryBuilder([
            'type' => 'has_access',
        ]);
        $results = $this->adapter->executeQuery($query);
        $this->assertCount(2, $results);
        $this->assertContains($binding1, $results);
        $this->assertContains($binding2, $results);
    }

    public function testValidateMetadataWithComplexNestedStructure(): void
    {
        $metadata = [
            'user' => [
                'profile' => [
                    'settings' => [
                        'notifications' => [
                            'email' => true,
                            'sms' => false,
                        ],
                    ],
                ],
            ],
            'timestamps' => [
                'created' => new \DateTime('2023-01-01'),
                'updated' => new \DateTimeImmutable('2023-01-02'),
            ],
        ];

        $result = $this->adapter->validateAndNormalizeMetadata($metadata);

        $this->assertTrue($result['user']['profile']['settings']['notifications']['email']);
        $this->assertFalse($result['user']['profile']['settings']['notifications']['sms']);
        $this->assertIsString($result['timestamps']['created']);
        $this->assertIsString($result['timestamps']['updated']);
    }

    public function testRemoveFromIndexesWithMultipleBindings(): void
    {
        $binding1 = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access');
        $binding2 = Binding::create('User', 'user-1', 'Project', 'project-2', 'has_access');
        $binding3 = Binding::create('User', 'user-2', 'Project', 'project-1', 'has_access');

        $this->adapter->store($binding1);
        $this->adapter->store($binding2);
        $this->adapter->store($binding3);

        // Delete one binding and verify indexes are properly updated
        $this->adapter->delete($binding1->getId());

        // user-1 should still have binding2
        $userBindings = $this->adapter->findByEntity('User', 'user-1');
        $this->assertCount(1, $userBindings);
        $this->assertContains($binding2, $userBindings);

        // project-1 should still have binding3
        $projectBindings = $this->adapter->findByEntity('Project', 'project-1');
        $this->assertCount(1, $projectBindings);
        $this->assertContains($binding3, $projectBindings);
    }

    public function testStoreBindingUpdatesTypeIndex(): void
    {
        $binding1 = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access');
        $binding2 = Binding::create('User', 'user-2', 'Project', 'project-2', 'has_access');
        $binding3 = Binding::create('User', 'user-3', 'Project', 'project-3', 'owns');

        $this->adapter->store($binding1);
        $this->adapter->store($binding2);
        $this->adapter->store($binding3);

        // Test that type filtering works (which relies on type index)
        $query = $this->createMockQueryBuilder([
            'type' => 'has_access',
        ]);

        $results = $this->adapter->executeQuery($query);
        $this->assertCount(2, $results);
        $this->assertContains($binding1, $results);
        $this->assertContains($binding2, $results);
    }

    public function testExecuteQueryWithEmptyResults(): void
    {
        // Test query that returns no results
        $query = $this->createMockQueryBuilder([
            'from_type' => 'NonExistent',
            'from_id' => 'non-existent',
        ]);

        $results = $this->adapter->executeQuery($query);
        $this->assertEmpty($results);
    }

    public function testCountQueryWithEmptyResults(): void
    {
        // Test count query that returns zero
        $query = $this->createMockQueryBuilder([
            'from_type' => 'NonExistent',
            'from_id' => 'non-existent',
        ]);

        $count = $this->adapter->count($query);
        $this->assertSame(0, $count);
    }

    /**
     * Create a mock QueryBuilderInterface for testing.
     *
     * @param array<string, mixed> $criteria
     */
    private function createMockQueryBuilder(array $criteria): QueryBuilderInterface
    {
        $mock = $this->createMock(QueryBuilderInterface::class);
        $mock->method('getCriteria')->willReturn($criteria);

        return $mock;
    }
}

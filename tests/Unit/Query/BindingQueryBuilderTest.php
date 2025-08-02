<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Unit\Query;

use EdgeBinder\Binding;
use EdgeBinder\Contracts\BindingInterface;
use EdgeBinder\Contracts\EntityInterface;
use EdgeBinder\Contracts\PersistenceAdapterInterface;
use EdgeBinder\Contracts\QueryBuilderInterface;
use EdgeBinder\Persistence\InMemory\InMemoryAdapter;
use EdgeBinder\Query\BindingQueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test entity for query builder tests.
 */
class QueryTestEntity implements EntityInterface
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

class BindingQueryBuilderTest extends TestCase
{
    /** @var PersistenceAdapterInterface&MockObject */
    private PersistenceAdapterInterface $storage;
    private BindingQueryBuilder $queryBuilder;

    protected function setUp(): void
    {
        $this->storage = $this->createMock(PersistenceAdapterInterface::class);
        $this->queryBuilder = new BindingQueryBuilder($this->storage);
    }

    /**
     * Create a real storage setup with test data for execution tests.
     *
     * @return array{InMemoryAdapter, BindingQueryBuilder, BindingInterface[]}
     */
    private function createRealStorageSetup(): array
    {
        $realStorage = new InMemoryAdapter();

        // Create some test bindings
        $binding1 = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access', ['level' => 'read']);
        $binding2 = Binding::create('User', 'user-1', 'Project', 'project-2', 'has_access', ['level' => 'write']);
        $binding3 = Binding::create('User', 'user-2', 'Project', 'project-1', 'owns', ['created_at' => '2023-01-01']);

        $realStorage->store($binding1);
        $realStorage->store($binding2);
        $realStorage->store($binding3);

        $realQueryBuilder = new BindingQueryBuilder($realStorage);

        return [$realStorage, $realQueryBuilder, [$binding1, $binding2, $binding3]];
    }

    public function testImplementsQueryBuilderInterface(): void
    {
        $this->assertInstanceOf(QueryBuilderInterface::class, $this->queryBuilder);
    }

    public function testFromWithObject(): void
    {
        $entity = new \stdClass();
        $this->storage->expects($this->once())
            ->method('extractEntityType')
            ->with($entity)
            ->willReturn('User');
        $this->storage->expects($this->once())
            ->method('extractEntityId')
            ->with($entity)
            ->willReturn('user-123');

        $result = $this->queryBuilder->from($entity);

        $this->assertNotSame($this->queryBuilder, $result);
        $this->assertEquals([
            'from_type' => 'User',
            'from_id' => 'user-123',
        ], $result->getCriteria());
    }

    public function testFromWithStringAndId(): void
    {
        $result = $this->queryBuilder->from('User', 'user-123');

        $this->assertEquals([
            'from_type' => 'User',
            'from_id' => 'user-123',
        ], $result->getCriteria());
    }

    public function testFromWithStringWithoutIdThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Entity ID is required when entity is provided as string');

        $this->queryBuilder->from('User');
    }

    public function testToWithObject(): void
    {
        $entity = new \stdClass();
        $this->storage->expects($this->once())
            ->method('extractEntityType')
            ->with($entity)
            ->willReturn('Project');
        $this->storage->expects($this->once())
            ->method('extractEntityId')
            ->with($entity)
            ->willReturn('project-456');

        $result = $this->queryBuilder->to($entity);

        $this->assertEquals([
            'to_type' => 'Project',
            'to_id' => 'project-456',
        ], $result->getCriteria());
    }

    public function testToWithStringAndId(): void
    {
        $result = $this->queryBuilder->to('Project', 'project-456');

        $this->assertEquals([
            'to_type' => 'Project',
            'to_id' => 'project-456',
        ], $result->getCriteria());
    }

    public function testToWithStringWithoutIdThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Entity ID is required when entity is provided as string');

        $this->queryBuilder->to('Project');
    }

    public function testType(): void
    {
        $result = $this->queryBuilder->type('has_access');

        $this->assertEquals(['type' => 'has_access'], $result->getCriteria());
    }

    public function testWhereWithTwoArguments(): void
    {
        $result = $this->queryBuilder->where('access_level', 'admin');

        $expected = [
            'where' => [
                [
                    'field' => 'access_level',
                    'operator' => '=',
                    'value' => 'admin',
                ],
            ],
        ];
        $this->assertEquals($expected, $result->getCriteria());
    }

    public function testWhereWithThreeArguments(): void
    {
        $result = $this->queryBuilder->where('score', '>', 0.8);

        $expected = [
            'where' => [
                [
                    'field' => 'score',
                    'operator' => '>',
                    'value' => 0.8,
                ],
            ],
        ];
        $this->assertEquals($expected, $result->getCriteria());
    }

    public function testMultipleWhereConditions(): void
    {
        $result = $this->queryBuilder
            ->where('access_level', 'admin')
            ->where('score', '>', 0.8);

        $expected = [
            'where' => [
                [
                    'field' => 'access_level',
                    'operator' => '=',
                    'value' => 'admin',
                ],
                [
                    'field' => 'score',
                    'operator' => '>',
                    'value' => 0.8,
                ],
            ],
        ];
        $this->assertEquals($expected, $result->getCriteria());
    }

    public function testWhereIn(): void
    {
        $result = $this->queryBuilder->whereIn('status', ['active', 'pending']);

        $expected = [
            'where' => [
                [
                    'field' => 'status',
                    'operator' => 'in',
                    'value' => ['active', 'pending'],
                ],
            ],
        ];
        $this->assertEquals($expected, $result->getCriteria());
    }

    public function testWhereBetween(): void
    {
        $result = $this->queryBuilder->whereBetween('score', 0.5, 0.9);

        $expected = [
            'where' => [
                [
                    'field' => 'score',
                    'operator' => 'between',
                    'value' => [0.5, 0.9],
                ],
            ],
        ];
        $this->assertEquals($expected, $result->getCriteria());
    }

    public function testWhereExists(): void
    {
        $result = $this->queryBuilder->whereExists('metadata_field');

        $expected = [
            'where' => [
                [
                    'field' => 'metadata_field',
                    'operator' => 'exists',
                    'value' => true,
                ],
            ],
        ];
        $this->assertEquals($expected, $result->getCriteria());
    }

    public function testWhereNull(): void
    {
        $result = $this->queryBuilder->whereNull('optional_field');

        $expected = [
            'where' => [
                [
                    'field' => 'optional_field',
                    'operator' => 'null',
                    'value' => true,
                ],
            ],
        ];
        $this->assertEquals($expected, $result->getCriteria());
    }

    public function testOrWhere(): void
    {
        $result = $this->queryBuilder->orWhere(function ($query) {
            return $query->where('status', 'active')->where('priority', 'high');
        });

        $expected = [
            'where' => [
                [
                    'type' => 'or',
                    'conditions' => [
                        [
                            'field' => 'status',
                            'operator' => '=',
                            'value' => 'active',
                        ],
                        [
                            'field' => 'priority',
                            'operator' => '=',
                            'value' => 'high',
                        ],
                    ],
                ],
            ],
        ];
        $this->assertEquals($expected, $result->getCriteria());
    }

    public function testOrderByAscending(): void
    {
        $result = $this->queryBuilder->orderBy('created_at');

        $expected = [
            'order_by' => [
                [
                    'field' => 'created_at',
                    'direction' => 'asc',
                ],
            ],
        ];
        $this->assertEquals($expected, $result->getCriteria());
    }

    public function testOrderByDescending(): void
    {
        $result = $this->queryBuilder->orderBy('score', 'desc');

        $expected = [
            'order_by' => [
                [
                    'field' => 'score',
                    'direction' => 'desc',
                ],
            ],
        ];
        $this->assertEquals($expected, $result->getCriteria());
    }

    public function testOrderByInvalidDirection(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Order direction must be 'asc' or 'desc', got: invalid");

        $this->queryBuilder->orderBy('field', 'invalid');
    }

    public function testMultipleOrderBy(): void
    {
        $result = $this->queryBuilder
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'asc');

        $expected = [
            'order_by' => [
                [
                    'field' => 'priority',
                    'direction' => 'desc',
                ],
                [
                    'field' => 'created_at',
                    'direction' => 'asc',
                ],
            ],
        ];
        $this->assertEquals($expected, $result->getCriteria());
    }

    public function testLimit(): void
    {
        $result = $this->queryBuilder->limit(10);

        $this->assertEquals(['limit' => 10], $result->getCriteria());
    }

    public function testLimitNegativeThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Limit must be non-negative, got: -1');

        $this->queryBuilder->limit(-1);
    }

    public function testOffset(): void
    {
        $result = $this->queryBuilder->offset(20);

        $this->assertEquals(['offset' => 20], $result->getCriteria());
    }

    public function testOffsetNegativeThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Offset must be non-negative, got: -5');

        $this->queryBuilder->offset(-5);
    }

    public function testComplexQuery(): void
    {
        $entity = new \stdClass();
        $this->storage->method('extractEntityType')->willReturn('User');
        $this->storage->method('extractEntityId')->willReturn('user-123');

        $result = $this->queryBuilder
            ->from($entity)
            ->type('has_access')
            ->where('access_level', 'admin')
            ->where('score', '>', 0.8)
            ->whereIn('status', ['active', 'verified'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->offset(20);

        $expected = [
            'from_type' => 'User',
            'from_id' => 'user-123',
            'type' => 'has_access',
            'where' => [
                [
                    'field' => 'access_level',
                    'operator' => '=',
                    'value' => 'admin',
                ],
                [
                    'field' => 'score',
                    'operator' => '>',
                    'value' => 0.8,
                ],
                [
                    'field' => 'status',
                    'operator' => 'in',
                    'value' => ['active', 'verified'],
                ],
            ],
            'order_by' => [
                [
                    'field' => 'created_at',
                    'direction' => 'desc',
                ],
            ],
            'limit' => 10,
            'offset' => 20,
        ];
        $this->assertEquals($expected, $result->getCriteria());
    }

    public function testGet(): void
    {
        [$realStorage, $realQueryBuilder, $bindings] = $this->createRealStorageSetup();

        // Query for user-1 bindings
        $result = $realQueryBuilder->from('User', 'user-1')->get();

        $this->assertCount(2, $result);
        $this->assertContains($bindings[0], $result); // binding1
        $this->assertContains($bindings[1], $result); // binding2
    }

    public function testFirst(): void
    {
        [$realStorage, $realQueryBuilder, $bindings] = $this->createRealStorageSetup();

        // Query for user-1 bindings and get first
        $result = $realQueryBuilder->from('User', 'user-1')->first();

        $this->assertNotNull($result);
        $this->assertInstanceOf(BindingInterface::class, $result);
        $this->assertEquals('User', $result->getFromType());
        $this->assertEquals('user-1', $result->getFromId());
    }

    public function testFirstReturnsNullWhenNoResults(): void
    {
        [$realStorage, $realQueryBuilder, $bindings] = $this->createRealStorageSetup();

        // Query for non-existent user
        $result = $realQueryBuilder->from('User', 'nonexistent')->first();

        $this->assertNull($result);
    }

    public function testCount(): void
    {
        [$realStorage, $realQueryBuilder, $bindings] = $this->createRealStorageSetup();

        // Count all bindings for user-1
        $result = $realQueryBuilder->from('User', 'user-1')->count();

        $this->assertEquals(2, $result);
    }

    public function testExistsReturnsTrueWhenCountGreaterThanZero(): void
    {
        [$realStorage, $realQueryBuilder, $bindings] = $this->createRealStorageSetup();

        // Check if user-1 has any bindings
        $result = $realQueryBuilder->from('User', 'user-1')->exists();

        $this->assertTrue($result);
    }

    public function testExistsReturnsFalseWhenCountIsZero(): void
    {
        [$realStorage, $realQueryBuilder, $bindings] = $this->createRealStorageSetup();

        // Check if non-existent user has any bindings
        $result = $realQueryBuilder->from('User', 'nonexistent')->exists();

        $this->assertFalse($result);
    }

    public function testImmutability(): void
    {
        $original = $this->queryBuilder;
        $modified = $original->type('test');

        $this->assertNotSame($original, $modified);
        $this->assertEquals([], $original->getCriteria());
        $this->assertEquals(['type' => 'test'], $modified->getCriteria());
    }
}

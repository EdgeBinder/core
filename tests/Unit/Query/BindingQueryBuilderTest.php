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
        // Use real InMemoryAdapter for this test since we're testing entity extraction
        $realStorage = new InMemoryAdapter();
        $realQueryBuilder = new BindingQueryBuilder($realStorage);

        $entity = new \stdClass();
        $entity->id = 'user-123'; // InMemoryAdapter will extract this

        $result = $realQueryBuilder->from($entity);

        $this->assertNotSame($realQueryBuilder, $result);
        $this->assertEquals([
            'fromType' => 'stdClass',
            'fromId' => 'user-123',
        ], $result->getCriteria());
    }

    public function testFromWithStringAndId(): void
    {
        $result = $this->queryBuilder->from('User', 'user-123');

        $this->assertEquals([
            'fromType' => 'User',
            'fromId' => 'user-123',
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
        // Use real InMemoryAdapter for this test since we're testing entity extraction
        $realStorage = new InMemoryAdapter();
        $realQueryBuilder = new BindingQueryBuilder($realStorage);

        $entity = new \stdClass();
        $entity->id = 'project-456'; // InMemoryAdapter will extract this

        $result = $realQueryBuilder->to($entity);

        $this->assertEquals([
            'toType' => 'stdClass',
            'toId' => 'project-456',
        ], $result->getCriteria());
    }

    public function testToWithStringAndId(): void
    {
        $result = $this->queryBuilder->to('Project', 'project-456');

        $this->assertEquals([
            'toType' => 'Project',
            'toId' => 'project-456',
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
            'orWhere' => [
                [
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
        ];
        $this->assertEquals($expected, $result->getCriteria());
    }

    public function testOrderByAscending(): void
    {
        $result = $this->queryBuilder->orderBy('created_at');

        $expected = [
            'orderBy' => [
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
            'orderBy' => [
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
            'orderBy' => [
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
        // Use real InMemoryAdapter for this test since we're testing entity extraction
        $realStorage = new InMemoryAdapter();
        $realQueryBuilder = new BindingQueryBuilder($realStorage);

        $entity = new \stdClass();
        $entity->id = 'user-123'; // InMemoryAdapter will extract this

        $result = $realQueryBuilder
            ->from($entity)
            ->type('has_access')
            ->where('access_level', 'admin')
            ->where('score', '>', 0.8)
            ->whereIn('status', ['active', 'verified'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->offset(20);

        $expected = [
            'fromType' => 'stdClass',
            'fromId' => 'user-123',
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
            'orderBy' => [
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

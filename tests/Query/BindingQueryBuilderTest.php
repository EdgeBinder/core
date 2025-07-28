<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Query;

use EdgeBinder\Contracts\BindingInterface;
use EdgeBinder\Contracts\QueryBuilderInterface;
use EdgeBinder\Contracts\StorageAdapterInterface;
use EdgeBinder\Query\BindingQueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BindingQueryBuilderTest extends TestCase
{
    /** @var StorageAdapterInterface&MockObject */
    private StorageAdapterInterface $storage;
    private BindingQueryBuilder $queryBuilder;

    protected function setUp(): void
    {
        $this->storage = $this->createMock(StorageAdapterInterface::class);
        $this->queryBuilder = new BindingQueryBuilder($this->storage);
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
        $expectedBindings = [
            $this->createMock(BindingInterface::class),
            $this->createMock(BindingInterface::class),
        ];

        $this->storage->expects($this->once())
            ->method('executeQuery')
            ->with($this->queryBuilder)
            ->willReturn($expectedBindings);

        $result = $this->queryBuilder->get();

        $this->assertSame($expectedBindings, $result);
    }

    public function testFirst(): void
    {
        $binding = $this->createMock(BindingInterface::class);
        $this->storage->expects($this->once())
            ->method('executeQuery')
            ->willReturn([$binding]);

        $result = $this->queryBuilder->first();

        $this->assertSame($binding, $result);
    }

    public function testFirstReturnsNullWhenNoResults(): void
    {
        $this->storage->expects($this->once())
            ->method('executeQuery')
            ->willReturn([]);

        $result = $this->queryBuilder->first();

        $this->assertNull($result);
    }

    public function testCount(): void
    {
        $this->storage->expects($this->once())
            ->method('count')
            ->with($this->queryBuilder)
            ->willReturn(42);

        $result = $this->queryBuilder->count();

        $this->assertEquals(42, $result);
    }

    public function testExistsReturnsTrueWhenCountGreaterThanZero(): void
    {
        $this->storage->expects($this->once())
            ->method('count')
            ->willReturn(5);

        $result = $this->queryBuilder->exists();

        $this->assertTrue($result);
    }

    public function testExistsReturnsFalseWhenCountIsZero(): void
    {
        $this->storage->expects($this->once())
            ->method('count')
            ->willReturn(0);

        $result = $this->queryBuilder->exists();

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

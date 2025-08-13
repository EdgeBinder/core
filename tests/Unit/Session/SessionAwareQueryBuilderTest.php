<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Unit\Session;

use EdgeBinder\Binding;
use EdgeBinder\Contracts\EntityInterface;
use EdgeBinder\Persistence\InMemory\InMemoryAdapter;
use EdgeBinder\Session\BindingCache;
use EdgeBinder\Session\SessionAwareQueryBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SessionAwareQueryBuilder.
 *
 * Tests the query builder that merges results from session cache
 * and the underlying adapter to provide immediate consistency.
 */
final class SessionAwareQueryBuilderTest extends TestCase
{
    private InMemoryAdapter $adapter;
    private BindingCache $cache;
    private SessionAwareQueryBuilder $queryBuilder;
    private TestEntity $fromEntity;
    private TestEntity $toEntity;

    protected function setUp(): void
    {
        $this->adapter = new InMemoryAdapter();
        $this->cache = new BindingCache();
        $this->queryBuilder = new SessionAwareQueryBuilder($this->adapter, $this->cache);

        $this->fromEntity = new TestEntity('user-1', 'User');
        $this->toEntity = new TestEntity('org-1', 'Organization');
    }

    public function testQueryBuilderFluentInterface(): void
    {
        $builder = $this->queryBuilder
            ->from($this->fromEntity)
            ->to($this->toEntity)
            ->type('member_of');

        $this->assertInstanceOf(SessionAwareQueryBuilder::class, $builder);
        $this->assertNotSame($this->queryBuilder, $builder);
    }

    public function testQueryMergesCacheAndAdapterResults(): void
    {
        // Create bindings for cache and adapter
        $cacheBinding = Binding::create(
            fromType: 'User',
            fromId: 'user-1',
            toType: 'Organization',
            toId: 'org-1',
            type: 'member_of',
            metadata: ['source' => 'cache']
        );

        $adapterBinding = Binding::create(
            fromType: 'User',
            fromId: 'user-1',
            toType: 'Team',
            toId: 'team-1',
            type: 'member_of',
            metadata: ['source' => 'adapter']
        );

        // Store in cache
        $this->cache->store($cacheBinding);

        // Store in adapter
        $this->adapter->store($adapterBinding);

        // Execute query
        $results = $this->queryBuilder
            ->from($this->fromEntity)
            ->type('member_of')
            ->get();

        // Should contain both bindings
        $bindings = $results->getBindings();
        $this->assertCount(2, $bindings);

        $bindingIds = array_map(fn ($b) => $b->getId(), $bindings);
        $this->assertContains($cacheBinding->getId(), $bindingIds);
        $this->assertContains($adapterBinding->getId(), $bindingIds);
    }

    public function testQueryDeduplicatesResults(): void
    {
        // Create same binding in both cache and adapter
        $binding = Binding::create(
            fromType: 'User',
            fromId: 'user-1',
            toType: 'Organization',
            toId: 'org-1',
            type: 'member_of',
            metadata: []
        );

        // Store in cache
        $this->cache->store($binding);

        // Store in adapter
        $this->adapter->store($binding);

        // Execute query
        $results = $this->queryBuilder
            ->from($this->fromEntity)
            ->type('member_of')
            ->get();

        // Should contain only one instance
        $bindings = $results->getBindings();
        $this->assertCount(1, $bindings);
        $this->assertEquals($binding->getId(), $bindings[0]->getId());
    }

    public function testQueryWithOnlyCacheResults(): void
    {
        $cacheBinding = Binding::create(
            fromType: 'User',
            fromId: 'user-1',
            toType: 'Organization',
            toId: 'org-1',
            type: 'member_of',
            metadata: []
        );

        $this->cache->store($cacheBinding);

        // Adapter has no results

        $results = $this->queryBuilder
            ->from($this->fromEntity)
            ->type('member_of')
            ->get();

        $bindings = $results->getBindings();
        $this->assertCount(1, $bindings);
        $this->assertEquals($cacheBinding->getId(), $bindings[0]->getId());
    }

    public function testQueryWithOnlyAdapterResults(): void
    {
        $adapterBinding = Binding::create(
            fromType: 'User',
            fromId: 'user-1',
            toType: 'Organization',
            toId: 'org-1',
            type: 'member_of',
            metadata: []
        );

        // Cache is empty

        // Store in adapter
        $this->adapter->store($adapterBinding);

        $results = $this->queryBuilder
            ->from($this->fromEntity)
            ->type('member_of')
            ->get();

        $bindings = $results->getBindings();
        $this->assertCount(1, $bindings);
        $this->assertEquals($adapterBinding->getId(), $bindings[0]->getId());
    }

    public function testQueryWithNoResults(): void
    {
        // Cache is empty
        // Adapter is empty

        $results = $this->queryBuilder
            ->from($this->fromEntity)
            ->type('member_of')
            ->get();

        $bindings = $results->getBindings();
        $this->assertEmpty($bindings);
    }

    public function testCountMergesCacheAndAdapterResults(): void
    {
        // Create unique bindings for cache and adapter
        $cacheBinding = Binding::create(
            fromType: 'User',
            fromId: 'user-1',
            toType: 'Organization',
            toId: 'org-1',
            type: 'member_of',
            metadata: ['id' => 'cache']
        );

        $adapterBinding = Binding::create(
            fromType: 'User',
            fromId: 'user-1',
            toType: 'Team',
            toId: 'team-1',
            type: 'member_of',
            metadata: ['id' => 'adapter']
        );

        $this->cache->store($cacheBinding);
        $this->adapter->store($adapterBinding);

        $count = $this->queryBuilder
            ->from($this->fromEntity)
            ->type('member_of')
            ->count();

        $this->assertEquals(2, $count);
    }

    public function testFirstReturnsFirstResult(): void
    {
        $binding1 = Binding::create(
            fromType: 'User',
            fromId: 'user-1',
            toType: 'Organization',
            toId: 'org-1',
            type: 'member_of',
            metadata: ['order' => 1]
        );

        $this->cache->store($binding1);

        $result = $this->queryBuilder
            ->from($this->fromEntity)
            ->type('member_of')
            ->first();

        $this->assertNotNull($result);
        $this->assertEquals(1, $result->getMetadata()['order']);
    }

    public function testFirstReturnsNullWhenNoResults(): void
    {
        $result = $this->queryBuilder
            ->from($this->fromEntity)
            ->type('member_of')
            ->first();

        $this->assertNull($result);
    }

    public function testWhereMethod(): void
    {
        $builder = $this->queryBuilder->where('field', '=', 'value');

        $this->assertInstanceOf(SessionAwareQueryBuilder::class, $builder);
        $this->assertNotSame($this->queryBuilder, $builder);
    }

    public function testOrWhereMethod(): void
    {
        $builder = $this->queryBuilder->orWhere(function ($query) {
            return $query->where('field', '=', 'value');
        });

        $this->assertInstanceOf(SessionAwareQueryBuilder::class, $builder);
        $this->assertNotSame($this->queryBuilder, $builder);
    }

    public function testOrderByMethod(): void
    {
        $builder = $this->queryBuilder->orderBy('field', 'asc');

        $this->assertInstanceOf(SessionAwareQueryBuilder::class, $builder);
        $this->assertNotSame($this->queryBuilder, $builder);
    }

    public function testLimitMethod(): void
    {
        $builder = $this->queryBuilder->limit(10);

        $this->assertInstanceOf(SessionAwareQueryBuilder::class, $builder);
        $this->assertNotSame($this->queryBuilder, $builder);
    }

    public function testOffsetMethod(): void
    {
        $builder = $this->queryBuilder->offset(5);

        $this->assertInstanceOf(SessionAwareQueryBuilder::class, $builder);
        $this->assertNotSame($this->queryBuilder, $builder);
    }

    public function testFromWithEntityObject(): void
    {
        $builder = $this->queryBuilder->from($this->fromEntity);

        $this->assertInstanceOf(SessionAwareQueryBuilder::class, $builder);
        $this->assertNotSame($this->queryBuilder, $builder);
    }

    public function testToWithEntityObject(): void
    {
        $builder = $this->queryBuilder->to($this->toEntity);

        $this->assertInstanceOf(SessionAwareQueryBuilder::class, $builder);
        $this->assertNotSame($this->queryBuilder, $builder);
    }

    public function testFromWithStringId(): void
    {
        // String IDs need to be passed differently - this tests the error handling
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Entity ID is required when entity is provided as string');

        $this->queryBuilder->from('user-123');
    }

    public function testToWithStringId(): void
    {
        // String IDs need to be passed differently - this tests the error handling
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Entity ID is required when entity is provided as string');

        $this->queryBuilder->to('org-456');
    }

    public function testChainedMethods(): void
    {
        $builder = $this->queryBuilder
            ->from($this->fromEntity)
            ->to($this->toEntity)
            ->type('member_of')
            ->where('active', '=', true)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->offset(5);

        $this->assertInstanceOf(SessionAwareQueryBuilder::class, $builder);
        $this->assertNotSame($this->queryBuilder, $builder);
    }

    public function testQueryWithComplexCriteria(): void
    {
        // Create test bindings
        $binding1 = Binding::create(
            fromType: 'User',
            fromId: 'user-1',
            toType: 'Organization',
            toId: 'org-1',
            type: 'member_of',
            metadata: ['active' => true]
        );

        $binding2 = Binding::create(
            fromType: 'User',
            fromId: 'user-1',
            toType: 'Organization',
            toId: 'org-2',
            type: 'member_of',
            metadata: ['active' => false]
        );

        $this->cache->store($binding1);
        $this->cache->store($binding2);

        // Query with criteria
        $results = $this->queryBuilder
            ->from($this->fromEntity)
            ->type('member_of')
            ->get();

        // Should return both bindings (filtering happens at adapter level)
        $bindings = $results->getBindings();
        $this->assertCount(2, $bindings);
    }

    public function testWhereInMethod(): void
    {
        $builder = $this->queryBuilder->whereIn('status', ['active', 'pending']);

        $this->assertInstanceOf(SessionAwareQueryBuilder::class, $builder);
        $this->assertNotSame($this->queryBuilder, $builder);
    }

    public function testWhereBetweenMethod(): void
    {
        $builder = $this->queryBuilder->whereBetween('score', 10, 90);

        $this->assertInstanceOf(SessionAwareQueryBuilder::class, $builder);
        $this->assertNotSame($this->queryBuilder, $builder);
    }

    public function testWhereNotInMethod(): void
    {
        $builder = $this->queryBuilder->whereNotIn('status', ['deleted', 'archived']);

        $this->assertInstanceOf(SessionAwareQueryBuilder::class, $builder);
        $this->assertNotSame($this->queryBuilder, $builder);
    }

    public function testWhereNullMethod(): void
    {
        $builder = $this->queryBuilder->whereNull('deleted_at');

        $this->assertInstanceOf(SessionAwareQueryBuilder::class, $builder);
        $this->assertNotSame($this->queryBuilder, $builder);
    }

    public function testWhereNotNullMethod(): void
    {
        $builder = $this->queryBuilder->whereNotNull('created_at');

        $this->assertInstanceOf(SessionAwareQueryBuilder::class, $builder);
        $this->assertNotSame($this->queryBuilder, $builder);
    }

    public function testWhereExistsMethod(): void
    {
        $builder = $this->queryBuilder->whereExists('metadata.tags');

        $this->assertInstanceOf(SessionAwareQueryBuilder::class, $builder);
        $this->assertNotSame($this->queryBuilder, $builder);
    }

    public function testComplexWhereChaining(): void
    {
        $builder = $this->queryBuilder
            ->from($this->fromEntity)
            ->to($this->toEntity)
            ->type('member_of')
            ->where('active', '=', true)
            ->whereIn('role', ['admin', 'member'])
            ->whereBetween('score', 50, 100)
            ->whereNotIn('status', ['banned', 'suspended'])
            ->whereNull('deleted_at')
            ->whereNotNull('created_at')
            ->whereExists('metadata.permissions')
            ->orderBy('created_at', 'desc')
            ->limit(25)
            ->offset(10);

        $this->assertInstanceOf(SessionAwareQueryBuilder::class, $builder);
        $this->assertNotSame($this->queryBuilder, $builder);
    }

    public function testOrWhereWithComplexCallback(): void
    {
        $builder = $this->queryBuilder->orWhere(function ($query) {
            return $query
                ->where('status', '=', 'active')
                ->whereIn('role', ['admin', 'moderator'])
                ->whereNotNull('last_login');
        });

        $this->assertInstanceOf(SessionAwareQueryBuilder::class, $builder);
        $this->assertNotSame($this->queryBuilder, $builder);
    }

    public function testNestedOrWhereCallbacks(): void
    {
        $builder = $this->queryBuilder
            ->where('active', '=', true)
            ->orWhere(function ($query) {
                return $query
                    ->where('role', '=', 'admin')
                    ->whereNotIn('status', ['banned']);
            })
            ->orWhere(function ($query) {
                return $query
                    ->whereIn('permissions', ['read', 'write'])
                    ->whereExists('metadata.special_access');
            });

        $this->assertInstanceOf(SessionAwareQueryBuilder::class, $builder);
        $this->assertNotSame($this->queryBuilder, $builder);
    }

    public function testGetCriteriaMethod(): void
    {
        $builder = $this->queryBuilder
            ->from($this->fromEntity)
            ->to($this->toEntity)
            ->type('member_of')
            ->where('active', '=', true);

        $criteria = $builder->getCriteria();

        $this->assertIsArray($criteria);
        // The criteria should contain the underlying adapter query builder's criteria
    }

    public function testCloneMethod(): void
    {
        $originalBuilder = $this->queryBuilder
            ->from($this->fromEntity)
            ->type('member_of');

        $clonedBuilder = clone $originalBuilder;

        // Should be different instances
        $this->assertNotSame($originalBuilder, $clonedBuilder);

        // Modifying clone shouldn't affect original
        $modifiedClone = $clonedBuilder->to($this->toEntity);

        $this->assertNotSame($originalBuilder, $modifiedClone);
        $this->assertNotSame($clonedBuilder, $modifiedClone);
    }

    public function testFromWithStringEntityId(): void
    {
        $builder = $this->queryBuilder->from('user', 'user-123');

        $this->assertInstanceOf(SessionAwareQueryBuilder::class, $builder);
        $this->assertNotSame($this->queryBuilder, $builder);
    }

    public function testToWithStringEntityId(): void
    {
        $builder = $this->queryBuilder->to('organization', 'org-456');

        $this->assertInstanceOf(SessionAwareQueryBuilder::class, $builder);
        $this->assertNotSame($this->queryBuilder, $builder);
    }

    public function testFromWithStringButNoEntityId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Entity ID is required when entity is provided as string');

        $this->queryBuilder->from('user');
    }

    public function testToWithStringButNoEntityId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Entity ID is required when entity is provided as string');

        $this->queryBuilder->to('organization');
    }

    public function testFromWithNullEntityIdAfterExtraction(): void
    {
        // Create a mock entity that returns null for ID extraction
        $mockEntity = new class {
            // This entity has no getId() method, so extraction might return null
        };

        // This should still work, just won't set session criteria
        $builder = $this->queryBuilder->from($mockEntity);

        $this->assertInstanceOf(SessionAwareQueryBuilder::class, $builder);
        $this->assertNotSame($this->queryBuilder, $builder);
    }

    public function testToWithNullEntityIdAfterExtraction(): void
    {
        // Create a mock entity that returns null for ID extraction
        $mockEntity = new class {
            // This entity has no getId() method, so extraction might return null
        };

        // This should still work, just won't set session criteria
        $builder = $this->queryBuilder->to($mockEntity);

        $this->assertInstanceOf(SessionAwareQueryBuilder::class, $builder);
        $this->assertNotSame($this->queryBuilder, $builder);
    }

    public function testExistsMethod(): void
    {
        // Test exists() method which calls count()
        $exists = $this->queryBuilder
            ->from($this->fromEntity)
            ->type('nonexistent_type')
            ->exists();

        $this->assertFalse($exists);

        // Add a binding to cache
        $binding = Binding::create(
            fromType: 'User',
            fromId: 'user-1',
            toType: 'Organization',
            toId: 'org-1',
            type: 'member_of',
            metadata: []
        );
        $this->cache->store($binding);

        $existsWithData = $this->queryBuilder
            ->from($this->fromEntity)
            ->type('member_of')
            ->exists();

        $this->assertTrue($existsWithData);
    }

    public function testCountMethod(): void
    {
        // Test count() method
        $count = $this->queryBuilder
            ->from($this->fromEntity)
            ->type('member_of')
            ->count();

        $this->assertEquals(0, $count);

        // Add bindings to cache
        $binding1 = Binding::create(
            fromType: 'User',
            fromId: 'user-1',
            toType: 'Organization',
            toId: 'org-1',
            type: 'member_of',
            metadata: []
        );
        $binding2 = Binding::create(
            fromType: 'User',
            fromId: 'user-1',
            toType: 'Team',
            toId: 'team-1',
            type: 'member_of',
            metadata: []
        );

        $this->cache->store($binding1);
        $this->cache->store($binding2);

        $countWithData = $this->queryBuilder
            ->from($this->fromEntity)
            ->type('member_of')
            ->count();

        $this->assertEquals(2, $countWithData);
    }
}

/**
 * Test entity for query builder tests.
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

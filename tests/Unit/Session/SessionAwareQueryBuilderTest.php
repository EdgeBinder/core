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

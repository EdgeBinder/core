<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Unit\Session;

use EdgeBinder\Binding;
use EdgeBinder\Contracts\BindingInterface;
use EdgeBinder\Session\BindingCache;
use EdgeBinder\Session\QueryCriteria;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the BindingCache component of the session system.
 *
 * These tests validate the in-memory caching and indexing functionality
 * that provides immediate read-after-write consistency within sessions.
 */
final class BindingCacheTest extends TestCase
{
    private BindingCache $cache;
    private BindingInterface $binding1;
    private BindingInterface $binding2;
    private BindingInterface $binding3;

    protected function setUp(): void
    {
        $this->cache = new BindingCache();

        // Create test bindings with different entity combinations
        $this->binding1 = Binding::create(
            fromType: 'User',
            fromId: 'user-1',
            toType: 'Organization',
            toId: 'org-1',
            type: 'member_of',
            metadata: ['role' => 'admin']
        );

        $this->binding2 = Binding::create(
            fromType: 'User',
            fromId: 'user-1',
            toType: 'Team',
            toId: 'team-1',
            type: 'member_of',
            metadata: ['role' => 'developer']
        );

        $this->binding3 = Binding::create(
            fromType: 'User',
            fromId: 'user-2',
            toType: 'Organization',
            toId: 'org-1',
            type: 'admin_of',
            metadata: ['permissions' => ['read', 'write']]
        );
    }

    public function testStoreAndRetrieveBinding(): void
    {
        $this->cache->store($this->binding1);

        $retrieved = $this->cache->findById($this->binding1->getId());
        $this->assertSame($this->binding1, $retrieved);
    }

    public function testFindByFromEntity(): void
    {
        $this->cache->store($this->binding1);
        $this->cache->store($this->binding2);
        $this->cache->store($this->binding3);

        $results = $this->cache->findByFrom('user-1');

        $this->assertCount(2, $results);
        $this->assertContains($this->binding1, $results);
        $this->assertContains($this->binding2, $results);
        $this->assertNotContains($this->binding3, $results);
    }

    public function testFindByToEntity(): void
    {
        $this->cache->store($this->binding1);
        $this->cache->store($this->binding2);
        $this->cache->store($this->binding3);

        $results = $this->cache->findByTo('org-1');

        $this->assertCount(2, $results);
        $this->assertContains($this->binding1, $results);
        $this->assertContains($this->binding3, $results);
        $this->assertNotContains($this->binding2, $results);
    }

    public function testFindByType(): void
    {
        $this->cache->store($this->binding1);
        $this->cache->store($this->binding2);
        $this->cache->store($this->binding3);

        $results = $this->cache->findByType('member_of');

        $this->assertCount(2, $results);
        $this->assertContains($this->binding1, $results);
        $this->assertContains($this->binding2, $results);
        $this->assertNotContains($this->binding3, $results);
    }

    public function testFindByQueryWithSingleCriteria(): void
    {
        $this->cache->store($this->binding1);
        $this->cache->store($this->binding2);
        $this->cache->store($this->binding3);

        $criteria = new QueryCriteria();
        $criteria->setFrom('user-1');

        $results = $this->cache->findByQuery($criteria);

        $this->assertCount(2, $results);
        $this->assertContains($this->binding1, $results);
        $this->assertContains($this->binding2, $results);
    }

    public function testFindByQueryWithMultipleCriteria(): void
    {
        $this->cache->store($this->binding1);
        $this->cache->store($this->binding2);
        $this->cache->store($this->binding3);

        $criteria = new QueryCriteria();
        $criteria->setFrom('user-1');
        $criteria->setType('member_of');

        $results = $this->cache->findByQuery($criteria);

        $this->assertCount(2, $results);
        $this->assertContains($this->binding1, $results);
        $this->assertContains($this->binding2, $results);
        $this->assertNotContains($this->binding3, $results);
    }

    public function testFindByQueryWithAllCriteria(): void
    {
        $this->cache->store($this->binding1);
        $this->cache->store($this->binding2);
        $this->cache->store($this->binding3);

        $criteria = new QueryCriteria();
        $criteria->setFrom('user-1');
        $criteria->setTo('org-1');
        $criteria->setType('member_of');

        $results = $this->cache->findByQuery($criteria);

        $this->assertCount(1, $results);
        $this->assertContains($this->binding1, $results);
    }

    public function testFindByQueryWithNoMatches(): void
    {
        $this->cache->store($this->binding1);

        $criteria = new QueryCriteria();
        $criteria->setFrom('nonexistent-user');

        $results = $this->cache->findByQuery($criteria);

        $this->assertEmpty($results);
    }

    public function testIndexingPerformance(): void
    {
        // Store many bindings to test indexing performance
        $bindings = [];
        for ($i = 0; $i < 1000; ++$i) {
            $binding = Binding::create(
                fromType: 'User',
                fromId: "user-{$i}",
                toType: 'Organization',
                toId: 'org-1',
                type: 'member_of',
                metadata: []
            );
            $bindings[] = $binding;
            $this->cache->store($binding);
        }

        // Query should be fast due to indexing
        $start = microtime(true);
        $results = $this->cache->findByTo('org-1');
        $end = microtime(true);

        $this->assertCount(1000, $results);
        $this->assertLessThan(0.1, $end - $start, 'Query should be fast with proper indexing');
    }

    public function testCacheSize(): void
    {
        $this->assertEquals(0, $this->cache->size());

        $this->cache->store($this->binding1);
        $this->assertEquals(1, $this->cache->size());

        $this->cache->store($this->binding2);
        $this->assertEquals(2, $this->cache->size());

        // Storing same binding again should not increase size
        $this->cache->store($this->binding1);
        $this->assertEquals(2, $this->cache->size());
    }

    public function testCacheClear(): void
    {
        $this->cache->store($this->binding1);
        $this->cache->store($this->binding2);

        $this->assertEquals(2, $this->cache->size());

        $this->cache->clear();

        $this->assertEquals(0, $this->cache->size());
        $this->assertNull($this->cache->findById($this->binding1->getId()));
        $this->assertEmpty($this->cache->findByFrom('user-1'));
    }

    public function testRemoveBinding(): void
    {
        $this->cache->store($this->binding1);
        $this->cache->store($this->binding2);

        $this->assertEquals(2, $this->cache->size());

        $this->cache->remove($this->binding1->getId());

        $this->assertEquals(1, $this->cache->size());
        $this->assertNull($this->cache->findById($this->binding1->getId()));
        $this->assertNotNull($this->cache->findById($this->binding2->getId()));

        // Indexes should be updated
        $fromResults = $this->cache->findByFrom('user-1');
        $this->assertCount(1, $fromResults);
        $this->assertContains($this->binding2, $fromResults);
    }

    public function testIndexConsistencyAfterRemoval(): void
    {
        $this->cache->store($this->binding1);
        $this->cache->store($this->binding2);
        $this->cache->store($this->binding3);

        // Verify initial state
        $this->assertCount(2, $this->cache->findByFrom('user-1'));
        $this->assertCount(2, $this->cache->findByTo('org-1'));
        $this->assertCount(2, $this->cache->findByType('member_of'));

        // Remove binding1
        $this->cache->remove($this->binding1->getId());

        // Verify indexes are updated correctly
        $this->assertCount(1, $this->cache->findByFrom('user-1'));
        $this->assertCount(1, $this->cache->findByTo('org-1'));
        $this->assertCount(1, $this->cache->findByType('member_of'));

        // Verify correct bindings remain
        $fromResults = $this->cache->findByFrom('user-1');
        $this->assertContains($this->binding2, $fromResults);

        $toResults = $this->cache->findByTo('org-1');
        $this->assertContains($this->binding3, $toResults);

        $typeResults = $this->cache->findByType('member_of');
        $this->assertContains($this->binding2, $typeResults);
    }

    public function testGetAllBindings(): void
    {
        $this->cache->store($this->binding1);
        $this->cache->store($this->binding2);
        $this->cache->store($this->binding3);

        $allBindings = $this->cache->getAll();

        $this->assertCount(3, $allBindings);
        $this->assertContains($this->binding1, $allBindings);
        $this->assertContains($this->binding2, $allBindings);
        $this->assertContains($this->binding3, $allBindings);
    }

    public function testHasBinding(): void
    {
        $this->assertFalse($this->cache->has($this->binding1->getId()));

        $this->cache->store($this->binding1);

        $this->assertTrue($this->cache->has($this->binding1->getId()));
        $this->assertFalse($this->cache->has($this->binding2->getId()));
    }

    public function testMemoryEfficiency(): void
    {
        // Test that the cache doesn't create unnecessary copies
        $this->cache->store($this->binding1);

        $retrieved = $this->cache->findById($this->binding1->getId());
        $this->assertSame($this->binding1, $retrieved, 'Cache should store references, not copies');

        $fromResults = $this->cache->findByFrom('user-1');
        $this->assertSame($this->binding1, $fromResults[0], 'Index results should be references');
    }
}

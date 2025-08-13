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

    public function testRemoveNonExistentBinding(): void
    {
        // Test removing a binding that doesn't exist (should not cause errors)
        $this->cache->remove('nonexistent-binding-id');

        // Cache should remain empty
        $this->assertEquals(0, $this->cache->size());
    }

    public function testRemoveFromIndexEdgeCases(): void
    {
        // Test the removeFromIndex method edge cases
        $this->cache->store($this->binding1);
        $this->cache->store($this->binding2);

        // Remove one binding - index should still contain the other
        $this->cache->remove($this->binding1->getId());

        $fromResults = $this->cache->findByFrom('user-1');
        $this->assertCount(1, $fromResults);
        $this->assertContains($this->binding2, $fromResults);

        // Remove the last binding for this from entity - index entry should be cleaned up
        $this->cache->remove($this->binding2->getId());

        $fromResultsAfter = $this->cache->findByFrom('user-1');
        $this->assertEmpty($fromResultsAfter);
    }

    public function testFindByQueryWithEmptyCriteria(): void
    {
        $this->cache->store($this->binding1);
        $this->cache->store($this->binding2);
        $this->cache->store($this->binding3);

        // Query with no criteria should return all bindings
        $emptyCriteria = new QueryCriteria();
        $results = $this->cache->findByQuery($emptyCriteria);

        $this->assertCount(3, $results);
        $this->assertContains($this->binding1, $results);
        $this->assertContains($this->binding2, $results);
        $this->assertContains($this->binding3, $results);
    }

    public function testFindByQueryWithPartialCriteria(): void
    {
        $this->cache->store($this->binding1);
        $this->cache->store($this->binding2);
        $this->cache->store($this->binding3);

        // Query with only from criteria
        $fromOnlyCriteria = new QueryCriteria();
        $fromOnlyCriteria->setFrom('user-1');
        $fromResults = $this->cache->findByQuery($fromOnlyCriteria);

        $this->assertCount(2, $fromResults);
        $this->assertContains($this->binding1, $fromResults);
        $this->assertContains($this->binding2, $fromResults);

        // Query with only to criteria
        $toOnlyCriteria = new QueryCriteria();
        $toOnlyCriteria->setTo('org-1');
        $toResults = $this->cache->findByQuery($toOnlyCriteria);

        $this->assertCount(2, $toResults);
        $this->assertContains($this->binding1, $toResults);
        $this->assertContains($this->binding3, $toResults);

        // Query with only type criteria
        $typeOnlyCriteria = new QueryCriteria();
        $typeOnlyCriteria->setType('member_of');
        $typeResults = $this->cache->findByQuery($typeOnlyCriteria);

        $this->assertCount(2, $typeResults);
        $this->assertContains($this->binding1, $typeResults);
        $this->assertContains($this->binding2, $typeResults);
    }

    public function testIndexConsistencyWithDuplicateStores(): void
    {
        // Store the same binding multiple times
        $this->cache->store($this->binding1);
        $this->cache->store($this->binding1);
        $this->cache->store($this->binding1);

        // Should only be stored once
        $this->assertEquals(1, $this->cache->size());

        // Indexes should not have duplicates
        $fromResults = $this->cache->findByFrom('user-1');
        $this->assertCount(1, $fromResults);

        $toResults = $this->cache->findByTo('org-1');
        $this->assertCount(1, $toResults);

        $typeResults = $this->cache->findByType('member_of');
        $this->assertCount(1, $typeResults);
    }

    public function testRemoveFromIndexWithNonExistentKey(): void
    {
        // This test exercises the edge case in removeFromIndex where the key doesn't exist
        // We can't directly test the private method, but we can test the scenario

        // Store a binding
        $this->cache->store($this->binding1);

        // Create a binding with a different from entity that shares the same to entity
        $binding4 = Binding::create(
            fromType: 'User',
            fromId: 'user-different',
            toType: 'Organization',
            toId: 'org-1',
            type: 'member_of',
            metadata: []
        );
        $this->cache->store($binding4);

        // Remove binding1 - this should exercise removeFromIndex with existing keys
        $this->cache->remove($this->binding1->getId());

        // Verify the other binding is still there and indexes are correct
        $this->assertTrue($this->cache->has($binding4->getId()));
        $this->assertFalse($this->cache->has($this->binding1->getId()));

        // The to index should still have the other binding
        $toResults = $this->cache->findByTo('org-1');
        $this->assertCount(1, $toResults);
        $this->assertContains($binding4, $toResults);
    }

    public function testFindByNonExistentKeys(): void
    {
        // Test finding by keys that don't exist in indexes
        $this->cache->store($this->binding1);

        // These should return empty arrays, not cause errors
        $fromResults = $this->cache->findByFrom('nonexistent-user');
        $this->assertEmpty($fromResults);

        $toResults = $this->cache->findByTo('nonexistent-org');
        $this->assertEmpty($toResults);

        $typeResults = $this->cache->findByType('nonexistent-type');
        $this->assertEmpty($typeResults);
    }

    public function testFindByIdWithNonExistentId(): void
    {
        $this->cache->store($this->binding1);

        $result = $this->cache->findById('nonexistent-id');
        $this->assertNull($result);
    }

    public function testRemoveFromIndexWithCorruptedState(): void
    {
        // This test exercises the edge case where removeFromIndex is called
        // with a key that doesn't exist in the index (line 203-204 in removeFromIndex)

        // Store a binding normally
        $this->cache->store($this->binding1);
        $this->assertEquals(1, $this->cache->size());

        // Manually create a binding that bypasses normal indexing to simulate corrupted state
        $corruptedBinding = Binding::create(
            fromType: 'User',
            fromId: 'corrupted-user',
            toType: 'Organization',
            toId: 'corrupted-org',
            type: 'corrupted_type',
            metadata: []
        );

        // Manually add to main storage without updating indexes (simulating corruption)
        $reflection = new \ReflectionClass($this->cache);
        $bindingsProperty = $reflection->getProperty('bindings');
        $bindingsProperty->setAccessible(true);
        $bindings = $bindingsProperty->getValue($this->cache);
        $bindings[$corruptedBinding->getId()] = $corruptedBinding;
        $bindingsProperty->setValue($this->cache, $bindings);

        // Now remove this corrupted binding - this should exercise the early return
        // in removeFromIndex when the index key doesn't exist
        $this->cache->remove($corruptedBinding->getId());

        // Cache should still work normally
        $this->assertEquals(1, $this->cache->size());
        $this->assertTrue($this->cache->has($this->binding1->getId()));
        $this->assertFalse($this->cache->has($corruptedBinding->getId()));
    }
}

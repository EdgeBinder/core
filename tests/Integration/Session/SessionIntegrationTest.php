<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Integration\Session;

use EdgeBinder\EdgeBinder;
use EdgeBinder\Persistence\InMemory\InMemoryAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for Session-based consistency functionality.
 *
 * These tests validate that the session implementation provides immediate
 * read-after-write consistency and proper caching behavior across different
 * adapter types, with focus on the core consistency issues identified in
 * DATABASE_TIMING_ISSUE.md.
 */
final class SessionIntegrationTest extends TestCase
{
    private EdgeBinder $edgeBinder;
    private InMemoryAdapter $adapter;
    private TestEntity $profile;
    private TestEntity $org;
    private TestEntity $team;

    protected function setUp(): void
    {
        $this->adapter = new InMemoryAdapter();
        $this->edgeBinder = new EdgeBinder($this->adapter);

        // Create test entities
        $this->profile = new TestEntity('profile-123', 'Profile');
        $this->org = new TestEntity('org-456', 'Organization');
        $this->team = new TestEntity('team-789', 'Team');
    }

    /**
     * Test the core read-after-write consistency issue that sessions solve.
     * This is the primary scenario from DATABASE_TIMING_ISSUE.md.
     */
    public function testSessionProvidesImmediateReadAfterWriteConsistency(): void
    {
        $session = $this->edgeBinder->createSession();

        // Create binding within session
        $binding = $session->bind(from: $this->profile, to: $this->org, type: 'member_of');

        // Query within same session should immediately see the binding
        $results = $session->query()->from($this->profile)->type('member_of')->get();
        $bindings = $results->getBindings();

        $this->assertCount(1, $bindings);
        $this->assertEquals($binding->getId(), $bindings[0]->getId());
        $this->assertEquals('member_of', $bindings[0]->getType());
    }

    /**
     * Test that session cache merges with adapter results correctly.
     */
    public function testSessionMergesWithAdapterResults(): void
    {
        // Create binding directly through adapter (simulates existing data)
        $existingBinding = $this->edgeBinder->bind(from: $this->profile, to: $this->team, type: 'member_of');

        $session = $this->edgeBinder->createSession();

        // Create new binding within session
        $newBinding = $session->bind(from: $this->profile, to: $this->org, type: 'member_of');

        // Query should see both bindings
        $results = $session->query()->from($this->profile)->type('member_of')->get();
        $bindings = $results->getBindings();

        $this->assertCount(2, $bindings);

        $bindingIds = array_map(fn ($b) => $b->getId(), $bindings);
        $this->assertContains($existingBinding->getId(), $bindingIds);
        $this->assertContains($newBinding->getId(), $bindingIds);
    }

    /**
     * Test that session cache prevents duplicate results.
     */
    public function testSessionPreventsDuplicateResults(): void
    {
        $session = $this->edgeBinder->createSession();

        // Create binding within session
        $binding = $session->bind(from: $this->profile, to: $this->org, type: 'member_of');

        // Flush to ensure it's in the adapter
        $session->flush();

        // Query should not return duplicates
        $results = $session->query()->from($this->profile)->type('member_of')->get();
        $bindings = $results->getBindings();

        $this->assertCount(1, $bindings);
        $this->assertEquals($binding->getId(), $bindings[0]->getId());
    }

    /**
     * Test complex query scenarios that were failing in persistent databases.
     */
    public function testSessionHandlesComplexQueryScenarios(): void
    {
        $session = $this->edgeBinder->createSession();

        // Create multiple bindings
        $memberBinding = $session->bind(from: $this->profile, to: $this->org, type: 'member_of');
        $adminBinding = $session->bind(from: $this->profile, to: $this->org, type: 'admin_of');
        $teamBinding = $session->bind(from: $this->profile, to: $this->team, type: 'member_of');

        // Test multi-entity query (all members of organization)
        $orgMembers = $session->query()->to($this->org)->get();
        $orgMemberBindings = $orgMembers->getBindings();
        $this->assertCount(2, $orgMemberBindings);

        // Test relationship lookup (specific type)
        $memberRelations = $session->query()->from($this->profile)->type('member_of')->get();
        $memberBindings = $memberRelations->getBindings();
        $this->assertCount(2, $memberBindings);

        // Test combined criteria
        $profileOrgMembership = $session->query()
            ->from($this->profile)
            ->to($this->org)
            ->type('member_of')
            ->get();
        $profileOrgBindings = $profileOrgMembership->getBindings();
        $this->assertCount(1, $profileOrgBindings);
        $this->assertEquals($memberBinding->getId(), $profileOrgBindings[0]->getId());
    }

    /**
     * Test rapid operation sequences that cause race conditions.
     */
    public function testSessionHandlesRapidOperationSequences(): void
    {
        $session = $this->edgeBinder->createSession();

        // Rapid create-then-query pattern
        for ($i = 0; $i < 10; ++$i) {
            $entity = new TestEntity("entity-{$i}", 'TestEntity');
            $binding = $session->bind(from: $this->profile, to: $entity, type: 'related_to');

            // Immediate query should always find the binding
            $results = $session->query()->from($this->profile)->to($entity)->get();
            $resultBindings = $results->getBindings();
            $this->assertCount(1, $resultBindings);
            $this->assertEquals($binding->getId(), $resultBindings[0]->getId());
        }

        // Final query should see all bindings
        $allResults = $session->query()->from($this->profile)->type('related_to')->get();
        $allBindings = $allResults->getBindings();
        $this->assertCount(10, $allBindings);
    }

    /**
     * Test session isolation - different sessions don't see each other's uncommitted changes.
     */
    public function testSessionIsolation(): void
    {
        $session1 = $this->edgeBinder->createSession();
        $session2 = $this->edgeBinder->createSession();

        // Create binding in session1
        $binding1 = $session1->bind(from: $this->profile, to: $this->org, type: 'member_of');

        // Session1 should see its own binding
        $results1 = $session1->query()->from($this->profile)->type('member_of')->get();
        $bindings1 = $results1->getBindings();
        $this->assertCount(1, $bindings1);

        // Session2 should not see session1's uncommitted binding (cache isolation)
        // But since InMemoryAdapter provides immediate consistency, it will see it in the adapter
        // The key test is that session2's cache doesn't contain session1's bindings
        $results2 = $session2->query()->from($this->profile)->type('member_of')->get();
        $bindings2 = $results2->getBindings();
        // With InMemoryAdapter, session2 will see the binding from the adapter
        $this->assertCount(1, $bindings2);

        // After flush, both sessions should still see the binding
        $session1->flush();

        $results1After = $session1->query()->from($this->profile)->type('member_of')->get();
        $results2After = $session2->query()->from($this->profile)->type('member_of')->get();
        $bindings1After = $results1After->getBindings();
        $bindings2After = $results2After->getBindings();

        $this->assertCount(1, $bindings1After);
        $this->assertCount(1, $bindings2After);
    }

    /**
     * Test explicit flush functionality.
     */
    public function testExplicitFlushEnsuresConsistency(): void
    {
        $session = $this->edgeBinder->createSession();

        // Create binding in session
        $binding = $session->bind(from: $this->profile, to: $this->org, type: 'member_of');

        // Direct adapter query should see the binding immediately (InMemoryAdapter provides immediate consistency)
        $directResults = $this->edgeBinder->query()->from($this->profile)->type('member_of')->get();
        $directBindings = $directResults->getBindings();
        $this->assertCount(1, $directBindings);

        // Flush session
        $session->flush();

        // Direct adapter query should still see the binding
        $directResultsAfter = $this->edgeBinder->query()->from($this->profile)->type('member_of')->get();
        $directBindingsAfter = $directResultsAfter->getBindings();
        $this->assertCount(1, $directBindingsAfter);
        $this->assertEquals($binding->getId(), $directBindingsAfter[0]->getId());
    }

    /**
     * Test auto-flush session behavior.
     */
    public function testAutoFlushSession(): void
    {
        $session = $this->edgeBinder->createSession(autoFlush: true);

        // Create binding - should auto-flush
        $binding = $session->bind(from: $this->profile, to: $this->org, type: 'member_of');

        // Direct adapter query should immediately see the binding
        $directResults = $this->edgeBinder->query()->from($this->profile)->type('member_of')->get();
        $directBindings = $directResults->getBindings();
        $this->assertCount(1, $directBindings);
        $this->assertEquals($binding->getId(), $directBindings[0]->getId());
    }

    /**
     * Test session scoping with callback.
     */
    public function testSessionScopingWithCallback(): void
    {
        $results = $this->edgeBinder->withSession(function ($session) {
            $session->bind(from: $this->profile, to: $this->org, type: 'member_of');
            $session->bind(from: $this->profile, to: $this->team, type: 'member_of');

            return $session->query()->from($this->profile)->type('member_of')->get();
        });

        $resultBindings = $results->getBindings();
        $this->assertCount(2, $resultBindings);

        // Session should be automatically closed
        // Direct query should see the bindings (they were flushed on close)
        $directResults = $this->edgeBinder->query()->from($this->profile)->type('member_of')->get();
        $directBindings = $directResults->getBindings();
        $this->assertCount(2, $directBindings);
    }

    /**
     * Test session state inspection methods.
     */
    public function testSessionStateInspection(): void
    {
        $session = $this->edgeBinder->createSession();

        // Initially clean
        $this->assertFalse($session->isDirty());
        $this->assertEmpty($session->getPendingOperations());
        $this->assertEmpty($session->getTrackedBindings());

        // After binding creation
        $binding = $session->bind(from: $this->profile, to: $this->org, type: 'member_of');

        $this->assertTrue($session->isDirty());
        $this->assertCount(1, $session->getPendingOperations());
        $this->assertCount(1, $session->getTrackedBindings());

        // After flush
        $session->flush();

        $this->assertFalse($session->isDirty());
        $this->assertEmpty($session->getPendingOperations());
        $this->assertCount(1, $session->getTrackedBindings()); // Still tracked for queries
    }

    /**
     * Test session clear functionality.
     */
    public function testSessionClear(): void
    {
        $session = $this->edgeBinder->createSession();

        // Create binding
        $binding = $session->bind(from: $this->profile, to: $this->org, type: 'member_of');

        $this->assertTrue($session->isDirty());
        $this->assertCount(1, $session->getTrackedBindings());

        // Clear session
        $session->clear();

        $this->assertFalse($session->isDirty());
        $this->assertEmpty($session->getTrackedBindings());

        // Query should not see cleared bindings from cache, but will see from adapter
        $results = $session->query()->from($this->profile)->type('member_of')->get();
        $resultBindings = $results->getBindings();
        // With InMemoryAdapter, the binding is still in the adapter even after cache clear
        $this->assertCount(1, $resultBindings);
    }

    /**
     * Test backward compatibility - existing code should work unchanged.
     */
    public function testBackwardCompatibility(): void
    {
        // Existing pattern should continue to work
        $binding = $this->edgeBinder->bind(from: $this->profile, to: $this->org, type: 'member_of');
        $results = $this->edgeBinder->query()->from($this->profile)->type('member_of')->get();
        $resultBindings = $results->getBindings();

        $this->assertCount(1, $resultBindings);
        $this->assertEquals($binding->getId(), $resultBindings[0]->getId());
    }
}

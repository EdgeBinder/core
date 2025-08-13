<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Integration\Session;

use EdgeBinder\EdgeBinder;
use EdgeBinder\Tests\Adapters\DelayedConsistencyAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Tests that validate sessions actually solve database timing issues.
 *
 * Uses DelayedConsistencyAdapter to simulate real persistent database behavior
 * and verify that sessions provide the consistency guarantees needed.
 */
class DatabaseTimingValidationTest extends TestCase
{
    private DelayedConsistencyAdapter $delayedAdapter;
    private EdgeBinder $edgeBinder;
    private TestEntity $profile;
    private TestEntity $org;

    protected function setUp(): void
    {
        // Create adapter that simulates 100ms indexing delay (like real persistent DBs)
        $this->delayedAdapter = new DelayedConsistencyAdapter(
            consistencyDelayMs: 100
        );

        $this->edgeBinder = new EdgeBinder($this->delayedAdapter);

        $this->profile = new TestEntity('profile-123', 'Profile');
        $this->org = new TestEntity('org-456', 'Organization');
    }

    /**
     * Test that demonstrates the database timing problem without sessions.
     * This test should FAIL to show the problem exists.
     */
    public function testDatabaseTimingProblemWithoutSessions(): void
    {
        // The problematic pattern from DATABASE_TIMING_ISSUE.md
        $binding = $this->edgeBinder->bind(from: $this->profile, to: $this->org, type: 'member_of');

        // Immediate query - this should fail with persistent databases due to indexing delay
        $result = $this->edgeBinder->query()->from($this->profile)->type('member_of')->get();
        $bindings = $result->getBindings();

        // This assertion should FAIL with DelayedConsistencyAdapter (demonstrating the problem)
        $this->assertCount(0, $bindings, 'Persistent databases have indexing delays - binding not immediately queryable');

        // Verify the binding exists but isn't queryable yet
        $this->assertFalse($this->delayedAdapter->isBindingQueryable($binding->getId()));
        $this->assertEquals(1, $this->delayedAdapter->getPendingIndexingCount());
    }

    /**
     * Test that sessions solve the database timing problem.
     * This test should PASS to show sessions provide the solution.
     */
    public function testSessionsSolveDatabaseTimingProblem(): void
    {
        $session = $this->edgeBinder->createSession();

        // Same problematic pattern, but with sessions
        $binding = $session->bind(from: $this->profile, to: $this->org, type: 'member_of');

        // Immediate query - this should WORK with sessions despite adapter timing issues
        $result = $session->query()->from($this->profile)->type('member_of')->get();
        $bindings = $result->getBindings();

        // This assertion should PASS - sessions provide immediate consistency
        $this->assertCount(1, $bindings, 'Sessions provide immediate consistency despite database timing issues');
        $this->assertEquals($binding->getId(), $bindings[0]->getId());

        // Verify the underlying adapter still has timing issues
        $this->assertFalse($this->delayedAdapter->isBindingQueryable($binding->getId()));
        $this->assertEquals(1, $this->delayedAdapter->getPendingIndexingCount());
    }

    /**
     * Test the exact scenario from DATABASE_TIMING_ISSUE.md.
     */
    public function testIsOrganizationMemberScenario(): void
    {
        $session = $this->edgeBinder->createSession();

        // Create membership
        $session->bind(from: $this->profile, to: $this->org, type: 'member_of');

        // The problematic check that was failing
        $memberships = $session->query()
            ->from($this->profile)
            ->to($this->org)
            ->type('member_of')
            ->get();

        $isOrganizationMember = count($memberships->getBindings()) > 0;

        // This should work with sessions
        $this->assertTrue($isOrganizationMember, 'Session should solve the isOrganizationMember timing issue');
    }

    /**
     * Test complex query scenarios that fail with persistent databases.
     */
    public function testComplexQueryScenariosWithSessions(): void
    {
        $session = $this->edgeBinder->createSession();

        $team = new TestEntity('team-789', 'Team');
        $project = new TestEntity('project-101', 'Project');

        // Create multiple relationships rapidly
        $session->bind(from: $this->profile, to: $this->org, type: 'member_of');
        $session->bind(from: $this->profile, to: $team, type: 'member_of');
        $session->bind(from: $this->profile, to: $project, type: 'works_on');

        // Complex queries that would fail with persistent DB timing
        $allRelationships = $session->query()->from($this->profile)->get();
        $membershipRelationships = $session->query()->from($this->profile)->type('member_of')->get();
        $orgMembers = $session->query()->to($this->org)->type('member_of')->get();

        // All should work immediately with sessions
        $this->assertCount(3, $allRelationships->getBindings());
        $this->assertCount(2, $membershipRelationships->getBindings());
        $this->assertCount(1, $orgMembers->getBindings());
    }

    /**
     * Test that demonstrates eventual consistency - after waiting, direct queries work.
     */
    public function testEventualConsistencyAfterDelay(): void
    {
        // Create binding with direct EdgeBinder (no session)
        $binding = $this->edgeBinder->bind(from: $this->profile, to: $this->org, type: 'member_of');

        // Immediate query fails (timing issue)
        $immediateResult = $this->edgeBinder->query()->from($this->profile)->type('member_of')->get();
        $this->assertCount(0, $immediateResult->getBindings(), 'Immediate query should fail due to indexing delay');

        // Wait for consistency delay to pass
        usleep(150 * 1000); // 150ms > 100ms consistency delay

        // Now query should work (eventual consistency)
        $delayedResult = $this->edgeBinder->query()->from($this->profile)->type('member_of')->get();
        $this->assertCount(1, $delayedResult->getBindings(), 'Query should work after indexing delay');
        $this->assertEquals($binding->getId(), $delayedResult->getBindings()[0]->getId());
    }

    /**
     * Test that force consistency works (simulates database refresh/sync).
     */
    public function testForceConsistency(): void
    {
        // Create binding
        $binding = $this->edgeBinder->bind(from: $this->profile, to: $this->org, type: 'member_of');

        // Should not be queryable immediately
        $result = $this->edgeBinder->query()->from($this->profile)->type('member_of')->get();
        $this->assertCount(0, $result->getBindings());

        // Force consistency (like database refresh)
        $this->delayedAdapter->forceConsistency();

        // Now should be queryable
        $resultAfter = $this->edgeBinder->query()->from($this->profile)->type('member_of')->get();
        $this->assertCount(1, $resultAfter->getBindings());
        $this->assertEquals($binding->getId(), $resultAfter->getBindings()[0]->getId());
    }

    /**
     * Test rapid operation sequences that are problematic for persistent databases.
     */
    public function testRapidOperationSequencesWithSessions(): void
    {
        $session = $this->edgeBinder->createSession();

        // Rapid create-then-query pattern (problematic for persistent DBs)
        for ($i = 0; $i < 10; ++$i) {
            $entity = new TestEntity("entity-{$i}", 'Entity');

            // Bind and immediately query (the problematic pattern)
            $binding = $session->bind(from: $this->profile, to: $entity, type: 'related_to');
            $result = $session->query()->from($this->profile)->to($entity)->type('related_to')->get();

            // Should work immediately with sessions
            $this->assertCount(1, $result->getBindings(), "Rapid operation {$i} should work with sessions");
            $this->assertEquals($binding->getId(), $result->getBindings()[0]->getId());
        }

        // Final verification - all 10 bindings should be queryable
        $allResults = $session->query()->from($this->profile)->type('related_to')->get();
        $this->assertCount(10, $allResults->getBindings());
    }
}

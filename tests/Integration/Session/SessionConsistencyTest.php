<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Integration\Session;

use EdgeBinder\Contracts\EntityInterface;
use EdgeBinder\EdgeBinder;
use EdgeBinder\Persistence\InMemory\InMemoryAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Consistency tests for Session functionality.
 *
 * These tests specifically validate the consistency guarantees that sessions
 * provide, focusing on the scenarios that were failing in DATABASE_TIMING_ISSUE.md.
 */
final class SessionConsistencyTest extends TestCase
{
    private EdgeBinder $edgeBinder;
    private InMemoryAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new InMemoryAdapter();
        $this->edgeBinder = new EdgeBinder($this->adapter);
    }

    /**
     * Test the exact scenario from DATABASE_TIMING_ISSUE.md.
     * This is the core problem that sessions solve.
     */
    public function testDatabaseTimingIssueScenario(): void
    {
        $profile = new TestEntity('profile-123', 'Profile');
        $org = new TestEntity('org-456', 'Organization');

        $session = $this->edgeBinder->createSession();

        // The problematic pattern from the issue report
        $binding = $session->bind(from: $profile, to: $org, type: 'member_of');
        $result = $session->query()->from($profile)->type('member_of')->get();
        $bindings = $result->getBindings();

        // This should work with sessions (was failing with persistent DBs)
        $this->assertCount(1, $bindings);
        $this->assertEquals($binding->getId(), $bindings[0]->getId());
        $this->assertEquals('member_of', $bindings[0]->getType());
    }

    /**
     * Test immediate query consistency across multiple operations.
     */
    public function testImmediateQueryConsistency(): void
    {
        $session = $this->edgeBinder->createSession();

        $profile = new TestEntity('profile-123', 'Profile');
        $org = new TestEntity('org-456', 'Organization');
        $team = new TestEntity('team-789', 'Team');

        // Rapid sequence of bind-then-query operations
        $binding1 = $session->bind(from: $profile, to: $org, type: 'member_of');
        $result1 = $session->query()->from($profile)->type('member_of')->get();
        $bindings1 = $result1->getBindings();
        $this->assertCount(1, $bindings1);

        $binding2 = $session->bind(from: $profile, to: $team, type: 'member_of');
        $result2 = $session->query()->from($profile)->type('member_of')->get();
        $bindings2 = $result2->getBindings();
        $this->assertCount(2, $bindings2);

        $binding3 = $session->bind(from: $profile, to: $org, type: 'admin_of');
        $result3 = $session->query()->from($profile)->get();
        $bindings3 = $result3->getBindings();
        $this->assertCount(3, $bindings3);

        // Each query should immediately see all previous bindings
        $bindingIds = array_map(fn ($b) => $b->getId(), $bindings3);
        $this->assertContains($binding1->getId(), $bindingIds);
        $this->assertContains($binding2->getId(), $bindingIds);
        $this->assertContains($binding3->getId(), $bindingIds);
    }

    /**
     * Test complex query scenarios that were failing.
     */
    public function testComplexQueryScenarios(): void
    {
        $session = $this->edgeBinder->createSession();

        // Create test entities
        $users = [];
        $orgs = [];

        for ($i = 1; $i <= 5; ++$i) {
            $users[] = new TestEntity("user-{$i}", 'User');
            $orgs[] = new TestEntity("org-{$i}", 'Organization');
        }

        // Create various relationships
        foreach ($users as $index => $user) {
            $session->bind(from: $user, to: $orgs[0], type: 'member_of'); // All users in org-1
            if ($index < 2) {
                $session->bind(from: $user, to: $orgs[0], type: 'admin_of'); // First 2 users are admins
            }
        }

        // Multi-entity queries (finding all members of an organization)
        $allMembers = $session->query()->to($orgs[0])->type('member_of')->get();
        $this->assertCount(5, $allMembers);

        // Relationship lookups (checking if specific relationships exist)
        $adminRelations = $session->query()->to($orgs[0])->type('admin_of')->get();
        $this->assertCount(2, $adminRelations);

        // Combined criteria queries (multiple from/to/type filters)
        $userAdminRelation = $session->query()
            ->from($users[0])
            ->to($orgs[0])
            ->type('admin_of')
            ->get();
        $this->assertCount(1, $userAdminRelation);

        // Verify specific user is admin
        $isAdmin = $session->query()
            ->from($users[0])
            ->to($orgs[0])
            ->type('admin_of')
            ->first();
        $this->assertNotNull($isAdmin);

        // Verify non-admin user
        $isNotAdmin = $session->query()
            ->from($users[4])
            ->to($orgs[0])
            ->type('admin_of')
            ->first();
        $this->assertNull($isNotAdmin);
    }

    /**
     * Test rapid operation sequences that cause race conditions.
     */
    public function testRapidOperationSequences(): void
    {
        $session = $this->edgeBinder->createSession();

        $profile = new TestEntity('profile-123', 'Profile');

        // Simulate rapid create-then-query patterns
        $bindings = [];
        for ($i = 0; $i < 20; ++$i) {
            $entity = new TestEntity("entity-{$i}", 'TestEntity');

            // Create binding
            $binding = $session->bind(from: $profile, to: $entity, type: 'related_to');
            $bindings[] = $binding;

            // Immediately query - should always find the binding
            $result = $session->query()->from($profile)->to($entity)->type('related_to')->first();
            $this->assertNotNull($result);
            $this->assertEquals($binding->getId(), $result->getId());

            // Query all relationships so far
            $allResults = $session->query()->from($profile)->type('related_to')->get();
            $this->assertCount($i + 1, $allResults);
        }

        // Final verification - all bindings should be queryable
        $finalResults = $session->query()->from($profile)->type('related_to')->get();
        $finalBindings = $finalResults->getBindings();
        $this->assertCount(20, $finalBindings);

        $finalIds = array_map(fn ($b) => $b->getId(), $finalBindings);
        foreach ($bindings as $binding) {
            $this->assertContains($binding->getId(), $finalIds);
        }
    }

    /**
     * Test sequential operations in test-like scenarios.
     */
    public function testSequentialOperationsTestIsolation(): void
    {
        // Simulate multiple test scenarios in sequence
        $testScenarios = ['scenario1', 'scenario2', 'scenario3'];

        foreach ($testScenarios as $name) {
            $session = $this->edgeBinder->createSession();
            $results = $this->runTestScenario($name, $session);

            // Each scenario should see its own data immediately
            $resultBindings = $results->getBindings();
            $this->assertGreaterThan(0, count($resultBindings), "Scenario {$name} should have results");

            // Verify isolation - other scenarios' data should not interfere
            foreach ($resultBindings as $result) {
                $this->assertStringContainsString($name, $result->getFromId());
            }

            $session->close();
        }
    }

    /**
     * Test that sessions handle the "isOrganizationMember" pattern from the issue.
     */
    public function testIsOrganizationMemberPattern(): void
    {
        $session = $this->edgeBinder->createSession();

        $profile = new TestEntity('profile-123', 'Profile');
        $org = new TestEntity('org-456', 'Organization');

        // Initially not a member
        $isMemberBefore = $this->isOrganizationMember($session, $profile, $org);
        $this->assertFalse($isMemberBefore);

        // Create membership
        $binding = $session->bind(from: $profile, to: $org, type: 'member_of');

        // Should immediately be detectable as member
        $isMemberAfter = $this->isOrganizationMember($session, $profile, $org);
        $this->assertTrue($isMemberAfter);

        // Verify the binding exists
        $membershipBinding = $session->query()
            ->from($profile)
            ->to($org)
            ->type('member_of')
            ->first();

        $this->assertNotNull($membershipBinding);
        $this->assertEquals($binding->getId(), $membershipBinding->getId());
    }

    /**
     * Test consistency across session flush operations.
     */
    public function testConsistencyAcrossFlushOperations(): void
    {
        $session = $this->edgeBinder->createSession();

        $profile = new TestEntity('profile-123', 'Profile');
        $org = new TestEntity('org-456', 'Organization');

        // Create binding in session
        $binding = $session->bind(from: $profile, to: $org, type: 'member_of');

        // Session should see it
        $sessionResult = $session->query()->from($profile)->type('member_of')->get();
        $sessionBindings = $sessionResult->getBindings();
        $this->assertCount(1, $sessionBindings);

        // Direct EdgeBinder should see it immediately (InMemoryAdapter provides immediate consistency)
        $directResult = $this->edgeBinder->query()->from($profile)->type('member_of')->get();
        $directBindings = $directResult->getBindings();
        $this->assertCount(1, $directBindings);

        // Flush session
        $session->flush();

        // Both should still see it
        $sessionResultAfter = $session->query()->from($profile)->type('member_of')->get();
        $directResultAfter = $this->edgeBinder->query()->from($profile)->type('member_of')->get();
        $sessionBindingsAfter = $sessionResultAfter->getBindings();
        $directBindingsAfter = $directResultAfter->getBindings();

        $this->assertCount(1, $sessionBindingsAfter);
        $this->assertCount(1, $directBindingsAfter);
        $this->assertEquals($binding->getId(), $sessionBindingsAfter[0]->getId());
        $this->assertEquals($binding->getId(), $directBindingsAfter[0]->getId());
    }

    /**
     * Test auto-flush consistency behavior.
     */
    public function testAutoFlushConsistency(): void
    {
        $session = $this->edgeBinder->createSession(autoFlush: true);

        $profile = new TestEntity('profile-123', 'Profile');
        $org = new TestEntity('org-456', 'Organization');

        // Create binding with auto-flush
        $binding = $session->bind(from: $profile, to: $org, type: 'member_of');

        // Both session and direct queries should immediately see it
        $sessionResult = $session->query()->from($profile)->type('member_of')->get();
        $directResult = $this->edgeBinder->query()->from($profile)->type('member_of')->get();
        $sessionBindings = $sessionResult->getBindings();
        $directBindings = $directResult->getBindings();

        $this->assertCount(1, $sessionBindings);
        $this->assertCount(1, $directBindings);
        $this->assertEquals($binding->getId(), $sessionBindings[0]->getId());
        $this->assertEquals($binding->getId(), $directBindings[0]->getId());
    }

    /**
     * Helper method to simulate the isOrganizationMember check from the issue.
     */
    private function isOrganizationMember(mixed $session, EntityInterface $profile, EntityInterface $org): bool
    {
        $membership = $session->query()
            ->from($profile)
            ->to($org)
            ->type('member_of')
            ->first();

        return null !== $membership;
    }

    /**
     * Helper method to run a test scenario.
     */
    private function runTestScenario(string $scenarioName, mixed $session = null): mixed
    {
        if (null === $session) {
            $session = $this->edgeBinder->createSession();
        }

        $user = new TestEntity("{$scenarioName}-user", 'User');
        $org = new TestEntity("{$scenarioName}-org", 'Organization');

        $session->bind(from: $user, to: $org, type: 'member_of');

        return $session->query()->from($user)->type('member_of')->get();
    }
}

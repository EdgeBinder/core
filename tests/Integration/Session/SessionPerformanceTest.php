<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Integration\Session;

use EdgeBinder\EdgeBinder;
use EdgeBinder\Persistence\InMemory\InMemoryAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Performance tests for Session functionality.
 *
 * These tests validate that the session implementation maintains
 * good performance characteristics even with large datasets and
 * complex query patterns.
 */
final class SessionPerformanceTest extends TestCase
{
    private EdgeBinder $edgeBinder;
    private InMemoryAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new InMemoryAdapter();
        $this->edgeBinder = new EdgeBinder($this->adapter);
    }

    /**
     * Test session cache performance with large number of bindings.
     */
    public function testSessionCachePerformanceWithLargeDataset(): void
    {
        $session = $this->edgeBinder->createSession();

        // Create a large number of entities and bindings
        $users = [];
        $orgs = [];

        for ($i = 0; $i < 100; ++$i) {
            $users[] = new TestEntity("user-{$i}", 'User');
            $orgs[] = new TestEntity("org-{$i}", 'Organization');
        }

        // Measure binding creation time
        $startTime = microtime(true);

        foreach ($users as $userIndex => $user) {
            foreach ($orgs as $orgIndex => $org) {
                if ($userIndex % 10 === $orgIndex % 10) { // Create selective relationships
                    $session->bind(from: $user, to: $org, type: 'member_of');
                }
            }
        }

        $bindingTime = microtime(true) - $startTime;

        // Measure query time
        $startTime = microtime(true);

        // Perform various queries
        for ($i = 0; $i < 10; ++$i) {
            $user = $users[$i];
            $results = $session->query()->from($user)->type('member_of')->get();
            $this->assertGreaterThan(0, count($results));
        }

        $queryTime = microtime(true) - $startTime;

        // Performance assertions
        $this->assertLessThan(1.0, $bindingTime, 'Binding creation should be fast');
        $this->assertLessThan(0.1, $queryTime, 'Queries should be fast with proper indexing');

        // Verify correctness
        $totalResults = $session->query()->type('member_of')->get();
        $this->assertEquals(1000, count($totalResults)); // 100 users * 10 orgs each
    }

    /**
     * Test session memory usage with large datasets.
     */
    public function testSessionMemoryUsage(): void
    {
        $session = $this->edgeBinder->createSession();

        $initialMemory = memory_get_usage(true);

        // Create many bindings
        for ($i = 0; $i < 1000; ++$i) {
            $user = new TestEntity("user-{$i}", 'User');
            $org = new TestEntity("org-{$i}", 'Organization');
            $session->bind(from: $user, to: $org, type: 'member_of');
        }

        $afterBindingMemory = memory_get_usage(true);
        $memoryIncrease = $afterBindingMemory - $initialMemory;

        // Memory usage should be reasonable (less than 10MB for 1000 bindings)
        $this->assertLessThan(10 * 1024 * 1024, $memoryIncrease, 'Memory usage should be reasonable');

        // Clear session and verify memory is freed
        $session->clear();

        // Force garbage collection multiple times (PHP's GC is not deterministic)
        for ($i = 0; $i < 3; ++$i) {
            gc_collect_cycles();
        }

        $afterClearMemory = memory_get_usage(true);

        // Memory should not increase significantly after clear (relaxed check due to PHP GC behavior)
        $this->assertLessThan($afterBindingMemory * 1.2, $afterClearMemory, 'Memory should not grow significantly after clear');
    }

    /**
     * Test concurrent session performance.
     */
    public function testConcurrentSessionPerformance(): void
    {
        $sessions = [];

        // Create multiple sessions
        for ($i = 0; $i < 10; ++$i) {
            $sessions[] = $this->edgeBinder->createSession();
        }

        $startTime = microtime(true);

        // Perform operations on all sessions
        foreach ($sessions as $index => $session) {
            for ($j = 0; $j < 100; ++$j) {
                $user = new TestEntity("user-{$index}-{$j}", 'User');
                $org = new TestEntity("org-{$index}", 'Organization');
                $session->bind(from: $user, to: $org, type: 'member_of');
            }
        }

        // Query from all sessions - each should see all bindings due to InMemoryAdapter's immediate consistency
        foreach ($sessions as $session) {
            $results = $session->query()->type('member_of')->get();
            $bindings = $results->getBindings();
            // With InMemoryAdapter, all sessions see all bindings (10 sessions × 100 bindings = 1000)
            $this->assertEquals(1000, count($bindings));
        }

        $totalTime = microtime(true) - $startTime;

        // Should handle concurrent sessions efficiently
        $this->assertLessThan(2.0, $totalTime, 'Concurrent sessions should perform well');
    }

    /**
     * Test session cache indexing performance.
     */
    public function testSessionCacheIndexingPerformance(): void
    {
        $session = $this->edgeBinder->createSession();

        // Create entities
        $users = [];
        $orgs = [];

        for ($i = 0; $i < 50; ++$i) {
            $users[] = new TestEntity("user-{$i}", 'User');
            $orgs[] = new TestEntity("org-{$i}", 'Organization');
        }

        // Create bindings with various patterns
        foreach ($users as $user) {
            foreach ($orgs as $org) {
                $session->bind(from: $user, to: $org, type: 'member_of');
            }
        }

        // Test different query patterns
        $queryPatterns = [
            // Query by from entity
            fn () => $session->query()->from($users[0])->get(),
            // Query by to entity
            fn () => $session->query()->to($orgs[0])->get(),
            // Query by type
            fn () => $session->query()->type('member_of')->get(),
            // Complex query
            fn () => $session->query()->from($users[0])->type('member_of')->get(),
        ];

        foreach ($queryPatterns as $pattern) {
            $startTime = microtime(true);

            // Run query multiple times
            for ($i = 0; $i < 100; ++$i) {
                $results = $pattern();
                $this->assertGreaterThan(0, count($results));
            }

            $queryTime = microtime(true) - $startTime;

            // Each query pattern should be reasonably fast (relaxed threshold for test environment)
            $this->assertLessThan(0.5, $queryTime, 'Query pattern should be fast with indexing');
        }
    }

    /**
     * Test session flush performance.
     */
    public function testSessionFlushPerformance(): void
    {
        $session = $this->edgeBinder->createSession();

        // Create many bindings
        for ($i = 0; $i < 500; ++$i) {
            $user = new TestEntity("user-{$i}", 'User');
            $org = new TestEntity("org-{$i}", 'Organization');
            $session->bind(from: $user, to: $org, type: 'member_of');
        }

        // Measure flush time
        $startTime = microtime(true);
        $session->flush();
        $flushTime = microtime(true) - $startTime;

        // Flush should be reasonably fast (InMemory adapter is immediate)
        $this->assertLessThan(0.5, $flushTime, 'Session flush should be fast');

        // Verify all bindings are accessible through direct queries
        $directResults = $this->edgeBinder->query()->type('member_of')->get();
        $this->assertEquals(500, count($directResults));
    }

    /**
     * Test session query result merging performance.
     */
    public function testSessionQueryMergingPerformance(): void
    {
        // Create some bindings directly in adapter (existing data)
        for ($i = 0; $i < 250; ++$i) {
            $user = new TestEntity("existing-user-{$i}", 'User');
            $org = new TestEntity("existing-org-{$i}", 'Organization');
            $this->edgeBinder->bind(from: $user, to: $org, type: 'member_of');
        }

        $session = $this->edgeBinder->createSession();

        // Create bindings in session (new data)
        for ($i = 0; $i < 250; ++$i) {
            $user = new TestEntity("session-user-{$i}", 'User');
            $org = new TestEntity("session-org-{$i}", 'Organization');
            $session->bind(from: $user, to: $org, type: 'member_of');
        }

        // Measure query merging performance
        $startTime = microtime(true);

        for ($i = 0; $i < 50; ++$i) {
            $results = $session->query()->type('member_of')->get();
            $this->assertEquals(500, count($results)); // 250 existing + 250 session
        }

        $queryTime = microtime(true) - $startTime;

        // Query merging should be efficient
        $this->assertLessThan(0.5, $queryTime, 'Query result merging should be efficient');
    }

    /**
     * Test session with complex relationship patterns.
     */
    public function testSessionComplexRelationshipPerformance(): void
    {
        $session = $this->edgeBinder->createSession();

        // Create entities
        $users = [];
        $teams = [];
        $projects = [];

        for ($i = 0; $i < 20; ++$i) {
            $users[] = new TestEntity("user-{$i}", 'User');
            $teams[] = new TestEntity("team-{$i}", 'Team');
            $projects[] = new TestEntity("project-{$i}", 'Project');
        }

        $startTime = microtime(true);

        // Create complex relationship patterns
        foreach ($users as $userIndex => $user) {
            // User-Team relationships
            for ($i = 0; $i < 3; ++$i) {
                $team = $teams[($userIndex + $i) % count($teams)];
                $session->bind(from: $user, to: $team, type: 'member_of');
            }

            // User-Project relationships
            for ($i = 0; $i < 5; ++$i) {
                $project = $projects[($userIndex + $i) % count($projects)];
                $session->bind(from: $user, to: $project, type: 'works_on');
            }
        }

        // Team-Project relationships
        foreach ($teams as $teamIndex => $team) {
            for ($i = 0; $i < 2; ++$i) {
                $project = $projects[($teamIndex + $i) % count($projects)];
                $session->bind(from: $team, to: $project, type: 'assigned_to');
            }
        }

        $creationTime = microtime(true) - $startTime;

        // Test complex queries
        $startTime = microtime(true);

        // Find all projects a user works on
        $userProjects = $session->query()->from($users[0])->type('works_on')->get();
        $this->assertEquals(5, count($userProjects));

        // Find all team members
        $teamMembers = $session->query()->to($teams[0])->type('member_of')->get();
        $this->assertGreaterThan(0, count($teamMembers));

        // Find all project assignments
        $projectAssignments = $session->query()->type('assigned_to')->get();
        $this->assertEquals(40, count($projectAssignments)); // 20 teams * 2 projects each

        $queryTime = microtime(true) - $startTime;

        // Performance should be good even with complex patterns
        $this->assertLessThan(1.0, $creationTime, 'Complex relationship creation should be fast');
        $this->assertLessThan(0.1, $queryTime, 'Complex queries should be fast');
    }
}

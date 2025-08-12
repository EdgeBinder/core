<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Integration\Core;

use EdgeBinder\EdgeBinder;
use EdgeBinder\Persistence\InMemory\InMemoryAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Comprehensive core EdgeBinder test suite to catch edge case bugs.
 *
 * This test suite systematically tests all query patterns, relationship types, and
 * entity types to prevent regressions and catch edge case bugs like:
 * - member_of relationship type failing
 * - to() query direction failing for certain entities
 *
 * CRITICAL: These tests should run against InMemory adapter first
 * to validate core EdgeBinder functionality before testing adapters.
 *
 * Expected Results:
 * - These tests should FAIL initially, demonstrating the bugs exist
 * - After core fixes, these tests should PASS
 */
class EdgeBinderCoreQueryPatternTest extends TestCase
{
    private EdgeBinder $edgeBinder;

    protected function setUp(): void
    {
        // Use InMemory adapter for core functionality testing
        $adapter = new InMemoryAdapter();
        $this->edgeBinder = new EdgeBinder($adapter);
    }

    /**
     * Test that ALL relationship types work with ALL query patterns.
     *
     * This test will catch bugs like the 'member_of' relationship type issue
     * where specific types fail while others work.
     *
     * BUG REPRODUCTION: This test should FAIL for 'member_of' type
     */
    public function testAllRelationshipTypesWithAllQueryPatterns(): void
    {
        $relationshipTypes = [
            'owns',
            'member_of',        // ← This type currently fails
            'contains',
            'has_access',
            'manages',
            'authenticates',
            'belongs_to',
            'controls',
        ];

        $entity1 = $this->createTestEntity('test-entity-1', 'Test Entity 1');
        $entity2 = $this->createTestEntity('test-entity-2', 'Test Entity 2');

        $createdBindings = [];

        // Create relationships for all types
        foreach ($relationshipTypes as $type) {
            $binding = $this->edgeBinder->bind(from: $entity1, to: $entity2, type: $type);
            $createdBindings[$type] = $binding;

            $this->assertNotNull($binding, "Should create binding for type '{$type}'");
            $this->assertEquals($type, $binding->getType(), "Binding should have correct type '{$type}'");
        }

        // Test all query patterns for each relationship type
        $failedQueries = [];

        foreach ($relationshipTypes as $type) {
            $binding = $createdBindings[$type];

            $queryPatterns = [
                'from_only' => $this->edgeBinder->query()->from($entity1)->get(),
                'to_only' => $this->edgeBinder->query()->to($entity2)->get(),
                'type_only' => $this->edgeBinder->query()->type($type)->get(),
                'from_and_type' => $this->edgeBinder->query()->from($entity1)->type($type)->get(),
                'to_and_type' => $this->edgeBinder->query()->to($entity2)->type($type)->get(),
                'from_and_to' => $this->edgeBinder->query()->from($entity1)->to($entity2)->get(),
                'from_to_type' => $this->edgeBinder->query()->from($entity1)->to($entity2)->type($type)->get(),
            ];

            foreach ($queryPatterns as $patternName => $result) {
                $bindings = $result->getBindings();
                $found = false;

                foreach ($bindings as $foundBinding) {
                    if ($foundBinding->getId() === $binding->getId()) {
                        $found = true;

                        break;
                    }
                }

                if (!$found) {
                    $failedQueries[] = [
                        'type' => $type,
                        'pattern' => $patternName,
                        'binding_id' => $binding->getId(),
                        'results_count' => count($bindings),
                    ];
                }
            }
        }

        // Report all failures
        if (!empty($failedQueries)) {
            $report = "Query pattern failures detected:\n";
            foreach ($failedQueries as $failure) {
                $report .= "- Type '{$failure['type']}' with pattern '{$failure['pattern']}': ";
                $report .= "expected binding {$failure['binding_id']}, got {$failure['results_count']} results\n";
            }
            $this->fail($report);
        }
    }

    /**
     * Test that ALL entity types work with ALL query directions.
     *
     * This test will catch bugs where specific entity types fail
     * in certain query directions (like the 'to organization' issue).
     *
     * BUG REPRODUCTION: This test should FAIL for 'to()' queries on certain entities
     */
    public function testAllEntityTypesWithAllQueryDirections(): void
    {
        // Create diverse entity types that mirror real-world usage
        $entities = [
            'profile' => $this->createTestEntity('test-profile', 'Test Profile'),
            'organization' => $this->createTestEntity('test-org', 'Test Organization'),
            'workspace' => $this->createTestEntity('test-workspace', 'Test Workspace'),
            'user' => $this->createTestEntity('test-user', 'Test User'),
            'project' => $this->createTestEntity('test-project', 'Test Project'),
        ];

        // Create comprehensive relationship matrix
        $bindings = [];
        foreach ($entities as $fromName => $fromEntity) {
            foreach ($entities as $toName => $toEntity) {
                if ($fromName !== $toName) {
                    $binding = $this->edgeBinder->bind(
                        from: $fromEntity,
                        to: $toEntity,
                        type: "relation_{$fromName}_to_{$toName}"
                    );
                    $bindings[] = [
                        'from_name' => $fromName,
                        'to_name' => $toName,
                        'from_entity' => $fromEntity,
                        'to_entity' => $toEntity,
                        'binding' => $binding,
                    ];
                }
            }
        }

        $this->assertNotEmpty($bindings, 'Should have created test relationships');

        // Test that every entity works in both query directions
        $failedDirections = [];

        foreach ($entities as $entityName => $entity) {
            // Test as 'from' entity
            $fromResults = $this->edgeBinder->query()->from($entity)->get();
            $expectedFromCount = count(array_filter($bindings, fn ($b) => $b['from_entity'] === $entity));

            if (count($fromResults->getBindings()) !== $expectedFromCount) {
                $failedDirections[] = [
                    'entity' => $entityName,
                    'direction' => 'from',
                    'expected' => $expectedFromCount,
                    'actual' => count($fromResults->getBindings()),
                ];
            }

            // Test as 'to' entity
            $toResults = $this->edgeBinder->query()->to($entity)->get();
            $expectedToCount = count(array_filter($bindings, fn ($b) => $b['to_entity'] === $entity));

            if (count($toResults->getBindings()) !== $expectedToCount) {
                $failedDirections[] = [
                    'entity' => $entityName,
                    'direction' => 'to',
                    'expected' => $expectedToCount,
                    'actual' => count($toResults->getBindings()),
                ];
            }
        }

        // Report direction failures
        if (!empty($failedDirections)) {
            $report = "Query direction failures detected:\n";
            foreach ($failedDirections as $failure) {
                $report .= "- Entity '{$failure['entity']}' in '{$failure['direction']}' direction: ";
                $report .= "expected {$failure['expected']} results, got {$failure['actual']}\n";
            }
            $this->fail($report);
        }
    }

    /**
     * Test specific edge cases that have been problematic.
     *
     * BUG REPRODUCTION: These specific cases should FAIL
     */
    public function testSpecificEdgeCaseBugs(): void
    {
        // Test Case 1: member_of relationship type bug
        $profile = $this->createTestEntity('edge-profile', 'Edge Profile');
        $organization = $this->createTestEntity('edge-org', 'Edge Organization');

        $memberBinding = $this->edgeBinder->bind(
            from: $profile,
            to: $organization,
            type: 'member_of',
            metadata: ['role' => 'admin']
        );

        // These queries should work but currently fail
        $memberResult1 = $this->edgeBinder->query()->from($profile)->type('member_of')->get();
        $this->assertNotEmpty(
            $memberResult1->getBindings(),
            "BUG: from() + type('member_of') should return results"
        );

        $memberResult2 = $this->edgeBinder->query()->to($organization)->type('member_of')->get();
        $this->assertNotEmpty(
            $memberResult2->getBindings(),
            "BUG: to() + type('member_of') should return results"
        );

        // Test Case 2: 'to' query direction bug with organization entities
        $ownsBinding = $this->edgeBinder->bind(from: $profile, to: $organization, type: 'owns');

        $toOrgResult = $this->edgeBinder->query()->to($organization)->get();
        $this->assertNotEmpty(
            $toOrgResult->getBindings(),
            'BUG: to() queries should work for organization entities'
        );

        // Verify the specific bindings are found
        $this->assertQueryFindsBinding(
            $memberResult1,
            $memberBinding,
            'member_of query should find the created membership'
        );
        $this->assertQueryFindsBinding(
            $toOrgResult,
            $ownsBinding,
            'to organization query should find the ownership'
        );
    }

    /**
     * Helper method to create consistent test entities.
     */
    private function createTestEntity(string $id, string $name): object
    {
        return new class($id, $name) {
            public function __construct(private string $id, private string $name)
            {
            }

            public function getId(): string
            {
                return $this->id;
            }

            public function getName(): string
            {
                return $this->name;
            }
        };
    }

    /**
     * Helper method to assert that a query result contains a specific binding.
     */
    private function assertQueryFindsBinding(mixed $queryResult, mixed $expectedBinding, string $message = ''): void
    {
        $bindings = $queryResult->getBindings();
        $found = false;

        foreach ($bindings as $binding) {
            if ($binding->getId() === $expectedBinding->getId()) {
                $found = true;

                break;
            }
        }

        $this->assertTrue($found, $message ?: "Query should find binding {$expectedBinding->getId()}");
    }
}

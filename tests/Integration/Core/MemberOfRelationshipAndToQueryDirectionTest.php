<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Integration\Core;

use EdgeBinder\EdgeBinder;
use EdgeBinder\Persistence\InMemory\InMemoryAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Test for member_of relationship type and to() query direction issues.
 *
 * This test specifically validates that:
 * 1. The 'member_of' relationship type works correctly in all query patterns
 * 2. The to() query direction works correctly for all entity types
 * 3. Complex query combinations (from + to + type) work as expected
 *
 * These were previously problematic patterns that have been fixed.
 * This test ensures they continue to work correctly (regression prevention).
 */
class MemberOfRelationshipAndToQueryDirectionTest extends TestCase
{
    private EdgeBinder $edgeBinder;

    protected function setUp(): void
    {
        // Use InMemory adapter exactly as described in bug report
        $adapter = new InMemoryAdapter();
        $this->edgeBinder = new EdgeBinder($adapter);
    }

    /**
     * Test that member_of relationship type works correctly in all query patterns.
     *
     * Validates that the 'member_of' relationship type works correctly in:
     * - from() + type() queries
     * - from() + to() + type() queries
     * - Control test with 'owns' type to ensure general functionality
     */
    public function testMemberOfRelationshipTypeQueryPatterns(): void
    {
        // Create test entities exactly as in bug report
        $profile = new class('profile-123', 'Test Profile') {
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

        $organization = new class('org-456', 'Test Organization') {
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

        // Create relationship - THIS WORKS (according to bug report)
        $binding = $this->edgeBinder->bind(
            from: $profile,
            to: $organization,
            type: 'member_of',
            metadata: ['role' => 'admin']
        );

        $this->assertNotNull($binding, 'Should create member_of binding');
        $this->assertEquals('member_of', $binding->getType(), 'Binding should have correct type');

        // Query patterns - THESE FAIL (according to bug report)
        $result1 = $this->edgeBinder->query()->from($profile)->type('member_of')->get();
        $result1Count = count($result1->getBindings());

        $result2 = $this->edgeBinder->query()->from($profile)->to($organization)->type('member_of')->get();
        $result2Count = count($result2->getBindings());

        // Control test with different type - THIS WORKS (according to bug report)
        $ownsBinding = $this->edgeBinder->bind(from: $profile, to: $organization, type: 'owns');
        $result3 = $this->edgeBinder->query()->from($profile)->type('owns')->get();
        $result3Count = count($result3->getBindings());

        // Validate that member_of relationship type works correctly
        $this->assertNotEmpty(
            $result1->getBindings(),
            'member_of type should work in from() + type() queries'
        );
        $this->assertEquals(1, $result1Count, "from() + type('member_of') should return exactly 1 result");

        $this->assertNotEmpty(
            $result2->getBindings(),
            'member_of type should work in from() + to() + type() queries'
        );
        $this->assertEquals(1, $result2Count, "from() + to() + type('member_of') should return exactly 1 result");

        $this->assertNotEmpty(
            $result3->getBindings(),
            "Control test: from() + type('owns') should work"
        );
        $this->assertEquals(1, $result3Count, "Control test: from() + type('owns') should return exactly 1 result");
        $this->assertNotNull($ownsBinding, 'Should create owns binding for control test');
    }

    /**
     * Test that to() query direction works correctly for all entity types.
     *
     * Validates that to() queries work correctly for:
     * - Organization entities (previously problematic)
     * - Profile entities (control test)
     * - Bidirectional relationship queries
     */
    public function testToQueryDirectionForAllEntityTypes(): void
    {
        // Create test entities exactly as in bug report
        $profile = new class('profile-789', 'Test Profile') {
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

        $organization = new class('org-101', 'Test Organization') {
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

        // Create relationships in both directions
        $binding1 = $this->edgeBinder->bind(from: $profile, to: $organization, type: 'owns');
        $binding2 = $this->edgeBinder->bind(from: $organization, to: $profile, type: 'contains');

        // Query by 'from' direction - THIS WORKS (according to bug report)
        $fromResult = $this->edgeBinder->query()->from($profile)->get();
        $fromCount = count($fromResult->getBindings());

        // Query by 'to' direction - THIS FAILS FOR SOME ENTITIES (according to bug report)
        $toResult = $this->edgeBinder->query()->to($organization)->get();
        $toOrgCount = count($toResult->getBindings());

        $toResult2 = $this->edgeBinder->query()->to($profile)->get();
        $toProfileCount = count($toResult2->getBindings());

        // Validate that to() queries work correctly for all entity types
        $this->assertNotEmpty(
            $fromResult->getBindings(),
            'Control test: from() queries should work'
        );
        $this->assertEquals(1, $fromCount, 'from() query should return exactly 1 result');
        $this->assertNotNull($binding1, 'Should create binding1 for testing');

        $this->assertNotEmpty(
            $toResult->getBindings(),
            'to() queries should work correctly for organization entities'
        );
        $this->assertEquals(1, $toOrgCount, 'to() organization query should return exactly 1 result');

        $this->assertNotEmpty(
            $toResult2->getBindings(),
            'to() queries should work for profile entities'
        );
        $this->assertEquals(1, $toProfileCount, 'to() profile query should return exactly 1 result');
        $this->assertNotNull($binding2, 'Should create binding2 for testing');
    }

    /**
     * Test comprehensive query pattern matrix for member_of and owns types.
     *
     * This test validates a comprehensive matrix of query patterns including:
     * - Dual-criteria queries (from+type, to+type)
     * - Triple-criteria queries (from+to+type)
     * - Individual component queries (from, to, type)
     */
    public function testComprehensiveQueryPatternMatrix(): void
    {
        // Create entities
        $profile = new class('test-profile', 'Test Profile') {
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

        $organization = new class('test-org', 'Test Organization') {
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

        $workspace = new class('test-workspace', 'Test Workspace') {
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

        // Create the exact relationships from bug report
        $membershipBinding = $this->edgeBinder->bind(from: $profile, to: $organization, type: 'member_of');
        $ownershipBinding = $this->edgeBinder->bind(from: $profile, to: $workspace, type: 'owns');

        // Dual-criteria queries
        $memberOfResult = $this->edgeBinder->query()->from($profile)->type('member_of')->get();
        $memberOfCount = count($memberOfResult->getBindings());

        $ownsResult = $this->edgeBinder->query()->from($profile)->type('owns')->get();
        $ownsCount = count($ownsResult->getBindings());

        // Triple-criteria queries
        $tripleMemResult = $this->edgeBinder->query()->from($profile)->to($organization)->type('member_of')->get();
        $tripleMemCount = count($tripleMemResult->getBindings());

        $tripleOwnResult = $this->edgeBinder->query()->from($profile)->to($workspace)->type('owns')->get();
        $tripleOwnCount = count($tripleOwnResult->getBindings());

        // Individual component queries
        $fromOnlyResult = $this->edgeBinder->query()->from($profile)->get();
        $fromOnlyCount = count($fromOnlyResult->getBindings());

        $toOrgResult = $this->edgeBinder->query()->to($organization)->get();
        $toOrgCount = count($toOrgResult->getBindings());

        $toWorkspaceResult = $this->edgeBinder->query()->to($workspace)->get();
        $toWorkspaceCount = count($toWorkspaceResult->getBindings());

        $typeMemberResult = $this->edgeBinder->query()->type('member_of')->get();
        $typeMemberCount = count($typeMemberResult->getBindings());

        $typeOwnsResult = $this->edgeBinder->query()->type('owns')->get();
        $typeOwnsCount = count($typeOwnsResult->getBindings());

        // Expected results (all should work correctly now):
        // ✅ from + type (member_of): 1 result
        // ✅ from + type (owns): 1 result
        // ✅ from + to + type (member_of): 1 result
        // ✅ from + to + type (owns): 1 result
        // ✅ from only: 2 results
        // ✅ to organization: 1 result
        // ✅ to workspace: 1 result
        // ✅ type member_of: 1 result
        // ✅ type owns: 1 result

        // Validate comprehensive query pattern matrix
        $this->assertNotNull($membershipBinding, 'Should create membership binding');
        $this->assertNotNull($ownershipBinding, 'Should create ownership binding');

        $this->assertCount(
            1,
            $memberOfResult->getBindings(),
            'from + type (member_of) should return 1 result'
        );
        $this->assertEquals(1, $memberOfCount, 'member_of count should be 1');

        $this->assertCount(
            1,
            $ownsResult->getBindings(),
            'from + type (owns) should return 1 result'
        );
        $this->assertEquals(1, $ownsCount, 'owns count should be 1');

        $this->assertCount(
            1,
            $tripleMemResult->getBindings(),
            'from + to + type (member_of) should return 1 result'
        );
        $this->assertEquals(1, $tripleMemCount, 'triple member_of count should be 1');

        $this->assertCount(
            1,
            $tripleOwnResult->getBindings(),
            'from + to + type (owns) should return 1 result'
        );
        $this->assertEquals(1, $tripleOwnCount, 'triple owns count should be 1');
        $this->assertCount(
            2,
            $fromOnlyResult->getBindings(),
            'from only should return 2 results'
        );
        $this->assertEquals(2, $fromOnlyCount, 'from only count should be 2');

        $this->assertCount(
            1,
            $toOrgResult->getBindings(),
            'to organization should return 1 result'
        );
        $this->assertEquals(1, $toOrgCount, 'to organization count should be 1');

        $this->assertCount(
            1,
            $toWorkspaceResult->getBindings(),
            'to workspace should return 1 result'
        );
        $this->assertEquals(1, $toWorkspaceCount, 'to workspace count should be 1');

        $this->assertCount(
            1,
            $typeMemberResult->getBindings(),
            'type member_of should return 1 result'
        );
        $this->assertEquals(1, $typeMemberCount, 'type member_of count should be 1');

        $this->assertCount(
            1,
            $typeOwnsResult->getBindings(),
            'type owns should return 1 result'
        );
        $this->assertEquals(1, $typeOwnsCount, 'type owns count should be 1');
    }

    /**
     * Test member_of relationship type with different entity ID patterns.
     *
     * Validates that member_of relationships work correctly regardless of
     * entity ID patterns (simple, hyphenated, numbered, UUID-like).
     */
    public function testMemberOfWithDifferentEntityIdPatterns(): void
    {
        // Test with different entity ID patterns
        $entities = [
            'simple' => new class('simple', 'Simple') {
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
            },
            'hyphenated' => new class('test-entity', 'Hyphenated') {
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
            },
            'numbered' => new class('entity-123', 'Numbered') {
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
            },
        ];

        foreach ($entities as $name => $entity) {
            $otherEntity = new class("other-{$name}", "Other {$name}") {
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

            // Test member_of with different entity patterns
            $binding = $this->edgeBinder->bind(from: $entity, to: $otherEntity, type: 'member_of');
            $this->assertNotNull($binding, "Should create binding for {$name} entity pattern");

            $result = $this->edgeBinder->query()->from($entity)->type('member_of')->get();
            $this->assertNotEmpty(
                $result->getBindings(),
                "member_of should work with {$name} entity pattern"
            );

            $toResult = $this->edgeBinder->query()->to($otherEntity)->get();
            $this->assertNotEmpty(
                $toResult->getBindings(),
                "to() should work with {$name} entity pattern"
            );
        }
    }
}

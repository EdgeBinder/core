<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Integration\Core;

use EdgeBinder\EdgeBinder;
use EdgeBinder\Persistence\InMemory\InMemoryAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Exact bug reproduction test based on the bug report.
 *
 * This test attempts to reproduce the exact scenarios described in the
 * EDGEBINDER_CORE_EDGE_CASES_BUG_REPORT.md file to verify if the bugs
 * still exist in the current version.
 *
 * Expected Results:
 * - If bugs exist: Tests should FAIL with specific error messages
 * - If bugs are fixed: Tests should PASS, indicating the issues are resolved
 */
class BugReproductionTest extends TestCase
{
    private EdgeBinder $edgeBinder;

    protected function setUp(): void
    {
        // Use InMemory adapter exactly as described in bug report
        $adapter = new InMemoryAdapter();
        $this->edgeBinder = new EdgeBinder($adapter);
    }

    /**
     * Reproduce Bug 1: member_of relationship type issue.
     *
     * This test reproduces the exact scenario from the bug report where
     * member_of relationship type queries return empty results.
     */
    public function testMemberOfRelationshipTypeBug(): void
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
        echo 'from + type (member_of): '.count($result1->getBindings())." results\n";

        $result2 = $this->edgeBinder->query()->from($profile)->to($organization)->type('member_of')->get();
        echo 'from + to + type (member_of): '.count($result2->getBindings())." results\n";

        // Control test with different type - THIS WORKS (according to bug report)
        $ownsBinding = $this->edgeBinder->bind(from: $profile, to: $organization, type: 'owns');
        $result3 = $this->edgeBinder->query()->from($profile)->type('owns')->get();
        echo 'from + type (owns): '.count($result3->getBindings())." results\n";

        // Assertions based on bug report expectations
        $this->assertNotEmpty(
            $result1->getBindings(),
            "BUG REPRODUCTION: from() + type('member_of') should return results but returns empty"
        );
        $this->assertNotEmpty(
            $result2->getBindings(),
            "BUG REPRODUCTION: from() + to() + type('member_of') should return results but returns empty"
        );
        $this->assertNotEmpty(
            $result3->getBindings(),
            "Control test: from() + type('owns') should work"
        );
    }

    /**
     * Reproduce Bug 2: to() query direction issue.
     *
     * This test reproduces the exact scenario from the bug report where
     * to() queries return empty results for certain entities.
     */
    public function testToQueryDirectionBug(): void
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
        echo 'from profile: '.count($fromResult->getBindings())." results\n";

        // Query by 'to' direction - THIS FAILS FOR SOME ENTITIES (according to bug report)
        $toResult = $this->edgeBinder->query()->to($organization)->get();
        echo 'to organization: '.count($toResult->getBindings())." results\n";

        $toResult2 = $this->edgeBinder->query()->to($profile)->get();
        echo 'to profile: '.count($toResult2->getBindings())." results\n";

        // Assertions based on bug report expectations
        $this->assertNotEmpty(
            $fromResult->getBindings(),
            'Control test: from() queries should work'
        );
        $this->assertNotEmpty(
            $toResult->getBindings(),
            'BUG REPRODUCTION: to() queries should work for organization entities but return empty'
        );
        $this->assertNotEmpty(
            $toResult2->getBindings(),
            'to() queries should work for profile entities'
        );
    }

    /**
     * Reproduce the systematic test results from the bug report.
     *
     * This test reproduces the exact test matrix described in the bug report
     * to see if we get the same failure patterns.
     */
    public function testSystematicBugReproduction(): void
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

        echo "\n=== Systematic Test Results ===\n";
        echo "Created membership: {$membershipBinding->getId()}\n";
        echo "Created ownership: {$ownershipBinding->getId()}\n";

        // Dual-criteria queries
        echo "\nDual-criteria queries:\n";
        $memberOfResult = $this->edgeBinder->query()->from($profile)->type('member_of')->get();
        echo 'from + type (member_of): '.count($memberOfResult->getBindings())." results\n";

        $ownsResult = $this->edgeBinder->query()->from($profile)->type('owns')->get();
        echo 'from + type (owns): '.count($ownsResult->getBindings())." results\n";

        // Triple-criteria queries
        echo "\nTriple-criteria queries:\n";
        $tripleMemResult = $this->edgeBinder->query()->from($profile)->to($organization)->type('member_of')->get();
        echo 'from + to + type (member_of): '.count($tripleMemResult->getBindings())." results\n";

        $tripleOwnResult = $this->edgeBinder->query()->from($profile)->to($workspace)->type('owns')->get();
        echo 'from + to + type (owns): '.count($tripleOwnResult->getBindings())." results\n";

        // Individual component queries
        echo "\nIndividual component queries:\n";
        $fromOnlyResult = $this->edgeBinder->query()->from($profile)->get();
        echo 'from only: '.count($fromOnlyResult->getBindings())." results\n";

        $toOrgResult = $this->edgeBinder->query()->to($organization)->get();
        echo 'to organization: '.count($toOrgResult->getBindings())." results\n";

        $toWorkspaceResult = $this->edgeBinder->query()->to($workspace)->get();
        echo 'to workspace: '.count($toWorkspaceResult->getBindings())." results\n";

        $typeMemberResult = $this->edgeBinder->query()->type('member_of')->get();
        echo 'type member_of: '.count($typeMemberResult->getBindings())." results\n";

        $typeOwnsResult = $this->edgeBinder->query()->type('owns')->get();
        echo 'type owns: '.count($typeOwnsResult->getBindings())." results\n";

        // Expected results based on bug report:
        // ❌ from + type (member_of): 0 results  (should be 1)
        // ✅ from + type (owns): 1 results
        // ❌ from + to + type (member_of): 0 results  (should be 1)
        // ✅ from + to + type (owns): 1 results
        // ✅ from only: 2 results
        // ❌ to organization: 0 results  (should be 1)
        // ✅ to workspace: 1 results
        // ✅ type member_of: 1 results
        // ✅ type owns: 1 results

        // Assertions to catch the bugs
        $this->assertCount(
            1,
            $memberOfResult->getBindings(),
            'BUG: from + type (member_of) should return 1 result'
        );
        $this->assertCount(
            1,
            $ownsResult->getBindings(),
            'Control: from + type (owns) should return 1 result'
        );
        $this->assertCount(
            1,
            $tripleMemResult->getBindings(),
            'BUG: from + to + type (member_of) should return 1 result'
        );
        $this->assertCount(
            1,
            $tripleOwnResult->getBindings(),
            'Control: from + to + type (owns) should return 1 result'
        );
        $this->assertCount(
            2,
            $fromOnlyResult->getBindings(),
            'from only should return 2 results'
        );
        $this->assertCount(
            1,
            $toOrgResult->getBindings(),
            'BUG: to organization should return 1 result'
        );
        $this->assertCount(
            1,
            $toWorkspaceResult->getBindings(),
            'to workspace should return 1 result'
        );
        $this->assertCount(
            1,
            $typeMemberResult->getBindings(),
            'type member_of should return 1 result'
        );
        $this->assertCount(
            1,
            $typeOwnsResult->getBindings(),
            'type owns should return 1 result'
        );
    }

    /**
     * Test if the bugs are environment or configuration specific.
     */
    public function testEnvironmentSpecificConditions(): void
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

            $result = $this->edgeBinder->query()->from($entity)->type('member_of')->get();
            $this->assertNotEmpty(
                $result->getBindings(),
                "member_of should work with {$name} entity pattern"
            );

            $toResult = $this->edgeBinder->query()->to($otherEntity)->get();
            $this->assertNotEmpty($toResult->getBindings(),
                "to() should work with {$name} entity pattern");
        }
    }
}

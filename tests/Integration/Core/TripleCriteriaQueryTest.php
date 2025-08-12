<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Integration\Core;

use EdgeBinder\EdgeBinder;
use EdgeBinder\Persistence\InMemory\InMemoryAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Focused test for the specific triple-criteria query pattern:
 * from() + to() + type() combination
 *
 * This test specifically covers the pattern mentioned in the issue:
 * $result = $this->edgeBinder->query()
 *     ->from($profile)
 *     ->to($organization)
 *     ->type(RelationshipType::MEMBER_OF->value)
 *     ->get();
 */
class TripleCriteriaQueryTest extends TestCase
{
    private EdgeBinder $edgeBinder;

    protected function setUp(): void
    {
        $adapter = new InMemoryAdapter();
        $this->edgeBinder = new EdgeBinder($adapter);
    }

    /**
     * Test the exact pattern mentioned in the issue.
     */
    public function testFromToTypePattern(): void
    {
        // Create test entities
        $profile = $this->createTestEntity('profile-123', 'Test Profile');
        $organization = $this->createTestEntity('org-456', 'Test Organization');

        // Create the relationship
        $binding = $this->edgeBinder->bind(
            from: $profile,
            to: $organization,
            type: 'member_of',  // Using string value like RelationshipType::MEMBER_OF->value
            metadata: ['role' => 'admin']
        );

        // Test the exact pattern from the issue
        $result = $this->edgeBinder->query()
            ->from($profile)
            ->to($organization)          // ← Additional constraint
            ->type('member_of')          // ← Using the relationship type value
            ->get();

        // Assertions
        $this->assertNotEmpty(
            $result->getBindings(),
            'Triple-criteria query (from + to + type) should return results'
        );
        $this->assertCount(
            1,
            $result->getBindings(),
            'Should find exactly 1 matching binding'
        );

        $foundBinding = $result->getBindings()[0];
        $this->assertEquals(
            $binding->getId(),
            $foundBinding->getId(),
            'Should find the correct binding'
        );
        $this->assertEquals(
            'member_of',
            $foundBinding->getType(),
            'Found binding should have correct type'
        );
    }

    /**
     * Test the pattern with multiple relationship types.
     */
    public function testFromToTypePatternWithMultipleTypes(): void
    {
        $profile = $this->createTestEntity('profile-789', 'Test Profile');
        $organization = $this->createTestEntity('org-101', 'Test Organization');

        // Create multiple relationships between the same entities
        $memberBinding = $this->edgeBinder->bind(from: $profile, to: $organization, type: 'member_of');
        $ownsBinding = $this->edgeBinder->bind(from: $profile, to: $organization, type: 'owns');
        $managesBinding = $this->edgeBinder->bind(from: $profile, to: $organization, type: 'manages');

        // Test each type specifically with triple-criteria
        $memberResult = $this->edgeBinder->query()
            ->from($profile)
            ->to($organization)
            ->type('member_of')
            ->get();

        $ownsResult = $this->edgeBinder->query()
            ->from($profile)
            ->to($organization)
            ->type('owns')
            ->get();

        $managesResult = $this->edgeBinder->query()
            ->from($profile)
            ->to($organization)
            ->type('manages')
            ->get();

        // Each query should return exactly 1 result
        $this->assertCount(
            1,
            $memberResult->getBindings(),
            'member_of triple-criteria should return 1 result'
        );
        $this->assertCount(
            1,
            $ownsResult->getBindings(),
            'owns triple-criteria should return 1 result'
        );
        $this->assertCount(
            1,
            $managesResult->getBindings(),
            'manages triple-criteria should return 1 result'
        );

        // Verify correct bindings are returned
        $this->assertEquals($memberBinding->getId(), $memberResult->getBindings()[0]->getId());
        $this->assertEquals($ownsBinding->getId(), $ownsResult->getBindings()[0]->getId());
        $this->assertEquals($managesBinding->getId(), $managesResult->getBindings()[0]->getId());
    }

    /**
     * Test the pattern with entities that have no matching relationships.
     */
    public function testFromToTypePatternWithNoMatches(): void
    {
        $profile = $this->createTestEntity('profile-999', 'Test Profile');
        $organization = $this->createTestEntity('org-888', 'Test Organization');
        $otherOrg = $this->createTestEntity('org-777', 'Other Organization');

        // Create relationship between profile and otherOrg (not organization)
        $this->edgeBinder->bind(from: $profile, to: $otherOrg, type: 'member_of');

        // Query for relationship that doesn't exist
        $result = $this->edgeBinder->query()
            ->from($profile)
            ->to($organization)          // ← No relationship to this organization
            ->type('member_of')
            ->get();

        $this->assertEmpty(
            $result->getBindings(),
            'Should return empty when no matching relationship exists'
        );
    }

    /**
     * Test the pattern with wrong relationship type.
     */
    public function testFromToTypePatternWithWrongType(): void
    {
        $profile = $this->createTestEntity('profile-555', 'Test Profile');
        $organization = $this->createTestEntity('org-444', 'Test Organization');

        // Create relationship with 'owns' type
        $this->edgeBinder->bind(from: $profile, to: $organization, type: 'owns');

        // Query for different type
        $result = $this->edgeBinder->query()
            ->from($profile)
            ->to($organization)
            ->type('member_of')          // ← Wrong type
            ->get();

        $this->assertEmpty(
            $result->getBindings(),
            "Should return empty when relationship type doesn't match"
        );
    }

    /**
     * Test the pattern with complex entity relationships.
     */
    public function testFromToTypePatternWithComplexRelationships(): void
    {
        // Create multiple entities
        $profile1 = $this->createTestEntity('profile-1', 'Profile 1');
        $profile2 = $this->createTestEntity('profile-2', 'Profile 2');
        $org1 = $this->createTestEntity('org-1', 'Organization 1');
        $org2 = $this->createTestEntity('org-2', 'Organization 2');

        // Create complex relationship matrix
        $this->edgeBinder->bind(from: $profile1, to: $org1, type: 'member_of');
        $this->edgeBinder->bind(from: $profile1, to: $org2, type: 'member_of');
        $this->edgeBinder->bind(from: $profile2, to: $org1, type: 'member_of');
        $this->edgeBinder->bind(from: $profile1, to: $org1, type: 'owns');  // Same entities, different type

        // Test specific triple-criteria queries
        $result1 = $this->edgeBinder->query()
            ->from($profile1)
            ->to($org1)
            ->type('member_of')
            ->get();

        $result2 = $this->edgeBinder->query()
            ->from($profile1)
            ->to($org1)
            ->type('owns')
            ->get();

        $result3 = $this->edgeBinder->query()
            ->from($profile2)
            ->to($org1)
            ->type('member_of')
            ->get();

        // Each should return exactly 1 result
        $this->assertCount(
            1,
            $result1->getBindings(),
            'profile1 -> org1 (member_of) should return 1 result'
        );
        $this->assertCount(
            1,
            $result2->getBindings(),
            'profile1 -> org1 (owns) should return 1 result'
        );
        $this->assertCount(
            1,
            $result3->getBindings(),
            'profile2 -> org1 (member_of) should return 1 result'
        );

        // Verify types are correct
        $this->assertEquals('member_of', $result1->getBindings()[0]->getType());
        $this->assertEquals('owns', $result2->getBindings()[0]->getType());
        $this->assertEquals('member_of', $result3->getBindings()[0]->getType());
    }

    /**
     * Test performance with the triple-criteria pattern.
     */
    public function testFromToTypePatternPerformance(): void
    {
        // Create many relationships
        $profiles = [];
        $organizations = [];

        for ($i = 1; $i <= 10; ++$i) {
            $profiles[] = $this->createTestEntity("profile-{$i}", "Profile {$i}");
            $organizations[] = $this->createTestEntity("org-{$i}", "Organization {$i}");
        }

        // Create relationships
        $targetBinding = null;
        foreach ($profiles as $i => $profile) {
            foreach ($organizations as $j => $org) {
                $binding = $this->edgeBinder->bind(from: $profile, to: $org, type: 'member_of');
                if (5 === $i && 5 === $j) {
                    $targetBinding = $binding; // Remember a specific binding
                }
            }
        }

        // Test triple-criteria query finds the specific binding
        $result = $this->edgeBinder->query()
            ->from($profiles[5])
            ->to($organizations[5])
            ->type('member_of')
            ->get();

        $this->assertCount(
            1,
            $result->getBindings(),
            'Should find exactly 1 result even with many relationships'
        );
        $this->assertNotNull($targetBinding, 'Target binding should not be null');
        $this->assertEquals(
            $targetBinding->getId(),
            $result->getBindings()[0]->getId(),
            'Should find the correct specific binding'
        );
    }

    /**
     * Helper method to create test entities.
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
}

<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Integration\Core;

use EdgeBinder\EdgeBinder;
use EdgeBinder\Persistence\InMemory\InMemoryAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Systematic relationship type testing to catch type-specific bugs.
 *
 * This test focuses specifically on ensuring ALL relationship types work
 * consistently across ALL query patterns. It's designed to catch bugs like
 * the 'member_of' relationship type issue where specific types fail.
 *
 * Expected Results:
 * - This test should FAIL for 'member_of' type initially
 * - After core fixes, all relationship types should work identically
 */
class RelationshipTypeMatrixTest extends TestCase
{
    private EdgeBinder $edgeBinder;

    protected function setUp(): void
    {
        $adapter = new InMemoryAdapter();
        $this->edgeBinder = new EdgeBinder($adapter);
    }

    /**
     * Test that every relationship type works with every query pattern.
     *
     * This is a comprehensive matrix test that creates relationships of each type
     * and then tests every possible query pattern against them.
     */
    public function testRelationshipTypeMatrix(): void
    {
        $relationshipTypes = [
            'owns',
            'member_of',        // ← Known to fail
            'contains',
            'has_access',
            'manages',
            'authenticates',
            'belongs_to',
            'controls',
            'depends_on',
            'inherits_from',
        ];

        $entity1 = $this->createTestEntity('matrix-entity-1', 'Matrix Entity 1');
        $entity2 = $this->createTestEntity('matrix-entity-2', 'Matrix Entity 2');

        $typeResults = [];

        foreach ($relationshipTypes as $type) {
            // Create binding for this type
            $binding = $this->edgeBinder->bind(from: $entity1, to: $entity2, type: $type);

            // Test all query patterns
            $patterns = [
                'from_only' => $this->edgeBinder->query()->from($entity1)->get(),
                'to_only' => $this->edgeBinder->query()->to($entity2)->get(),
                'type_only' => $this->edgeBinder->query()->type($type)->get(),
                'from_and_type' => $this->edgeBinder->query()->from($entity1)->type($type)->get(),
                'to_and_type' => $this->edgeBinder->query()->to($entity2)->type($type)->get(),
                'from_and_to' => $this->edgeBinder->query()->from($entity1)->to($entity2)->get(),
                'from_to_type' => $this->edgeBinder->query()->from($entity1)->to($entity2)->type($type)->get(),
            ];

            $typeResults[$type] = [
                'binding' => $binding,
                'patterns' => [],
            ];

            foreach ($patterns as $patternName => $result) {
                $bindings = $result->getBindings();
                $found = $this->findBindingInResults($binding, $bindings);

                $typeResults[$type]['patterns'][$patternName] = [
                    'found' => $found,
                    'count' => count($bindings),
                ];
            }
        }

        // Analyze results and report inconsistencies
        $this->analyzeTypeConsistency($typeResults);

        // If we reach here, all relationship types worked correctly
        $this->assertNotEmpty($typeResults, 'Should have tested relationship types');
        $this->assertCount(count($relationshipTypes), $typeResults, 'Should have results for all relationship types');
    }

    /**
     * Test specific problematic relationship types in isolation.
     */
    public function testProblematicRelationshipTypes(): void
    {
        $problematicTypes = [
            'member_of',    // Known to fail in bug report
            'belongs_to',   // Similar semantic meaning, might also fail
        ];

        $workingTypes = [
            'owns',         // Known to work in bug report
            'has_access',   // Known to work in bug report
        ];

        $profile = $this->createTestEntity('test-profile', 'Test Profile');
        $organization = $this->createTestEntity('test-org', 'Test Organization');

        // Test problematic types
        foreach ($problematicTypes as $type) {
            $binding = $this->edgeBinder->bind(from: $profile, to: $organization, type: $type);
            $this->assertNotNull($binding, "Should create binding for problematic type '{$type}'");

            // These should work but might fail
            $fromTypeResult = $this->edgeBinder->query()->from($profile)->type($type)->get();
            $this->assertNotEmpty(
                $fromTypeResult->getBindings(),
                "CRITICAL BUG: from() + type('{$type}') should return results but returns empty"
            );

            $toTypeResult = $this->edgeBinder->query()->to($organization)->type($type)->get();
            $this->assertNotEmpty(
                $toTypeResult->getBindings(),
                "CRITICAL BUG: to() + type('{$type}') should return results but returns empty"
            );

            $tripleResult = $this->edgeBinder->query()->from($profile)->to($organization)->type($type)->get();
            $this->assertNotEmpty(
                $tripleResult->getBindings(),
                "CRITICAL BUG: from() + to() + type('{$type}') should return results but returns empty"
            );
        }

        // Test working types (control group)
        foreach ($workingTypes as $type) {
            $binding = $this->edgeBinder->bind(from: $profile, to: $organization, type: $type);
            $this->assertNotNull($binding, "Should create binding for working type '{$type}'");

            // These should work and do work
            $fromTypeResult = $this->edgeBinder->query()->from($profile)->type($type)->get();
            $this->assertNotEmpty(
                $fromTypeResult->getBindings(),
                "Control test: from() + type('{$type}') should work"
            );

            $toTypeResult = $this->edgeBinder->query()->to($organization)->type($type)->get();
            $this->assertNotEmpty(
                $toTypeResult->getBindings(),
                "Control test: to() + type('{$type}') should work"
            );

            $tripleResult = $this->edgeBinder->query()->from($profile)->to($organization)->type($type)->get();
            $this->assertNotEmpty(
                $tripleResult->getBindings(),
                "Control test: from() + to() + type('{$type}') should work"
            );
        }
    }

    /**
     * Test relationship types with special characters or patterns.
     */
    public function testSpecialRelationshipTypes(): void
    {
        $specialTypes = [
            'member_of',        // Underscore - known issue
            'member-of',        // Hyphen
            'memberOf',         // CamelCase
            'MEMBER_OF',        // Uppercase
            'member.of',        // Dot
            'member/of',        // Slash
        ];

        $entity1 = $this->createTestEntity('special-entity-1', 'Special Entity 1');
        $entity2 = $this->createTestEntity('special-entity-2', 'Special Entity 2');

        foreach ($specialTypes as $type) {
            $binding = $this->edgeBinder->bind(from: $entity1, to: $entity2, type: $type);
            $this->assertNotNull($binding, "Should create binding for special type '{$type}'");

            // Test basic query patterns
            $fromResult = $this->edgeBinder->query()->from($entity1)->type($type)->get();
            $this->assertNotEmpty(
                $fromResult->getBindings(),
                "Special type '{$type}' should work in from() + type() queries"
            );

            $toResult = $this->edgeBinder->query()->to($entity2)->type($type)->get();
            $this->assertNotEmpty(
                $toResult->getBindings(),
                "Special type '{$type}' should work in to() + type() queries"
            );
        }
    }

    /**
     * Analyze type consistency and report failures.
     *
     * @param array<string, array<string, mixed>> $typeResults
     */
    private function analyzeTypeConsistency(array $typeResults): void
    {
        $failures = [];

        foreach ($typeResults as $type => $results) {
            foreach ($results['patterns'] as $pattern => $result) {
                if (!$result['found']) {
                    $failures[] = [
                        'type' => $type,
                        'pattern' => $pattern,
                        'count' => $result['count'],
                    ];
                }
            }
        }

        if (!empty($failures)) {
            $report = "Relationship type inconsistencies detected:\n";
            foreach ($failures as $failure) {
                $report .= "- Type '{$failure['type']}' failed pattern '{$failure['pattern']}' ";
                $report .= "(returned {$failure['count']} results instead of finding the binding)\n";
            }

            // Add analysis of which types work vs fail
            $workingTypes = [];
            $failingTypes = [];

            foreach ($typeResults as $type => $results) {
                $hasFailures = false;
                foreach ($results['patterns'] as $result) {
                    if (!$result['found']) {
                        $hasFailures = true;

                        break;
                    }
                }

                if ($hasFailures) {
                    $failingTypes[] = $type;
                } else {
                    $workingTypes[] = $type;
                }
            }

            $report .= "\nWorking types: ".implode(', ', $workingTypes)."\n";
            $report .= 'Failing types: '.implode(', ', $failingTypes)."\n";

            $this->fail($report);
        }
    }

    /**
     * Find a specific binding in query results.
     *
     * @param array<mixed> $bindings
     */
    private function findBindingInResults(mixed $expectedBinding, array $bindings): bool
    {
        foreach ($bindings as $binding) {
            if ($binding->getId() === $expectedBinding->getId()) {
                return true;
            }
        }

        return false;
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
}

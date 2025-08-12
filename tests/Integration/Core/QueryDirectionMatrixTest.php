<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Integration\Core;

use EdgeBinder\EdgeBinder;
use EdgeBinder\Persistence\InMemory\InMemoryAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Systematic query direction testing to catch direction-specific bugs.
 *
 * This test focuses specifically on ensuring ALL entity types work
 * consistently in ALL query directions. It's designed to catch bugs like
 * the 'to() organization' query direction issue.
 *
 * Expected Results:
 * - This test should FAIL for 'to()' queries on certain entity types initially
 * - After core fixes, all entity types should work in both directions
 */
class QueryDirectionMatrixTest extends TestCase
{
    private EdgeBinder $edgeBinder;

    protected function setUp(): void
    {
        $adapter = new InMemoryAdapter();
        $this->edgeBinder = new EdgeBinder($adapter);
    }

    /**
     * Test that every entity type works in every query direction.
     *
     * This is a comprehensive matrix test that creates relationships between
     * different entity types and tests both 'from' and 'to' query directions.
     */
    public function testQueryDirectionMatrix(): void
    {
        // Create diverse entity types that mirror real-world usage
        $entities = [
            'profile' => $this->createTestEntity('matrix-profile', 'Matrix Profile'),
            'organization' => $this->createTestEntity('matrix-org', 'Matrix Organization'),
            'workspace' => $this->createTestEntity('matrix-workspace', 'Matrix Workspace'),
            'user' => $this->createTestEntity('matrix-user', 'Matrix User'),
            'project' => $this->createTestEntity('matrix-project', 'Matrix Project'),
            'team' => $this->createTestEntity('matrix-team', 'Matrix Team'),
        ];

        // Create comprehensive relationship matrix
        $bindings = [];
        foreach ($entities as $fromName => $fromEntity) {
            foreach ($entities as $toName => $toEntity) {
                if ($fromName !== $toName) {
                    $binding = $this->edgeBinder->bind(
                        from: $fromEntity,
                        to: $toEntity,
                        type: 'test_relation'
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
        $directionResults = [];

        foreach ($entities as $entityName => $entity) {
            // Test as 'from' entity
            $fromResults = $this->edgeBinder->query()->from($entity)->get();
            $expectedFromCount = count(array_filter($bindings, fn ($b) => $b['from_entity'] === $entity));

            // Test as 'to' entity
            $toResults = $this->edgeBinder->query()->to($entity)->get();
            $expectedToCount = count(array_filter($bindings, fn ($b) => $b['to_entity'] === $entity));

            $directionResults[$entityName] = [
                'from' => [
                    'expected' => $expectedFromCount,
                    'actual' => count($fromResults->getBindings()),
                    'success' => count($fromResults->getBindings()) === $expectedFromCount,
                ],
                'to' => [
                    'expected' => $expectedToCount,
                    'actual' => count($toResults->getBindings()),
                    'success' => count($toResults->getBindings()) === $expectedToCount,
                ],
            ];
        }

        // Analyze and report direction failures
        $this->analyzeDirectionConsistency($directionResults);
    }

    /**
     * Test specific problematic entity types in query directions.
     */
    public function testProblematicEntityDirections(): void
    {
        // Based on bug report: organization entities fail in 'to' queries
        $problematicEntities = [
            'organization' => $this->createTestEntity('problem-org', 'Problem Organization'),
        ];

        $workingEntities = [
            'profile' => $this->createTestEntity('working-profile', 'Working Profile'),
            'workspace' => $this->createTestEntity('working-workspace', 'Working Workspace'),
        ];

        // Test problematic entities
        foreach ($problematicEntities as $entityName => $entity) {
            $otherEntity = $this->createTestEntity('other-entity', 'Other Entity');

            // Create relationship TO the problematic entity
            $binding = $this->edgeBinder->bind(from: $otherEntity, to: $entity, type: 'test_relation');

            // This should work but might fail
            $toResult = $this->edgeBinder->query()->to($entity)->get();
            $this->assertNotEmpty(
                $toResult->getBindings(),
                "CRITICAL BUG: to() queries should work for '{$entityName}' entities but return empty"
            );

            // Verify the specific binding is found
            $found = false;
            foreach ($toResult->getBindings() as $foundBinding) {
                if ($foundBinding->getId() === $binding->getId()) {
                    $found = true;

                    break;
                }
            }
            $this->assertTrue(
                $found,
                "CRITICAL BUG: to() query should find the specific binding for '{$entityName}' entity"
            );
        }

        // Test working entities (control group)
        foreach ($workingEntities as $entityName => $entity) {
            $otherEntity = $this->createTestEntity('other-entity-2', 'Other Entity 2');

            // Create relationship TO the working entity
            $binding = $this->edgeBinder->bind(from: $otherEntity, to: $entity, type: 'test_relation');

            // This should work and does work
            $toResult = $this->edgeBinder->query()->to($entity)->get();
            $this->assertNotEmpty(
                $toResult->getBindings(),
                "Control test: to() queries should work for '{$entityName}' entities"
            );
        }
    }

    /**
     * Test bidirectional queries to ensure symmetry.
     */
    public function testBidirectionalQuerySymmetry(): void
    {
        $entityA = $this->createTestEntity('entity-a', 'Entity A');
        $entityB = $this->createTestEntity('entity-b', 'Entity B');

        // Create relationships in both directions
        $bindingAtoB = $this->edgeBinder->bind(from: $entityA, to: $entityB, type: 'forward');
        $bindingBtoA = $this->edgeBinder->bind(from: $entityB, to: $entityA, type: 'backward');

        // Test that both entities work as 'from' entities
        $fromA = $this->edgeBinder->query()->from($entityA)->get();
        $fromB = $this->edgeBinder->query()->from($entityB)->get();

        $this->assertCount(
            1,
            $fromA->getBindings(),
            'Entity A should have 1 outgoing relationship'
        );
        $this->assertCount(
            1,
            $fromB->getBindings(),
            'Entity B should have 1 outgoing relationship'
        );

        // Test that both entities work as 'to' entities
        $toA = $this->edgeBinder->query()->to($entityA)->get();
        $toB = $this->edgeBinder->query()->to($entityB)->get();

        $this->assertCount(
            1,
            $toA->getBindings(),
            'Entity A should have 1 incoming relationship'
        );
        $this->assertCount(
            1,
            $toB->getBindings(),
            'Entity B should have 1 incoming relationship'
        );

        // Verify specific bindings are found
        $this->assertEquals($bindingAtoB->getId(), $fromA->getBindings()[0]->getId());
        $this->assertEquals($bindingBtoA->getId(), $fromB->getBindings()[0]->getId());
        $this->assertEquals($bindingBtoA->getId(), $toA->getBindings()[0]->getId());
        $this->assertEquals($bindingAtoB->getId(), $toB->getBindings()[0]->getId());
    }

    /**
     * Test complex multi-entity direction scenarios.
     */
    public function testComplexDirectionScenarios(): void
    {
        // Create a hub entity that has many incoming and outgoing relationships
        $hub = $this->createTestEntity('hub-entity', 'Hub Entity');
        $satellites = [];

        for ($i = 1; $i <= 5; ++$i) {
            $satellites[] = $this->createTestEntity("satellite-{$i}", "Satellite {$i}");
        }

        $incomingBindings = [];
        $outgoingBindings = [];

        // Create incoming relationships (satellites -> hub)
        foreach ($satellites as $satellite) {
            $binding = $this->edgeBinder->bind(from: $satellite, to: $hub, type: 'points_to_hub');
            $incomingBindings[] = $binding;
        }

        // Create outgoing relationships (hub -> satellites)
        foreach ($satellites as $satellite) {
            $binding = $this->edgeBinder->bind(from: $hub, to: $satellite, type: 'manages');
            $outgoingBindings[] = $binding;
        }

        // Test hub as 'from' entity (should find all outgoing)
        $fromHub = $this->edgeBinder->query()->from($hub)->get();
        $this->assertCount(
            count($outgoingBindings),
            $fromHub->getBindings(),
            'Hub entity should find all outgoing relationships in from() query'
        );

        // Test hub as 'to' entity (should find all incoming)
        $toHub = $this->edgeBinder->query()->to($hub)->get();
        $this->assertCount(
            count($incomingBindings),
            $toHub->getBindings(),
            'CRITICAL BUG: Hub entity should find all incoming relationships in to() query'
        );

        // Test each satellite in both directions
        foreach ($satellites as $i => $satellite) {
            $fromSatellite = $this->edgeBinder->query()->from($satellite)->get();
            $this->assertCount(
                1,
                $fromSatellite->getBindings(),
                "Satellite {$i} should have 1 outgoing relationship"
            );

            $toSatellite = $this->edgeBinder->query()->to($satellite)->get();
            $this->assertCount(
                1,
                $toSatellite->getBindings(),
                "Satellite {$i} should have 1 incoming relationship"
            );
        }
    }

    /**
     * Analyze direction consistency and report failures.
     *
     * @param array<string, array<string, array<string, mixed>>> $directionResults
     */
    private function analyzeDirectionConsistency(array $directionResults): void
    {
        $failures = [];

        foreach ($directionResults as $entityName => $results) {
            if (!$results['from']['success']) {
                $failures[] = [
                    'entity' => $entityName,
                    'direction' => 'from',
                    'expected' => $results['from']['expected'],
                    'actual' => $results['from']['actual'],
                ];
            }

            if (!$results['to']['success']) {
                $failures[] = [
                    'entity' => $entityName,
                    'direction' => 'to',
                    'expected' => $results['to']['expected'],
                    'actual' => $results['to']['actual'],
                ];
            }
        }

        if (!empty($failures)) {
            $report = "Query direction inconsistencies detected:\n";
            foreach ($failures as $failure) {
                $report .= "- Entity '{$failure['entity']}' in '{$failure['direction']}' direction: ";
                $report .= "expected {$failure['expected']} results, got {$failure['actual']}\n";
            }

            // Add analysis of which directions work vs fail
            $workingFromEntities = [];
            $failingFromEntities = [];
            $workingToEntities = [];
            $failingToEntities = [];

            foreach ($directionResults as $entityName => $results) {
                if ($results['from']['success']) {
                    $workingFromEntities[] = $entityName;
                } else {
                    $failingFromEntities[] = $entityName;
                }

                if ($results['to']['success']) {
                    $workingToEntities[] = $entityName;
                } else {
                    $failingToEntities[] = $entityName;
                }
            }

            $report .= "\nWorking in 'from' direction: ".implode(', ', $workingFromEntities)."\n";
            $report .= "Failing in 'from' direction: ".implode(', ', $failingFromEntities)."\n";
            $report .= "Working in 'to' direction: ".implode(', ', $workingToEntities)."\n";
            $report .= "Failing in 'to' direction: ".implode(', ', $failingToEntities)."\n";

            $this->fail($report);
        }
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

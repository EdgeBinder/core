<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Unit\Session;

use EdgeBinder\Binding;
use EdgeBinder\Persistence\InMemory\InMemoryAdapter;
use EdgeBinder\Session\Session;
use EdgeBinder\Tests\Integration\Session\TestEntity;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Session Phase 1 critical methods that provide feature parity with EdgeBinder.
 *
 * These methods were identified as critical missing functionality in the v0.8.0 Session API:
 * - unbindEntities() - Bulk unbinding between entities
 * - findBindingsFor() - Find all bindings for an entity
 * - areBound() - Check if entities are bound
 *
 * Tests ensure these methods work with both session cache and adapter integration.
 */
class SessionCriticalMethodsTest extends TestCase
{
    private Session $session;
    private InMemoryAdapter $adapter;
    private TestEntity $profile;
    private TestEntity $organization;
    private TestEntity $team;

    protected function setUp(): void
    {
        $this->adapter = new InMemoryAdapter();
        $this->session = new Session($this->adapter);

        $this->profile = new TestEntity('profile-123', 'Profile');
        $this->organization = new TestEntity('org-456', 'Organization');
        $this->team = new TestEntity('team-789', 'Team');
    }

    // ========================================
    // unbindEntities() Tests - Phase 1 Critical
    // ========================================

    public function testUnbindEntitiesRemovesAllBindingsBetweenEntities(): void
    {
        // Create multiple bindings between profile and organization
        $binding1 = $this->session->bind($this->profile, $this->organization, 'member_of');
        $binding2 = $this->session->bind($this->profile, $this->organization, 'admin_of');

        // Create a binding to a different entity (should not be affected)
        $binding3 = $this->session->bind($this->profile, $this->team, 'member_of');

        // Verify initial state
        $this->assertCount(3, $this->session->getTrackedBindings());

        // Unbind all relationships between profile and organization
        $deletedCount = $this->session->unbindEntities($this->profile, $this->organization);

        // Should have deleted 2 bindings
        $this->assertEquals(2, $deletedCount);

        // Verify bindings are removed from session cache
        $trackedBindings = $this->session->getTrackedBindings();
        $this->assertCount(1, $trackedBindings);
        $this->assertEquals($binding3->getId(), $trackedBindings[0]->getId());

        // Verify bindings are removed from adapter
        $this->assertNull($this->adapter->find($binding1->getId()));
        $this->assertNull($this->adapter->find($binding2->getId()));
        $this->assertNotNull($this->adapter->find($binding3->getId()));
    }

    public function testUnbindEntitiesWithSpecificType(): void
    {
        // Create multiple bindings with different types
        $memberBinding = $this->session->bind($this->profile, $this->organization, 'member_of');
        $adminBinding = $this->session->bind($this->profile, $this->organization, 'admin_of');

        // Unbind only 'member_of' relationships
        $deletedCount = $this->session->unbindEntities($this->profile, $this->organization, 'member_of');

        // Should have deleted only 1 binding
        $this->assertEquals(1, $deletedCount);

        // Verify only member_of binding was removed
        $this->assertNull($this->adapter->find($memberBinding->getId()));
        $this->assertNotNull($this->adapter->find($adminBinding->getId()));
    }

    public function testUnbindEntitiesWithNoBindings(): void
    {
        // Try to unbind entities that have no bindings
        $deletedCount = $this->session->unbindEntities($this->profile, $this->organization);

        $this->assertEquals(0, $deletedCount);
    }

    public function testUnbindEntitiesWorksWithAdapterOnlyBindings(): void
    {
        // Create binding directly in adapter (not in session cache)
        $binding = Binding::create(
            fromType: 'Profile',
            fromId: 'profile-123',
            toType: 'Organization',
            toId: 'org-456',
            type: 'member_of',
            metadata: []
        );
        $this->adapter->store($binding);

        // Unbind should find and remove adapter-only binding
        $deletedCount = $this->session->unbindEntities($this->profile, $this->organization);

        $this->assertEquals(1, $deletedCount);
        $this->assertNull($this->adapter->find($binding->getId()));
    }

    // ========================================
    // findBindingsFor() Tests - Phase 1 Critical
    // ========================================

    public function testFindBindingsForReturnsAllEntityBindings(): void
    {
        // Create bindings from profile to different entities
        $binding1 = $this->session->bind($this->profile, $this->organization, 'member_of');
        $binding2 = $this->session->bind($this->profile, $this->team, 'member_of');

        // Create binding to profile (should also be included)
        $binding3 = $this->session->bind($this->organization, $this->profile, 'employs');

        $bindings = $this->session->findBindingsFor($this->profile);

        $this->assertCount(3, $bindings);

        $bindingIds = array_map(fn ($b) => $b->getId(), $bindings);
        $this->assertContains($binding1->getId(), $bindingIds);
        $this->assertContains($binding2->getId(), $bindingIds);
        $this->assertContains($binding3->getId(), $bindingIds);
    }

    public function testFindBindingsForMergesCacheAndAdapter(): void
    {
        // Create binding in session cache
        $cacheBinding = $this->session->bind($this->profile, $this->organization, 'member_of');

        // Create binding directly in adapter
        $adapterBinding = Binding::create(
            fromType: 'Profile',
            fromId: 'profile-123',
            toType: 'Team',
            toId: 'team-789',
            type: 'member_of',
            metadata: []
        );
        $this->adapter->store($adapterBinding);

        $bindings = $this->session->findBindingsFor($this->profile);

        $this->assertCount(2, $bindings);

        $bindingIds = array_map(fn ($b) => $b->getId(), $bindings);
        $this->assertContains($cacheBinding->getId(), $bindingIds);
        $this->assertContains($adapterBinding->getId(), $bindingIds);
    }

    public function testFindBindingsForWithNoBindings(): void
    {
        $bindings = $this->session->findBindingsFor($this->profile);

        $this->assertEmpty($bindings);
    }

    // ========================================
    // areBound() Tests - Phase 1 Critical
    // ========================================

    public function testAreBoundReturnsTrueWhenEntitiesAreConnected(): void
    {
        $this->session->bind($this->profile, $this->organization, 'member_of');

        $result = $this->session->areBound($this->profile, $this->organization);

        $this->assertTrue($result);
    }

    public function testAreBoundReturnsFalseWhenEntitiesNotConnected(): void
    {
        $result = $this->session->areBound($this->profile, $this->organization);

        $this->assertFalse($result);
    }

    public function testAreBoundWithSpecificType(): void
    {
        $this->session->bind($this->profile, $this->organization, 'member_of');
        $this->session->bind($this->profile, $this->organization, 'admin_of');

        // Should find member_of relationship
        $this->assertTrue($this->session->areBound($this->profile, $this->organization, 'member_of'));

        // Should find admin_of relationship
        $this->assertTrue($this->session->areBound($this->profile, $this->organization, 'admin_of'));

        // Should not find non-existent relationship type
        $this->assertFalse($this->session->areBound($this->profile, $this->organization, 'owner_of'));
    }

    public function testAreBoundWorksWithAdapterOnlyBindings(): void
    {
        // Create binding directly in adapter
        $binding = Binding::create(
            fromType: 'Profile',
            fromId: 'profile-123',
            toType: 'Organization',
            toId: 'org-456',
            type: 'member_of',
            metadata: []
        );
        $this->adapter->store($binding);

        $result = $this->session->areBound($this->profile, $this->organization, 'member_of');

        $this->assertTrue($result);
    }

    public function testAreBoundWorksInBothDirections(): void
    {
        $this->session->bind($this->profile, $this->organization, 'member_of');

        // Should work in the direction the binding was created
        $this->assertTrue($this->session->areBound($this->profile, $this->organization, 'member_of'));

        // Should not work in reverse direction (different relationship)
        $this->assertFalse($this->session->areBound($this->organization, $this->profile, 'member_of'));
    }

    // ========================================
    // Integration Tests - Multiple Methods
    // ========================================

    public function testPhase1MethodsWorkTogether(): void
    {
        // Create some bindings
        $this->session->bind($this->profile, $this->organization, 'member_of');
        $this->session->bind($this->profile, $this->team, 'member_of');

        // Verify entities are bound
        $this->assertTrue($this->session->areBound($this->profile, $this->organization, 'member_of'));

        // Find all bindings for profile
        $bindings = $this->session->findBindingsFor($this->profile);
        $this->assertCount(2, $bindings);

        // Unbind from organization
        $deletedCount = $this->session->unbindEntities($this->profile, $this->organization, 'member_of');
        $this->assertEquals(1, $deletedCount);

        // Verify entities are no longer bound
        $this->assertFalse($this->session->areBound($this->profile, $this->organization, 'member_of'));

        // Should still have binding to team
        $remainingBindings = $this->session->findBindingsFor($this->profile);
        $this->assertCount(1, $remainingBindings);
        $this->assertTrue($this->session->areBound($this->profile, $this->team, 'member_of'));
    }

    // ========================================
    // Phase 2 Important Methods Tests
    // ========================================

    // findBinding() Tests
    public function testFindBindingReturnsBindingById(): void
    {
        $binding = $this->session->bind($this->profile, $this->organization, 'member_of');

        $foundBinding = $this->session->findBinding($binding->getId());

        $this->assertNotNull($foundBinding);
        $this->assertEquals($binding->getId(), $foundBinding->getId());
        $this->assertEquals($binding->getType(), $foundBinding->getType());
    }

    public function testFindBindingReturnsNullForNonExistentBinding(): void
    {
        $result = $this->session->findBinding('non-existent-id');

        $this->assertNull($result);
    }

    public function testFindBindingWorksWithAdapterOnlyBinding(): void
    {
        // Create binding directly in adapter
        $binding = Binding::create(
            fromType: 'Profile',
            fromId: 'profile-123',
            toType: 'Organization',
            toId: 'org-456',
            type: 'member_of',
            metadata: []
        );
        $this->adapter->store($binding);

        $foundBinding = $this->session->findBinding($binding->getId());

        $this->assertNotNull($foundBinding);
        $this->assertEquals($binding->getId(), $foundBinding->getId());
    }

    // findBindingsBetween() Tests
    public function testFindBindingsBetweenReturnsAllBindings(): void
    {
        $binding1 = $this->session->bind($this->profile, $this->organization, 'member_of');
        $binding2 = $this->session->bind($this->profile, $this->organization, 'admin_of');

        // Create binding in different direction (should not be included)
        $this->session->bind($this->organization, $this->profile, 'employs');

        $bindings = $this->session->findBindingsBetween($this->profile, $this->organization);

        $this->assertCount(2, $bindings);
        $bindingIds = array_map(fn ($b) => $b->getId(), $bindings);
        $this->assertContains($binding1->getId(), $bindingIds);
        $this->assertContains($binding2->getId(), $bindingIds);
    }

    public function testFindBindingsBetweenWithSpecificType(): void
    {
        $memberBinding = $this->session->bind($this->profile, $this->organization, 'member_of');
        $this->session->bind($this->profile, $this->organization, 'admin_of');

        $bindings = $this->session->findBindingsBetween($this->profile, $this->organization, 'member_of');

        $this->assertCount(1, $bindings);
        $this->assertEquals($memberBinding->getId(), $bindings[0]->getId());
    }

    public function testFindBindingsBetweenWithNoBindings(): void
    {
        $bindings = $this->session->findBindingsBetween($this->profile, $this->organization);

        $this->assertEmpty($bindings);
    }

    // hasBindings() Tests
    public function testHasBindingsReturnsTrueWhenEntityHasBindings(): void
    {
        $this->session->bind($this->profile, $this->organization, 'member_of');

        $result = $this->session->hasBindings($this->profile);

        $this->assertTrue($result);
    }

    public function testHasBindingsReturnsFalseWhenEntityHasNoBindings(): void
    {
        $result = $this->session->hasBindings($this->profile);

        $this->assertFalse($result);
    }

    public function testHasBindingsWorksWithAdapterOnlyBindings(): void
    {
        // Create binding directly in adapter
        $binding = Binding::create(
            fromType: 'Profile',
            fromId: 'profile-123',
            toType: 'Organization',
            toId: 'org-456',
            type: 'member_of',
            metadata: []
        );
        $this->adapter->store($binding);

        $result = $this->session->hasBindings($this->profile);

        $this->assertTrue($result);
    }

    // countBindingsFor() Tests
    public function testCountBindingsForReturnsCorrectCount(): void
    {
        $this->session->bind($this->profile, $this->organization, 'member_of');
        $this->session->bind($this->profile, $this->team, 'member_of');
        $this->session->bind($this->organization, $this->profile, 'employs');

        $count = $this->session->countBindingsFor($this->profile);

        $this->assertEquals(3, $count);
    }

    public function testCountBindingsForWithSpecificType(): void
    {
        $this->session->bind($this->profile, $this->organization, 'member_of');
        $this->session->bind($this->profile, $this->team, 'member_of');
        $this->session->bind($this->profile, $this->organization, 'admin_of');

        $count = $this->session->countBindingsFor($this->profile, 'member_of');

        $this->assertEquals(2, $count);
    }

    public function testCountBindingsForWithNoBindings(): void
    {
        $count = $this->session->countBindingsFor($this->profile);

        $this->assertEquals(0, $count);
    }

    // unbindEntity() Tests
    public function testUnbindEntityRemovesAllEntityBindings(): void
    {
        $binding1 = $this->session->bind($this->profile, $this->organization, 'member_of');
        $binding2 = $this->session->bind($this->profile, $this->team, 'member_of');
        $binding3 = $this->session->bind($this->organization, $this->profile, 'employs');

        // Create binding not involving profile (should remain)
        $binding4 = $this->session->bind($this->organization, $this->team, 'sponsors');

        $deletedCount = $this->session->unbindEntity($this->profile);

        $this->assertEquals(3, $deletedCount);

        // Verify profile bindings are removed
        $this->assertNull($this->adapter->find($binding1->getId()));
        $this->assertNull($this->adapter->find($binding2->getId()));
        $this->assertNull($this->adapter->find($binding3->getId()));

        // Verify unrelated binding remains
        $this->assertNotNull($this->adapter->find($binding4->getId()));
    }

    public function testUnbindEntityWithNoBindings(): void
    {
        $deletedCount = $this->session->unbindEntity($this->profile);

        $this->assertEquals(0, $deletedCount);
    }

    public function testUnbindEntityWorksWithAdapterOnlyBindings(): void
    {
        // Create binding directly in adapter
        $binding = Binding::create(
            fromType: 'Profile',
            fromId: 'profile-123',
            toType: 'Organization',
            toId: 'org-456',
            type: 'member_of',
            metadata: []
        );
        $this->adapter->store($binding);

        $deletedCount = $this->session->unbindEntity($this->profile);

        $this->assertEquals(1, $deletedCount);
        $this->assertNull($this->adapter->find($binding->getId()));
    }

    // ========================================
    // Phase 3 Convenience Methods Tests
    // ========================================

    // bindMany() Tests
    public function testBindManyCreatesMultipleBindings(): void
    {
        $bindingSpecs = [
            [
                'from' => $this->profile,
                'to' => $this->organization,
                'type' => 'member_of',
                'metadata' => ['role' => 'developer'],
            ],
            [
                'from' => $this->profile,
                'to' => $this->team,
                'type' => 'member_of',
                'metadata' => ['role' => 'lead'],
            ],
            [
                'from' => $this->organization,
                'to' => $this->profile,
                'type' => 'employs',
            ],
        ];

        $createdBindings = $this->session->bindMany($bindingSpecs);

        $this->assertCount(3, $createdBindings);

        // Verify first binding
        $this->assertEquals('member_of', $createdBindings[0]->getType());
        $this->assertEquals(['role' => 'developer'], $createdBindings[0]->getMetadata());

        // Verify second binding
        $this->assertEquals('member_of', $createdBindings[1]->getType());
        $this->assertEquals(['role' => 'lead'], $createdBindings[1]->getMetadata());

        // Verify third binding (no metadata)
        $this->assertEquals('employs', $createdBindings[2]->getType());
        $this->assertEmpty($createdBindings[2]->getMetadata());

        // Verify all bindings are tracked in session
        $this->assertCount(3, $this->session->getTrackedBindings());
    }

    public function testBindManyWithEmptyArray(): void
    {
        $createdBindings = $this->session->bindMany([]);

        $this->assertEmpty($createdBindings);
        $this->assertEmpty($this->session->getTrackedBindings());
    }

    public function testBindManyWithMissingMetadata(): void
    {
        $bindingSpecs = [
            [
                'from' => $this->profile,
                'to' => $this->organization,
                'type' => 'member_of',
                // metadata key is optional
            ],
        ];

        $createdBindings = $this->session->bindMany($bindingSpecs);

        $this->assertCount(1, $createdBindings);
        $this->assertEmpty($createdBindings[0]->getMetadata());
    }

    // updateMetadata() Tests
    public function testUpdateMetadataMergesWithExistingMetadata(): void
    {
        $binding = $this->session->bind(
            $this->profile,
            $this->organization,
            'member_of',
            ['role' => 'developer', 'level' => 'junior']
        );

        $updatedBinding = $this->session->updateMetadata($binding->getId(), [
            'level' => 'senior',
            'department' => 'engineering',
        ]);

        $expectedMetadata = [
            'role' => 'developer',
            'level' => 'senior',  // Updated
            'department' => 'engineering',  // Added
        ];

        $this->assertEquals($expectedMetadata, $updatedBinding->getMetadata());
        $this->assertEquals($binding->getId(), $updatedBinding->getId());
    }

    public function testUpdateMetadataThrowsExceptionForNonExistentBinding(): void
    {
        $this->expectException(\EdgeBinder\Exception\BindingNotFoundException::class);

        $this->session->updateMetadata('non-existent-id', ['key' => 'value']);
    }

    public function testUpdateMetadataWorksWithAdapterOnlyBinding(): void
    {
        // Create binding directly in adapter
        $binding = Binding::create(
            fromType: 'Profile',
            fromId: 'profile-123',
            toType: 'Organization',
            toId: 'org-456',
            type: 'member_of',
            metadata: ['role' => 'developer']
        );
        $this->adapter->store($binding);

        $updatedBinding = $this->session->updateMetadata($binding->getId(), [
            'level' => 'senior',
        ]);

        $expectedMetadata = [
            'role' => 'developer',
            'level' => 'senior',
        ];

        $this->assertEquals($expectedMetadata, $updatedBinding->getMetadata());
    }

    // replaceMetadata() Tests
    public function testReplaceMetadataReplacesAllMetadata(): void
    {
        $binding = $this->session->bind(
            $this->profile,
            $this->organization,
            'member_of',
            ['role' => 'developer', 'level' => 'junior', 'department' => 'engineering']
        );

        $updatedBinding = $this->session->replaceMetadata($binding->getId(), [
            'position' => 'team_lead',
            'salary_grade' => 'L5',
        ]);

        $expectedMetadata = [
            'position' => 'team_lead',
            'salary_grade' => 'L5',
        ];

        $this->assertEquals($expectedMetadata, $updatedBinding->getMetadata());
        $this->assertEquals($binding->getId(), $updatedBinding->getId());
    }

    public function testReplaceMetadataThrowsExceptionForNonExistentBinding(): void
    {
        $this->expectException(\EdgeBinder\Exception\BindingNotFoundException::class);

        $this->session->replaceMetadata('non-existent-id', ['key' => 'value']);
    }

    public function testReplaceMetadataWithEmptyArray(): void
    {
        $binding = $this->session->bind(
            $this->profile,
            $this->organization,
            'member_of',
            ['role' => 'developer', 'level' => 'junior']
        );

        $updatedBinding = $this->session->replaceMetadata($binding->getId(), []);

        $this->assertEmpty($updatedBinding->getMetadata());
    }

    // getMetadata() Tests
    public function testGetMetadataReturnsBindingMetadata(): void
    {
        $metadata = ['role' => 'developer', 'level' => 'senior', 'active' => true];
        $binding = $this->session->bind($this->profile, $this->organization, 'member_of', $metadata);

        $result = $this->session->getMetadata($binding->getId());

        $this->assertEquals($metadata, $result);
    }

    public function testGetMetadataThrowsExceptionForNonExistentBinding(): void
    {
        $this->expectException(\EdgeBinder\Exception\BindingNotFoundException::class);

        $this->session->getMetadata('non-existent-id');
    }

    public function testGetMetadataWorksWithAdapterOnlyBinding(): void
    {
        $metadata = ['role' => 'developer', 'active' => true];

        // Create binding directly in adapter
        $binding = Binding::create(
            fromType: 'Profile',
            fromId: 'profile-123',
            toType: 'Organization',
            toId: 'org-456',
            type: 'member_of',
            metadata: $metadata
        );
        $this->adapter->store($binding);

        $result = $this->session->getMetadata($binding->getId());

        $this->assertEquals($metadata, $result);
    }

    public function testGetMetadataWithEmptyMetadata(): void
    {
        $binding = $this->session->bind($this->profile, $this->organization, 'member_of', []);

        $result = $this->session->getMetadata($binding->getId());

        $this->assertEmpty($result);
    }

    // ========================================
    // Phase 3 Integration Tests
    // ========================================

    public function testPhase3MethodsWorkTogether(): void
    {
        // Create multiple bindings with bindMany
        $bindingSpecs = [
            [
                'from' => $this->profile,
                'to' => $this->organization,
                'type' => 'member_of',
                'metadata' => ['role' => 'developer'],
            ],
            [
                'from' => $this->profile,
                'to' => $this->team,
                'type' => 'member_of',
                'metadata' => ['role' => 'lead'],
            ],
        ];

        $createdBindings = $this->session->bindMany($bindingSpecs);
        $this->assertCount(2, $createdBindings);

        $firstBinding = $createdBindings[0];
        $secondBinding = $createdBindings[1];

        // Test getMetadata
        $metadata1 = $this->session->getMetadata($firstBinding->getId());
        $this->assertEquals(['role' => 'developer'], $metadata1);

        // Test updateMetadata (merge)
        $updatedBinding = $this->session->updateMetadata($firstBinding->getId(), [
            'level' => 'senior',
            'active' => true,
        ]);

        $expectedMetadata = ['role' => 'developer', 'level' => 'senior', 'active' => true];
        $this->assertEquals($expectedMetadata, $updatedBinding->getMetadata());

        // Verify getMetadata reflects the update
        $updatedMetadata = $this->session->getMetadata($firstBinding->getId());
        $this->assertEquals($expectedMetadata, $updatedMetadata);

        // Test replaceMetadata (complete replacement)
        $replacedBinding = $this->session->replaceMetadata($secondBinding->getId(), [
            'position' => 'team_lead',
            'department' => 'engineering',
        ]);

        $this->assertEquals(['position' => 'team_lead', 'department' => 'engineering'], $replacedBinding->getMetadata());

        // Verify getMetadata reflects the replacement
        $replacedMetadata = $this->session->getMetadata($secondBinding->getId());
        $this->assertEquals(['position' => 'team_lead', 'department' => 'engineering'], $replacedMetadata);
    }

    public function testAllPhasesWorkTogether(): void
    {
        // Phase 3: Create multiple bindings
        $bindingSpecs = [
            ['from' => $this->profile, 'to' => $this->organization, 'type' => 'member_of'],
            ['from' => $this->profile, 'to' => $this->team, 'type' => 'member_of'],
        ];
        $bindings = $this->session->bindMany($bindingSpecs);

        // Phase 1: Verify relationships exist
        $this->assertTrue($this->session->areBound($this->profile, $this->organization, 'member_of'));
        $this->assertCount(2, $this->session->findBindingsFor($this->profile));

        // Phase 2: Check entity has bindings and count them
        $this->assertTrue($this->session->hasBindings($this->profile));
        $this->assertEquals(2, $this->session->countBindingsFor($this->profile, 'member_of'));

        // Phase 3: Update metadata on first binding
        $this->session->updateMetadata($bindings[0]->getId(), ['updated' => true]);
        $metadata = $this->session->getMetadata($bindings[0]->getId());
        $this->assertEquals(['updated' => true], $metadata);

        // Phase 1: Unbind entities
        $deletedCount = $this->session->unbindEntities($this->profile, $this->organization, 'member_of');
        $this->assertEquals(1, $deletedCount);

        // Verify final state
        $this->assertFalse($this->session->areBound($this->profile, $this->organization, 'member_of'));
        $this->assertEquals(1, $this->session->countBindingsFor($this->profile));
    }
}

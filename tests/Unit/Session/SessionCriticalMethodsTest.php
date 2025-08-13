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
}

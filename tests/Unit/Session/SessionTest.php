<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Unit\Session;

use EdgeBinder\Persistence\InMemory\InMemoryAdapter;
use EdgeBinder\Session\Session;
use EdgeBinder\Tests\Integration\Session\TestEntity;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Session class.
 */
class SessionTest extends TestCase
{
    private Session $session;
    private InMemoryAdapter $adapter;
    private TestEntity $fromEntity;
    private TestEntity $toEntity;

    protected function setUp(): void
    {
        $this->adapter = new InMemoryAdapter();
        $this->session = new Session($this->adapter);
        $this->fromEntity = new TestEntity('user-123', 'User');
        $this->toEntity = new TestEntity('org-456', 'Organization');
    }

    public function testInitialState(): void
    {
        $this->assertFalse($this->session->isDirty());
        $this->assertEmpty($this->session->getPendingOperations());
        $this->assertEmpty($this->session->getTrackedBindings());
    }

    public function testAutoFlushSession(): void
    {
        $autoFlushSession = new Session($this->adapter, autoFlush: true);
        // We can test auto-flush behavior by checking if operations are immediately flushed
        $autoFlushSession->bind(
            from: $this->fromEntity,
            to: $this->toEntity,
            type: 'member_of'
        );

        // With auto-flush, operations should be flushed immediately
        $this->assertFalse($autoFlushSession->isDirty());
    }

    public function testBindOperation(): void
    {
        $binding = $this->session->bind(
            from: $this->fromEntity,
            to: $this->toEntity,
            type: 'member_of'
        );

        $this->assertNotNull($binding);
        $this->assertEquals($this->fromEntity->getId(), $binding->getFromId());
        $this->assertEquals($this->toEntity->getId(), $binding->getToId());
        $this->assertEquals('member_of', $binding->getType());

        $this->assertTrue($this->session->isDirty());
        $this->assertCount(1, $this->session->getPendingOperations());
        $this->assertCount(1, $this->session->getTrackedBindings());
    }

    public function testUnbindOperation(): void
    {
        // First create a binding
        $binding = $this->session->bind(
            from: $this->fromEntity,
            to: $this->toEntity,
            type: 'member_of'
        );

        $this->assertCount(1, $this->session->getTrackedBindings());

        // Then unbind it using the binding ID
        $result = $this->session->unbind($binding->getId());

        $this->assertTrue($result);
        $this->assertTrue($this->session->isDirty());
        $this->assertCount(2, $this->session->getPendingOperations()); // bind + unbind
        $this->assertCount(0, $this->session->getTrackedBindings()); // should be removed from cache
    }

    public function testFlushOperations(): void
    {
        $this->session->bind(
            from: $this->fromEntity,
            to: $this->toEntity,
            type: 'member_of'
        );

        $this->assertTrue($this->session->isDirty());

        $this->session->flush();

        $this->assertFalse($this->session->isDirty());
        // Operations should be cleared after flush
        $this->assertEmpty($this->session->getPendingOperations());
        // But tracked bindings should remain
        $this->assertCount(1, $this->session->getTrackedBindings());
    }

    public function testClearSession(): void
    {
        $this->session->bind(
            from: $this->fromEntity,
            to: $this->toEntity,
            type: 'member_of'
        );

        $this->assertTrue($this->session->isDirty());
        $this->assertCount(1, $this->session->getTrackedBindings());

        $this->session->clear();

        $this->assertFalse($this->session->isDirty());
        $this->assertEmpty($this->session->getPendingOperations());
        $this->assertEmpty($this->session->getTrackedBindings());
    }

    public function testCloseSession(): void
    {
        $this->session->bind(
            from: $this->fromEntity,
            to: $this->toEntity,
            type: 'member_of'
        );

        $this->assertTrue($this->session->isDirty());

        $this->session->close();

        // Close should flush and then clear
        $this->assertFalse($this->session->isDirty());
        $this->assertEmpty($this->session->getPendingOperations());
        $this->assertEmpty($this->session->getTrackedBindings());
    }

    public function testAutoFlushOnBind(): void
    {
        $autoFlushSession = new Session($this->adapter, autoFlush: true);

        $autoFlushSession->bind(
            from: $this->fromEntity,
            to: $this->toEntity,
            type: 'member_of'
        );

        // With auto-flush, operations should be flushed immediately
        $this->assertFalse($autoFlushSession->isDirty());
        $this->assertEmpty($autoFlushSession->getPendingOperations());
        $this->assertCount(1, $autoFlushSession->getTrackedBindings());
    }

    public function testAutoFlushOnUnbind(): void
    {
        $autoFlushSession = new Session($this->adapter, autoFlush: true);

        $binding = $autoFlushSession->bind(
            from: $this->fromEntity,
            to: $this->toEntity,
            type: 'member_of'
        );

        $autoFlushSession->unbind($binding->getId());

        // With auto-flush, unbind should be flushed immediately
        $this->assertFalse($autoFlushSession->isDirty());
        $this->assertEmpty($autoFlushSession->getPendingOperations());
        $this->assertEmpty($autoFlushSession->getTrackedBindings());
    }

    public function testQueryBuilder(): void
    {
        $queryBuilder = $this->session->query();

        $this->assertInstanceOf(\EdgeBinder\Session\SessionAwareQueryBuilder::class, $queryBuilder);
    }

    public function testMultipleBindings(): void
    {
        $binding1 = $this->session->bind(
            from: $this->fromEntity,
            to: $this->toEntity,
            type: 'member_of'
        );

        $binding2 = $this->session->bind(
            from: $this->fromEntity,
            to: $this->toEntity,
            type: 'admin_of'
        );

        $this->assertCount(2, $this->session->getTrackedBindings());
        $this->assertCount(2, $this->session->getPendingOperations());
        $this->assertTrue($this->session->isDirty());

        $this->assertNotEquals($binding1->getId(), $binding2->getId());
    }

    public function testUnbindNonExistentBinding(): void
    {
        // Try to unbind a binding that doesn't exist
        $result = $this->session->unbind('non-existent-binding-id');

        $this->assertFalse($result);
        $this->assertFalse($this->session->isDirty());
        $this->assertEmpty($this->session->getPendingOperations());
        $this->assertEmpty($this->session->getTrackedBindings());
    }

    public function testFlushWithPendingOperations(): void
    {
        // Create some operations to exercise waitForConsistency
        $binding1 = $this->session->bind(
            from: $this->fromEntity,
            to: $this->toEntity,
            type: 'member_of'
        );

        $this->session->unbind($binding1->getId());

        $this->session->bind(
            from: $this->toEntity,
            to: $this->fromEntity,
            type: 'admin_of'
        );

        // Should have pending operations
        $this->assertTrue($this->session->isDirty());
        $this->assertCount(3, $this->session->getPendingOperations()); // bind + unbind + bind

        // Flush should process all operations and call waitForConsistency
        $this->session->flush();

        // After flush, no pending operations
        $this->assertFalse($this->session->isDirty());
        $this->assertEmpty($this->session->getPendingOperations());

        // But tracked bindings should remain (only binding2 since binding1 was unbound)
        $this->assertCount(1, $this->session->getTrackedBindings());
    }

    public function testUnbindWithAdapterException(): void
    {
        // Create a binding first
        $binding = $this->session->bind(
            from: $this->fromEntity,
            to: $this->toEntity,
            type: 'member_of'
        );

        // Manually remove from adapter to simulate adapter exception during unbind
        $this->adapter->delete($binding->getId());

        // Unbind should still work (removes from cache even if adapter delete fails)
        $result = $this->session->unbind($binding->getId());

        $this->assertTrue($result);
        $this->assertCount(0, $this->session->getTrackedBindings());
        $this->assertTrue($this->session->isDirty()); // Should have recorded the delete operation
    }
}

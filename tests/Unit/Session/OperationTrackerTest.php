<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Unit\Session;

use EdgeBinder\Binding;
use EdgeBinder\Session\Operation;
use EdgeBinder\Session\OperationTracker;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for OperationTracker class.
 */
class OperationTrackerTest extends TestCase
{
    private OperationTracker $tracker;
    private Binding $binding;

    protected function setUp(): void
    {
        $this->tracker = new OperationTracker();
        $this->binding = Binding::create(
            fromType: 'User',
            fromId: 'user-123',
            toType: 'Organization',
            toId: 'org-456',
            type: 'member_of'
        );
    }

    public function testInitialState(): void
    {
        $this->assertFalse($this->tracker->hasPendingOperations());
        $this->assertEmpty($this->tracker->getPendingOperations());
        $this->assertEmpty($this->tracker->getCompletedOperations());
        $this->assertEquals(0, $this->tracker->getTotalOperationCount());
    }

    public function testRecordCreateOperation(): void
    {
        $this->tracker->recordCreate($this->binding);

        $this->assertTrue($this->tracker->hasPendingOperations());
        $this->assertCount(1, $this->tracker->getPendingOperations());
        $this->assertEquals(1, $this->tracker->getTotalOperationCount());

        $operations = $this->tracker->getPendingOperations();
        $this->assertEquals(Operation::TYPE_CREATE, $operations[0]->getType());
        $this->assertSame($this->binding, $operations[0]->getBinding());
    }

    public function testRecordDeleteOperation(): void
    {
        $this->tracker->recordDelete($this->binding);

        $this->assertTrue($this->tracker->hasPendingOperations());
        $this->assertCount(1, $this->tracker->getPendingOperations());

        $operations = $this->tracker->getPendingOperations();
        $this->assertEquals(Operation::TYPE_DELETE, $operations[0]->getType());
        $this->assertSame($this->binding, $operations[0]->getBinding());
    }

    public function testRecordUpdateOperation(): void
    {
        $this->tracker->recordUpdate($this->binding);

        $this->assertTrue($this->tracker->hasPendingOperations());
        $this->assertCount(1, $this->tracker->getPendingOperations());

        $operations = $this->tracker->getPendingOperations();
        $this->assertEquals(Operation::TYPE_UPDATE, $operations[0]->getType());
        $this->assertSame($this->binding, $operations[0]->getBinding());
    }

    public function testMultipleOperations(): void
    {
        $binding2 = Binding::create(
            fromType: 'User',
            fromId: 'user-456',
            toType: 'Team',
            toId: 'team-789',
            type: 'admin_of'
        );

        $this->tracker->recordCreate($this->binding);
        $this->tracker->recordDelete($binding2);

        $this->assertTrue($this->tracker->hasPendingOperations());
        $this->assertCount(2, $this->tracker->getPendingOperations());
        $this->assertEquals(2, $this->tracker->getTotalOperationCount());
    }

    public function testMarkAllComplete(): void
    {
        $this->tracker->recordCreate($this->binding);
        $this->assertTrue($this->tracker->hasPendingOperations());

        $this->tracker->markAllComplete();

        $this->assertFalse($this->tracker->hasPendingOperations());
        $this->assertEmpty($this->tracker->getPendingOperations());
        $this->assertCount(1, $this->tracker->getCompletedOperations());
        $this->assertEquals(1, $this->tracker->getTotalOperationCount());
    }

    public function testMarkSpecificComplete(): void
    {
        $binding2 = Binding::create(
            fromType: 'User',
            fromId: 'user-456',
            toType: 'Team',
            toId: 'team-789',
            type: 'admin_of'
        );

        $this->tracker->recordCreate($this->binding);
        $this->tracker->recordDelete($binding2);
        $this->assertCount(2, $this->tracker->getPendingOperations());

        $operations = $this->tracker->getPendingOperations();
        $this->tracker->markComplete($operations[0]);

        $this->assertCount(1, $this->tracker->getPendingOperations());
        $this->assertCount(1, $this->tracker->getCompletedOperations());
        $this->assertEquals(2, $this->tracker->getTotalOperationCount());
    }

    public function testClearOperations(): void
    {
        $this->tracker->recordCreate($this->binding);
        $this->tracker->markAllComplete();

        $this->assertCount(1, $this->tracker->getCompletedOperations());
        $this->assertEquals(1, $this->tracker->getTotalOperationCount());

        $this->tracker->clear();

        $this->assertFalse($this->tracker->hasPendingOperations());
        $this->assertEmpty($this->tracker->getPendingOperations());
        $this->assertEmpty($this->tracker->getCompletedOperations());
        $this->assertEquals(0, $this->tracker->getTotalOperationCount());
    }
}

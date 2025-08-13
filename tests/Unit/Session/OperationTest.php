<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Unit\Session;

use EdgeBinder\Binding;
use EdgeBinder\Session\Operation;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Operation class.
 */
class OperationTest extends TestCase
{
    private Binding $binding;

    protected function setUp(): void
    {
        $this->binding = Binding::create(
            fromType: 'User',
            fromId: 'user-123',
            toType: 'Organization',
            toId: 'org-456',
            type: 'member_of'
        );
    }

    public function testCreateOperation(): void
    {
        $operation = Operation::create($this->binding);

        $this->assertEquals(Operation::TYPE_CREATE, $operation->getType());
        $this->assertSame($this->binding, $operation->getBinding());
        $this->assertEquals($this->binding->getId(), $operation->getBindingId());
        $this->assertInstanceOf(\DateTimeImmutable::class, $operation->getTimestamp());
    }

    public function testDeleteOperation(): void
    {
        $operation = Operation::delete($this->binding);

        $this->assertEquals(Operation::TYPE_DELETE, $operation->getType());
        $this->assertSame($this->binding, $operation->getBinding());
        $this->assertEquals($this->binding->getId(), $operation->getBindingId());
        $this->assertInstanceOf(\DateTimeImmutable::class, $operation->getTimestamp());
    }

    public function testUpdateOperation(): void
    {
        $operation = Operation::update($this->binding);

        $this->assertEquals(Operation::TYPE_UPDATE, $operation->getType());
        $this->assertSame($this->binding, $operation->getBinding());
        $this->assertEquals($this->binding->getId(), $operation->getBindingId());
        $this->assertInstanceOf(\DateTimeImmutable::class, $operation->getTimestamp());
    }

    public function testOperationTimestamp(): void
    {
        $before = new \DateTimeImmutable();
        $operation = Operation::create($this->binding);
        $after = new \DateTimeImmutable();

        $timestamp = $operation->getTimestamp();
        $this->assertGreaterThanOrEqual($before, $timestamp);
        $this->assertLessThanOrEqual($after, $timestamp);
    }

    public function testOperationConstants(): void
    {
        $this->assertEquals('create', Operation::TYPE_CREATE);
        $this->assertEquals('delete', Operation::TYPE_DELETE);
        $this->assertEquals('update', Operation::TYPE_UPDATE);
    }

    public function testOperationWithDifferentBindings(): void
    {
        $binding1 = Binding::create(
            fromType: 'User',
            fromId: 'user-1',
            toType: 'Organization',
            toId: 'org-1',
            type: 'member_of'
        );

        $binding2 = Binding::create(
            fromType: 'User',
            fromId: 'user-2',
            toType: 'Organization',
            toId: 'org-2',
            type: 'admin_of'
        );

        $operation1 = Operation::create($binding1);
        $operation2 = Operation::create($binding2);

        $this->assertNotEquals($operation1->getBindingId(), $operation2->getBindingId());
        $this->assertEquals($binding1->getId(), $operation1->getBindingId());
        $this->assertEquals($binding2->getId(), $operation2->getBindingId());
    }
}

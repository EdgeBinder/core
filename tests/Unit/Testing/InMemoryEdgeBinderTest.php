<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Unit\Testing;

use EdgeBinder\Contracts\BindingInterface;
use EdgeBinder\Contracts\EdgeBinderInterface;
use EdgeBinder\Contracts\EntityInterface;
use EdgeBinder\Exception\BindingNotFoundException;
use EdgeBinder\Tests\Support\InMemoryEdgeBinder;
use PHPUnit\Framework\TestCase;

/**
 * Test entity for InMemoryEdgeBinder tests.
 */
class TestEntity implements EntityInterface
{
    public function __construct(
        private readonly string $id,
        private readonly string $type
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }
}

/**
 * Tests for InMemoryEdgeBinder test implementation.
 */
class InMemoryEdgeBinderTest extends TestCase
{
    private InMemoryEdgeBinder $edgeBinder;
    private TestEntity $fromEntity;
    private TestEntity $toEntity;

    protected function setUp(): void
    {
        $this->edgeBinder = new InMemoryEdgeBinder();
        $this->fromEntity = new TestEntity('user-123', 'User');
        $this->toEntity = new TestEntity('project-456', 'Project');
    }

    public function testImplementsEdgeBinderInterface(): void
    {
        $this->assertInstanceOf(EdgeBinderInterface::class, $this->edgeBinder);
    }

    public function testBindCreatesBinding(): void
    {
        $binding = $this->edgeBinder->bind(
            $this->fromEntity,
            $this->toEntity,
            'has_access',
            ['level' => 'admin']
        );

        $this->assertInstanceOf(BindingInterface::class, $binding);
        $this->assertEquals('User', $binding->getFromType());
        $this->assertEquals('user-123', $binding->getFromId());
        $this->assertEquals('Project', $binding->getToType());
        $this->assertEquals('project-456', $binding->getToId());
        $this->assertEquals('has_access', $binding->getType());
        $this->assertEquals(['level' => 'admin'], $binding->getMetadata());
    }

    public function testGetBindingCountReturnsCorrectCount(): void
    {
        $this->assertEquals(0, $this->edgeBinder->getBindingCount());

        $this->edgeBinder->bind($this->fromEntity, $this->toEntity, 'test');
        $this->assertEquals(1, $this->edgeBinder->getBindingCount());

        $this->edgeBinder->bind($this->toEntity, $this->fromEntity, 'reverse');
        $this->assertEquals(2, $this->edgeBinder->getBindingCount());
    }

    public function testClearRemovesAllBindings(): void
    {
        $this->edgeBinder->bind($this->fromEntity, $this->toEntity, 'test1');
        $this->edgeBinder->bind($this->toEntity, $this->fromEntity, 'test2');

        $this->assertEquals(2, $this->edgeBinder->getBindingCount());

        $this->edgeBinder->clear();

        $this->assertEquals(0, $this->edgeBinder->getBindingCount());
        $this->assertFalse($this->edgeBinder->hasBindings());
    }

    public function testFindBinding(): void
    {
        $binding = $this->edgeBinder->bind($this->fromEntity, $this->toEntity, 'test');

        $found = $this->edgeBinder->findBinding($binding->getId());

        $this->assertSame($binding, $found);
    }

    public function testFindBindingReturnsNullForNonexistent(): void
    {
        $result = $this->edgeBinder->findBinding('nonexistent');

        $this->assertNull($result);
    }

    public function testAreBound(): void
    {
        $this->assertFalse($this->edgeBinder->areBound($this->fromEntity, $this->toEntity));

        $this->edgeBinder->bind($this->fromEntity, $this->toEntity, 'test');

        $this->assertTrue($this->edgeBinder->areBound($this->fromEntity, $this->toEntity));
        $this->assertTrue($this->edgeBinder->areBound($this->fromEntity, $this->toEntity, 'test'));
        $this->assertFalse($this->edgeBinder->areBound($this->fromEntity, $this->toEntity, 'other'));
    }

    public function testUnbind(): void
    {
        $binding = $this->edgeBinder->bind($this->fromEntity, $this->toEntity, 'test');

        $this->assertEquals(1, $this->edgeBinder->getBindingCount());

        $this->edgeBinder->unbind($binding->getId());

        $this->assertEquals(0, $this->edgeBinder->getBindingCount());
    }

    public function testUnbindThrowsExceptionForNonexistent(): void
    {
        $this->expectException(BindingNotFoundException::class);

        $this->edgeBinder->unbind('nonexistent');
    }

    public function testUnbindEntities(): void
    {
        $this->edgeBinder->bind($this->fromEntity, $this->toEntity, 'test1');
        $this->edgeBinder->bind($this->fromEntity, $this->toEntity, 'test2');
        $this->edgeBinder->bind($this->toEntity, $this->fromEntity, 'reverse');

        $this->assertEquals(3, $this->edgeBinder->getBindingCount());

        $removed = $this->edgeBinder->unbindEntities($this->fromEntity, $this->toEntity);

        $this->assertEquals(2, $removed);
        $this->assertEquals(1, $this->edgeBinder->getBindingCount());
    }

    public function testUnbindEntity(): void
    {
        $otherEntity = new TestEntity('other-789', 'Other');

        $this->edgeBinder->bind($this->fromEntity, $this->toEntity, 'test1');
        $this->edgeBinder->bind($this->fromEntity, $otherEntity, 'test2');
        $this->edgeBinder->bind($otherEntity, $this->fromEntity, 'test3');

        $this->assertEquals(3, $this->edgeBinder->getBindingCount());

        $removed = $this->edgeBinder->unbindEntity($this->fromEntity);

        $this->assertEquals(3, $removed);
        $this->assertEquals(0, $this->edgeBinder->getBindingCount());
    }

    public function testUpdateMetadata(): void
    {
        $binding = $this->edgeBinder->bind($this->fromEntity, $this->toEntity, 'test', ['key1' => 'value1']);

        $updated = $this->edgeBinder->updateMetadata($binding->getId(), ['key2' => 'value2']);

        $expected = ['key1' => 'value1', 'key2' => 'value2'];
        $this->assertEquals($expected, $updated->getMetadata());
    }

    public function testReplaceMetadata(): void
    {
        $binding = $this->edgeBinder->bind($this->fromEntity, $this->toEntity, 'test', ['key1' => 'value1']);

        $updated = $this->edgeBinder->replaceMetadata($binding->getId(), ['key2' => 'value2']);

        $this->assertEquals(['key2' => 'value2'], $updated->getMetadata());
    }

    public function testGetAllBindings(): void
    {
        $binding1 = $this->edgeBinder->bind($this->fromEntity, $this->toEntity, 'test1');
        $binding2 = $this->edgeBinder->bind($this->toEntity, $this->fromEntity, 'test2');

        $allBindings = $this->edgeBinder->getAllBindings();

        $this->assertCount(2, $allBindings);
        $this->assertContains($binding1, $allBindings);
        $this->assertContains($binding2, $allBindings);
    }

    public function testHasBindings(): void
    {
        $this->assertFalse($this->edgeBinder->hasBindings());

        $this->edgeBinder->bind($this->fromEntity, $this->toEntity, 'test');

        $this->assertTrue($this->edgeBinder->hasBindings());
    }
}

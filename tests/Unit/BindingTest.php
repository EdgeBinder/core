<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Unit;

use EdgeBinder\Binding;
use EdgeBinder\Contracts\BindingInterface;
use PHPUnit\Framework\TestCase;

class BindingTest extends TestCase
{
    private \DateTimeImmutable $fixedTime;

    protected function setUp(): void
    {
        $this->fixedTime = new \DateTimeImmutable('2024-01-15 10:30:00');
    }

    public function testImplementsBindingInterface(): void
    {
        $binding = $this->createTestBinding();

        $this->assertInstanceOf(BindingInterface::class, $binding);
    }

    public function testCreateBinding(): void
    {
        $binding = Binding::create(
            fromType: 'User',
            fromId: 'user-123',
            toType: 'Project',
            toId: 'project-456',
            type: 'has_access',
            metadata: ['access_level' => 'write']
        );

        $this->assertStringStartsWith('binding', $binding->getId());
        $this->assertEquals('User', $binding->getFromType());
        $this->assertEquals('user-123', $binding->getFromId());
        $this->assertEquals('Project', $binding->getToType());
        $this->assertEquals('project-456', $binding->getToId());
        $this->assertEquals('has_access', $binding->getType());
        $this->assertEquals(['access_level' => 'write'], $binding->getMetadata());
        $this->assertInstanceOf(\DateTimeImmutable::class, $binding->getCreatedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $binding->getUpdatedAt());
    }

    public function testCreateBindingWithoutMetadata(): void
    {
        $binding = Binding::create('User', 'user-1', 'Project', 'project-1', 'owns');

        $this->assertEquals([], $binding->getMetadata());
    }

    public function testConstructorSetsAllProperties(): void
    {
        $createdAt = new \DateTimeImmutable('2024-01-01 12:00:00');
        $updatedAt = new \DateTimeImmutable('2024-01-02 12:00:00');
        $metadata = ['key' => 'value'];

        $binding = new Binding(
            id: 'test-id',
            fromType: 'TypeA',
            fromId: 'id-a',
            toType: 'TypeB',
            toId: 'id-b',
            type: 'relates_to',
            metadata: $metadata,
            createdAt: $createdAt,
            updatedAt: $updatedAt
        );

        $this->assertEquals('test-id', $binding->getId());
        $this->assertEquals('TypeA', $binding->getFromType());
        $this->assertEquals('id-a', $binding->getFromId());
        $this->assertEquals('TypeB', $binding->getToType());
        $this->assertEquals('id-b', $binding->getToId());
        $this->assertEquals('relates_to', $binding->getType());
        $this->assertEquals($metadata, $binding->getMetadata());
        $this->assertSame($createdAt, $binding->getCreatedAt());
        $this->assertSame($updatedAt, $binding->getUpdatedAt());
    }

    public function testWithMetadataCreatesNewInstance(): void
    {
        $original = $this->createTestBinding();
        $newMetadata = ['new_key' => 'new_value'];

        $updated = $original->withMetadata($newMetadata);

        $this->assertNotSame($original, $updated);
        $this->assertEquals($original->getMetadata(), ['test' => 'data']);
        $this->assertEquals($updated->getMetadata(), $newMetadata);
        $this->assertEquals($original->getId(), $updated->getId());
        $this->assertEquals($original->getCreatedAt(), $updated->getCreatedAt());
        $this->assertNotEquals($original->getUpdatedAt(), $updated->getUpdatedAt());
    }

    public function testMergeMetadata(): void
    {
        $binding = $this->createTestBinding(['existing' => 'value', 'keep' => 'this']);
        $newMetadata = ['existing' => 'updated', 'new' => 'added'];

        $updated = $binding->mergeMetadata($newMetadata);

        $expected = ['existing' => 'updated', 'keep' => 'this', 'new' => 'added'];
        $this->assertEquals($expected, $updated->getMetadata());
    }

    public function testConnects(): void
    {
        $binding = $this->createTestBinding();

        $this->assertTrue($binding->connects('User', 'user-1', 'Project', 'project-1'));
        $this->assertFalse($binding->connects('User', 'user-2', 'Project', 'project-1'));
        $this->assertFalse($binding->connects('User', 'user-1', 'Project', 'project-2'));
        $this->assertFalse($binding->connects('Admin', 'user-1', 'Project', 'project-1'));
        $this->assertFalse($binding->connects('User', 'user-1', 'Task', 'project-1'));
    }

    public function testInvolves(): void
    {
        $binding = $this->createTestBinding();

        $this->assertTrue($binding->involves('User', 'user-1'));
        $this->assertTrue($binding->involves('Project', 'project-1'));
        $this->assertFalse($binding->involves('User', 'user-2'));
        $this->assertFalse($binding->involves('Project', 'project-2'));
        $this->assertFalse($binding->involves('Task', 'user-1'));
    }

    public function testGetMetadataValue(): void
    {
        $binding = $this->createTestBinding(['key1' => 'value1', 'key2' => null]);

        $this->assertEquals('value1', $binding->getMetadataValue('key1'));
        $this->assertNull($binding->getMetadataValue('key2'));
        $this->assertEquals('default', $binding->getMetadataValue('nonexistent', 'default'));
        $this->assertNull($binding->getMetadataValue('nonexistent'));
    }

    public function testHasMetadata(): void
    {
        $binding = $this->createTestBinding(['key1' => 'value1', 'key2' => null]);

        $this->assertTrue($binding->hasMetadata('key1'));
        $this->assertTrue($binding->hasMetadata('key2')); // null values still count as existing
        $this->assertFalse($binding->hasMetadata('nonexistent'));
    }

    public function testReverse(): void
    {
        $binding = $this->createTestBinding();

        $reversed = $binding->reverse();

        $this->assertNotEquals($binding->getId(), $reversed->getId());
        $this->assertEquals($binding->getFromType(), $reversed->getToType());
        $this->assertEquals($binding->getFromId(), $reversed->getToId());
        $this->assertEquals($binding->getToType(), $reversed->getFromType());
        $this->assertEquals($binding->getToId(), $reversed->getFromId());
        $this->assertEquals($binding->getType(), $reversed->getType());
        $this->assertEquals($binding->getMetadata(), $reversed->getMetadata());
        $this->assertEquals($binding->getCreatedAt(), $reversed->getCreatedAt());
    }

    public function testReverseWithDifferentType(): void
    {
        $binding = $this->createTestBinding();

        $reversed = $binding->reverse('owned_by');

        $this->assertEquals('owned_by', $reversed->getType());
    }

    public function testReverseWithDifferentMetadata(): void
    {
        $binding = $this->createTestBinding();
        $newMetadata = ['reverse' => 'metadata'];

        $reversed = $binding->reverse(null, $newMetadata);

        $this->assertEquals($newMetadata, $reversed->getMetadata());
    }

    public function testToArray(): void
    {
        $binding = new Binding(
            id: 'test-id',
            fromType: 'User',
            fromId: 'user-1',
            toType: 'Project',
            toId: 'project-1',
            type: 'owns',
            metadata: ['key' => 'value'],
            createdAt: $this->fixedTime,
            updatedAt: $this->fixedTime
        );

        $expected = [
            'id' => 'test-id',
            'fromType' => 'User',
            'fromId' => 'user-1',
            'toType' => 'Project',
            'toId' => 'project-1',
            'type' => 'owns',
            'metadata' => ['key' => 'value'],
            'createdAt' => '2024-01-15T10:30:00+00:00',
            'updatedAt' => '2024-01-15T10:30:00+00:00',
        ];

        $this->assertEquals($expected, $binding->toArray());
    }

    public function testFromArray(): void
    {
        $data = [
            'id' => 'test-id',
            'fromType' => 'User',
            'fromId' => 'user-1',
            'toType' => 'Project',
            'toId' => 'project-1',
            'type' => 'owns',
            'metadata' => ['key' => 'value'],
            'createdAt' => '2024-01-15T10:30:00+00:00',
            'updatedAt' => '2024-01-15T10:30:00+00:00',
        ];

        $binding = Binding::fromArray($data);

        $this->assertEquals('test-id', $binding->getId());
        $this->assertEquals('User', $binding->getFromType());
        $this->assertEquals('user-1', $binding->getFromId());
        $this->assertEquals('Project', $binding->getToType());
        $this->assertEquals('project-1', $binding->getToId());
        $this->assertEquals('owns', $binding->getType());
        $this->assertEquals(['key' => 'value'], $binding->getMetadata());
        $this->assertEquals($this->fixedTime, $binding->getCreatedAt());
        $this->assertEquals($this->fixedTime, $binding->getUpdatedAt());
    }

    public function testFromArrayWithoutMetadata(): void
    {
        $data = [
            'id' => 'test-id',
            'fromType' => 'User',
            'fromId' => 'user-1',
            'toType' => 'Project',
            'toId' => 'project-1',
            'type' => 'owns',
            'createdAt' => '2024-01-15T10:30:00+00:00',
            'updatedAt' => '2024-01-15T10:30:00+00:00',
        ];

        $binding = Binding::fromArray($data);

        $this->assertEquals([], $binding->getMetadata());
    }

    public function testFromArrayThrowsExceptionForMissingFields(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: id');

        Binding::fromArray(['from_type' => 'User']);
    }

    public function testRoundTripArrayConversion(): void
    {
        $original = $this->createTestBinding();
        $array = $original->toArray();
        $restored = Binding::fromArray($array);

        $this->assertEquals($original->getId(), $restored->getId());
        $this->assertEquals($original->getFromType(), $restored->getFromType());
        $this->assertEquals($original->getFromId(), $restored->getFromId());
        $this->assertEquals($original->getToType(), $restored->getToType());
        $this->assertEquals($original->getToId(), $restored->getToId());
        $this->assertEquals($original->getType(), $restored->getType());
        $this->assertEquals($original->getMetadata(), $restored->getMetadata());
        $this->assertEquals($original->getCreatedAt(), $restored->getCreatedAt());
        $this->assertEquals($original->getUpdatedAt(), $restored->getUpdatedAt());
    }

    public function testGeneratedIdsAreUnique(): void
    {
        $binding1 = Binding::create('User', 'user-1', 'Project', 'project-1', 'owns');
        $binding2 = Binding::create('User', 'user-1', 'Project', 'project-1', 'owns');

        $this->assertNotEquals($binding1->getId(), $binding2->getId());
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function createTestBinding(array $metadata = ['test' => 'data']): Binding
    {
        return new Binding(
            id: 'test-binding-id',
            fromType: 'User',
            fromId: 'user-1',
            toType: 'Project',
            toId: 'project-1',
            type: 'owns',
            metadata: $metadata,
            createdAt: $this->fixedTime,
            updatedAt: $this->fixedTime
        );
    }
}

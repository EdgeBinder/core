<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Unit\Testing;

use EdgeBinder\Contracts\EdgeBinderInterface;
use EdgeBinder\Contracts\EntityInterface;
use EdgeBinder\EdgeBinder;
use EdgeBinder\Tests\Support\EdgeBinderTestFactory;
use EdgeBinder\Tests\Support\InMemoryEdgeBinder;
use PHPUnit\Framework\TestCase;

/**
 * Test entity for factory tests.
 */
class FactoryTestEntity implements EntityInterface
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
 * Tests for EdgeBinderTestFactory.
 */
class EdgeBinderTestFactoryTest extends TestCase
{
    public function testCreateInMemoryReturnsInMemoryEdgeBinder(): void
    {
        $edgeBinder = EdgeBinderTestFactory::createInMemory();

        $this->assertInstanceOf(InMemoryEdgeBinder::class, $edgeBinder);
        $this->assertInstanceOf(EdgeBinderInterface::class, $edgeBinder);
    }

    public function testCreateWithInMemoryAdapterReturnsRealEdgeBinder(): void
    {
        $edgeBinder = EdgeBinderTestFactory::createWithInMemoryAdapter();

        $this->assertInstanceOf(EdgeBinder::class, $edgeBinder);
        $this->assertInstanceOf(EdgeBinderInterface::class, $edgeBinder);
    }

    public function testGetMockableInterfaceReturnsCorrectClass(): void
    {
        $interfaceClass = EdgeBinderTestFactory::getMockableInterface();

        $this->assertEquals(EdgeBinderInterface::class, $interfaceClass);

        // Test that we can create a mock with it
        $mock = $this->createMock(EdgeBinderInterface::class);
        $this->assertInstanceOf(EdgeBinderInterface::class, $mock);
    }

    public function testGetMockableClassReturnsCorrectClass(): void
    {
        $edgeBinderClass = EdgeBinderTestFactory::getMockableClass();

        $this->assertEquals(EdgeBinder::class, $edgeBinderClass);

        // Test that we can create a partial mock with it
        $partialMock = $this->getMockBuilder(EdgeBinder::class)
            ->setConstructorArgs([new \EdgeBinder\Persistence\InMemory\InMemoryAdapter()])
            ->onlyMethods(['bind'])
            ->getMock();

        $this->assertInstanceOf(EdgeBinder::class, $partialMock);
    }

    public function testCreateWithTestDataPopulatesBindings(): void
    {
        $entity1 = new FactoryTestEntity('1', 'User');
        $entity2 = new FactoryTestEntity('2', 'Project');
        $entity3 = new FactoryTestEntity('3', 'Organization');

        $testData = [
            [
                'from' => $entity1,
                'to' => $entity2,
                'type' => 'has_access',
                'metadata' => ['level' => 'admin'],
            ],
            [
                'from' => $entity2,
                'to' => $entity3,
                'type' => 'belongs_to',
            ],
        ];

        $edgeBinder = EdgeBinderTestFactory::createWithTestData($testData);

        $this->assertInstanceOf(InMemoryEdgeBinder::class, $edgeBinder);
        \assert($edgeBinder instanceof InMemoryEdgeBinder);
        $this->assertEquals(2, $edgeBinder->getBindingCount());
        $this->assertTrue($edgeBinder->areBound($entity1, $entity2, 'has_access'));
        $this->assertTrue($edgeBinder->areBound($entity2, $entity3, 'belongs_to'));
    }

    public function testCreateWithTestDataWorksWithEmptyArray(): void
    {
        $edgeBinder = EdgeBinderTestFactory::createWithTestData([]);

        $this->assertInstanceOf(InMemoryEdgeBinder::class, $edgeBinder);
        \assert($edgeBinder instanceof InMemoryEdgeBinder);
        $this->assertEquals(0, $edgeBinder->getBindingCount());
        $this->assertFalse($edgeBinder->hasBindings());
    }

    public function testFactoryMethodsReturnDifferentInstances(): void
    {
        $edgeBinder1 = EdgeBinderTestFactory::createInMemory();
        $edgeBinder2 = EdgeBinderTestFactory::createInMemory();

        $this->assertNotSame($edgeBinder1, $edgeBinder2);

        // Verify they are isolated
        $entity1 = new FactoryTestEntity('1', 'Test');
        $entity2 = new FactoryTestEntity('2', 'Test');

        $edgeBinder1->bind($entity1, $entity2, 'test');

        \assert($edgeBinder1 instanceof InMemoryEdgeBinder);
        \assert($edgeBinder2 instanceof InMemoryEdgeBinder);
        $this->assertEquals(1, $edgeBinder1->getBindingCount());
        $this->assertEquals(0, $edgeBinder2->getBindingCount());
    }

    public function testEdgeBinderInterfaceCanBeMocked(): void
    {
        // This test demonstrates that removing 'final' allows mocking
        $mock = $this->createMock(EdgeBinderInterface::class);

        $mock->expects($this->once())
            ->method('bind')
            ->willReturn($this->createMock(\EdgeBinder\Contracts\BindingInterface::class));

        $entity1 = new FactoryTestEntity('1', 'Test');
        $entity2 = new FactoryTestEntity('2', 'Test');

        $mock->bind($entity1, $entity2, 'test');
    }

    public function testRealEdgeBinderCanBeMocked(): void
    {
        // This test demonstrates that removing 'final' from EdgeBinder allows mocking
        $mock = $this->createMock(EdgeBinder::class);

        $mock->expects($this->once())
            ->method('bind')
            ->willReturn($this->createMock(\EdgeBinder\Contracts\BindingInterface::class));

        $entity1 = new FactoryTestEntity('1', 'Test');
        $entity2 = new FactoryTestEntity('2', 'Test');

        $mock->bind($entity1, $entity2, 'test');
    }
}

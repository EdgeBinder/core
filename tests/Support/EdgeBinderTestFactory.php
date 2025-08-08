<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Support;

use EdgeBinder\Contracts\EdgeBinderInterface;
use EdgeBinder\EdgeBinder;
use EdgeBinder\Persistence\InMemory\InMemoryAdapter;

/**
 * Factory for creating EdgeBinder test instances.
 *
 * This factory provides convenient static methods for creating different types
 * of EdgeBinder instances suitable for testing scenarios:
 *
 * - In-memory implementations for fast unit tests
 * - Mock objects for behavior verification
 * - Real EdgeBinder instances with test adapters
 *
 * Usage examples:
 * ```php
 * // Fast in-memory implementation
 * $edgeBinder = EdgeBinderTestFactory::createInMemory();
 *
 * // PHPUnit mock for behavior testing
 * $mock = EdgeBinderTestFactory::createMock($this);
 * $mock->expects($this->once())->method('bind');
 *
 * // Real EdgeBinder with in-memory adapter
 * $edgeBinder = EdgeBinderTestFactory::createWithInMemoryAdapter();
 * ```
 */
class EdgeBinderTestFactory
{
    /**
     * Create an in-memory EdgeBinder implementation.
     *
     * This creates an InMemoryEdgeBinder instance that implements the full
     * EdgeBinderInterface without requiring any external dependencies.
     * Perfect for fast unit tests.
     *
     * @return EdgeBinderInterface Fast in-memory implementation
     */
    public static function createInMemory(): EdgeBinderInterface
    {
        return new InMemoryEdgeBinder();
    }

    /**
     * Create a real EdgeBinder instance with an in-memory adapter.
     *
     * This creates the actual EdgeBinder class but uses an InMemoryAdapter
     * for storage. Useful when you need to test the real EdgeBinder behavior
     * but don't want external dependencies.
     *
     * @return EdgeBinder Real EdgeBinder with in-memory storage
     */
    public static function createWithInMemoryAdapter(): EdgeBinder
    {
        return new EdgeBinder(new InMemoryAdapter());
    }

    /**
     * Create a PHPUnit mock of EdgeBinderInterface.
     *
     * Note: This method is provided for convenience, but you can also create
     * mocks directly in your test methods using $this->createMock().
     *
     * @return string The interface class name for creating mocks
     */
    public static function getMockableInterface(): string
    {
        return EdgeBinderInterface::class;
    }

    /**
     * Get the EdgeBinder class name for creating partial mocks.
     *
     * Note: This method is provided for convenience, but you can also create
     * partial mocks directly in your test methods.
     *
     * @return string The EdgeBinder class name for creating partial mocks
     */
    public static function getMockableClass(): string
    {
        return EdgeBinder::class;
    }

    /**
     * Create an in-memory EdgeBinder with pre-populated test data.
     *
     * This creates an InMemoryEdgeBinder and populates it with common test
     * data patterns. Useful for tests that need existing relationships.
     *
     * @param array<array{from: object, to: object, type: string, metadata?: array<string, mixed>}> $bindings Test bindings to create
     *
     * @return EdgeBinderInterface In-memory implementation with test data
     */
    public static function createWithTestData(array $bindings = []): EdgeBinderInterface
    {
        $edgeBinder = new InMemoryEdgeBinder();

        foreach ($bindings as $bindingSpec) {
            $metadata = $bindingSpec['metadata'] ?? [];
            $edgeBinder->bind(
                from: $bindingSpec['from'],
                to: $bindingSpec['to'],
                type: $bindingSpec['type'],
                metadata: $metadata
            );
        }

        return $edgeBinder;
    }
}

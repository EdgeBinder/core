<?php

declare(strict_types=1);

namespace EdgeBinder\Testing;

use EdgeBinder\Binding;
use EdgeBinder\Contracts\BindingInterface;
use EdgeBinder\Contracts\EdgeBinderInterface;
use EdgeBinder\Contracts\PersistenceAdapterInterface;
use EdgeBinder\Contracts\QueryBuilderInterface;
use EdgeBinder\Exception\BindingNotFoundException;
use EdgeBinder\Persistence\InMemory\InMemoryAdapter;
use EdgeBinder\Query\BindingQueryBuilder;

/**
 * In-memory EdgeBinder implementation for fast unit testing.
 *
 * This implementation provides a complete EdgeBinder interface implementation
 * that stores all data in memory without requiring any external dependencies.
 * It's designed specifically for unit testing scenarios where you need:
 *
 * - Fast test execution (no I/O operations)
 * - Isolated test environments (no shared state)
 * - Easy setup and teardown
 * - Predictable behavior
 *
 * Features:
 * - Complete EdgeBinderInterface implementation
 * - Built-in entity extraction using standard patterns
 * - Metadata validation and normalization
 * - Helper methods for test assertions
 * - Automatic cleanup capabilities
 *
 * Usage in tests:
 * ```php
 * $edgeBinder = new InMemoryEdgeBinder();
 * $service = new MyService($edgeBinder);
 *
 * // Test your service logic
 * $service->createRelationship($user, $project);
 *
 * // Assert using helper methods
 * $this->assertEquals(1, $edgeBinder->getBindingCount());
 * ```
 */
class InMemoryEdgeBinder implements EdgeBinderInterface
{
    private InMemoryAdapter $adapter;

    public function __construct()
    {
        $this->adapter = new InMemoryAdapter();
    }

    public function bind(
        object $from,
        object $to,
        string $type,
        array $metadata = []
    ): BindingInterface {
        // Extract entity information using the adapter
        $fromType = $this->adapter->extractEntityType($from);
        $fromId = $this->adapter->extractEntityId($from);
        $toType = $this->adapter->extractEntityType($to);
        $toId = $this->adapter->extractEntityId($to);

        // Validate and normalize metadata
        $normalizedMetadata = $this->adapter->validateAndNormalizeMetadata($metadata);

        // Create the binding
        $binding = Binding::create(
            fromType: $fromType,
            fromId: $fromId,
            toType: $toType,
            toId: $toId,
            type: $type,
            metadata: $normalizedMetadata
        );

        // Store the binding
        $this->adapter->store($binding);

        return $binding;
    }

    public function unbind(string $bindingId): void
    {
        $binding = $this->adapter->find($bindingId);
        if (null === $binding) {
            throw new BindingNotFoundException("Binding with ID '{$bindingId}' not found");
        }

        $this->adapter->delete($bindingId);
    }

    public function unbindEntities(object $from, object $to, ?string $type = null): int
    {
        $fromType = $this->adapter->extractEntityType($from);
        $fromId = $this->adapter->extractEntityId($from);
        $toType = $this->adapter->extractEntityType($to);
        $toId = $this->adapter->extractEntityId($to);

        $bindings = $this->adapter->findBetweenEntities($fromType, $fromId, $toType, $toId);

        $deletedCount = 0;
        foreach ($bindings as $binding) {
            if (null === $type || $binding->getType() === $type) {
                $this->adapter->delete($binding->getId());
                ++$deletedCount;
            }
        }

        return $deletedCount;
    }

    public function unbindEntity(object $entity): int
    {
        $entityType = $this->adapter->extractEntityType($entity);
        $entityId = $this->adapter->extractEntityId($entity);

        $bindings = $this->adapter->findByEntity($entityType, $entityId);

        foreach ($bindings as $binding) {
            $this->adapter->delete($binding->getId());
        }

        return count($bindings);
    }

    public function query(): QueryBuilderInterface
    {
        return new BindingQueryBuilder($this->adapter);
    }

    public function findBinding(string $bindingId): ?BindingInterface
    {
        return $this->adapter->find($bindingId);
    }

    public function findBindingsFor(object $entity): array
    {
        $entityType = $this->adapter->extractEntityType($entity);
        $entityId = $this->adapter->extractEntityId($entity);

        return $this->adapter->findByEntity($entityType, $entityId);
    }

    public function findBindingsBetween(object $from, object $to, ?string $type = null): array
    {
        $fromType = $this->adapter->extractEntityType($from);
        $fromId = $this->adapter->extractEntityId($from);
        $toType = $this->adapter->extractEntityType($to);
        $toId = $this->adapter->extractEntityId($to);

        $bindings = $this->adapter->findBetweenEntities($fromType, $fromId, $toType, $toId);

        if (null === $type) {
            return $bindings;
        }

        return array_filter($bindings, fn (BindingInterface $binding) => $binding->getType() === $type);
    }

    public function areBound(object $from, object $to, ?string $type = null): bool
    {
        $bindings = $this->findBindingsBetween($from, $to, $type);

        return count($bindings) > 0;
    }

    public function updateMetadata(string $bindingId, array $metadata): BindingInterface
    {
        $binding = $this->adapter->find($bindingId);
        if (null === $binding) {
            throw new BindingNotFoundException("Binding with ID '{$bindingId}' not found");
        }

        // Validate new metadata
        $normalizedMetadata = $this->adapter->validateAndNormalizeMetadata($metadata);

        // Merge with existing metadata
        $currentMetadata = $binding->getMetadata();
        $mergedMetadata = array_merge($currentMetadata, $normalizedMetadata);

        // Update the binding
        $updatedBinding = $binding->withMetadata($mergedMetadata);
        $this->adapter->updateMetadata($bindingId, $mergedMetadata);

        return $updatedBinding;
    }

    public function replaceMetadata(string $bindingId, array $metadata): BindingInterface
    {
        $binding = $this->adapter->find($bindingId);
        if (null === $binding) {
            throw new BindingNotFoundException("Binding with ID '{$bindingId}' not found");
        }

        // Validate new metadata
        $normalizedMetadata = $this->adapter->validateAndNormalizeMetadata($metadata);

        // Replace metadata entirely
        $updatedBinding = $binding->withMetadata($normalizedMetadata);
        $this->adapter->updateMetadata($bindingId, $normalizedMetadata);

        return $updatedBinding;
    }

    public function getMetadata(string $bindingId): array
    {
        $binding = $this->adapter->find($bindingId);
        if (null === $binding) {
            throw new BindingNotFoundException("Binding with ID '{$bindingId}' not found");
        }

        return $binding->getMetadata();
    }

    public function getStorageAdapter(): PersistenceAdapterInterface
    {
        return $this->adapter;
    }

    // Helper methods for testing

    /**
     * Get the total number of bindings stored.
     *
     * @return int Number of bindings
     */
    public function getBindingCount(): int
    {
        // Create a query that matches all bindings
        $query = $this->query();

        return $this->adapter->count($query);
    }

    /**
     * Clear all stored bindings.
     *
     * Useful for test cleanup or resetting state between tests.
     */
    public function clear(): void
    {
        // Get all bindings and delete them
        $query = $this->query();
        $bindings = $query->get();

        foreach ($bindings as $binding) {
            $this->adapter->delete($binding->getId());
        }
    }

    /**
     * Get all stored bindings.
     *
     * @return BindingInterface[] All bindings
     */
    public function getAllBindings(): array
    {
        return $this->query()->get();
    }

    /**
     * Check if any bindings exist.
     *
     * @return bool True if bindings exist
     */
    public function hasBindings(): bool
    {
        return $this->getBindingCount() > 0;
    }
}

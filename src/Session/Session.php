<?php

declare(strict_types=1);

namespace EdgeBinder\Session;

use EdgeBinder\Binding;
use EdgeBinder\Contracts\BindingInterface;
use EdgeBinder\Contracts\PersistenceAdapterInterface;
use EdgeBinder\Contracts\QueryBuilderInterface;
use EdgeBinder\Exception\BindingNotFoundException;
use EdgeBinder\Exception\PersistenceException;

/**
 * Session implementation providing immediate read-after-write consistency.
 *
 * Sessions maintain an in-memory cache of recent operations and provide
 * query builders that merge cache and adapter results to solve database
 * timing issues.
 */
class Session implements SessionInterface
{
    private BindingCache $cache;
    private OperationTracker $tracker;

    public function __construct(
        private readonly PersistenceAdapterInterface $adapter,
        private readonly bool $autoFlush = false
    ) {
        $this->cache = new BindingCache();
        $this->tracker = new OperationTracker();
    }

    public function bind(object $from, object $to, string $type, array $metadata = []): BindingInterface
    {
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

        // Store in adapter
        $this->adapter->store($binding);

        // Track in session cache
        $this->cache->store($binding);
        $this->tracker->recordCreate($binding);

        if ($this->autoFlush) {
            $this->flush();
        }

        return $binding;
    }

    public function unbind(string $bindingId): bool
    {
        // Try to find binding in cache first
        $binding = $this->cache->findById($bindingId);

        // If not in cache, try to find in adapter
        if (null === $binding) {
            $binding = $this->adapter->find($bindingId);
        }

        if (null === $binding) {
            return false;
        }

        // Delete from adapter
        try {
            $this->adapter->delete($bindingId);
        } catch (\Exception $e) {
            // If adapter delete fails, binding might not exist there
            // Continue with cache removal
        }

        // Remove from cache
        $this->cache->remove($bindingId);
        $this->tracker->recordDelete($binding);

        if ($this->autoFlush) {
            $this->flush();
        }

        return true;
    }

    public function query(): QueryBuilderInterface
    {
        return new SessionAwareQueryBuilder($this->adapter, $this->cache);
    }

    public function flush(): void
    {
        // For InMemory adapter, this is essentially a no-op since it provides
        // immediate consistency. For persistent databases, this would wait
        // for indexing/persistence to complete.

        // Wait for all pending operations to be queryable
        foreach ($this->tracker->getPendingOperations() as $operation) {
            $this->waitForConsistency($operation);
        }

        $this->tracker->markAllComplete();
    }

    public function clear(): void
    {
        $this->cache->clear();
        $this->tracker->clear();
    }

    public function close(): void
    {
        $this->flush();
        $this->clear();
    }

    public function isDirty(): bool
    {
        return $this->tracker->hasPendingOperations();
    }

    public function getPendingOperations(): array
    {
        return $this->tracker->getPendingOperations();
    }

    public function getTrackedBindings(): array
    {
        return $this->cache->getAll();
    }

    /**
     * Wait for an operation to be consistent in the adapter.
     *
     * For InMemory adapter, this is a no-op since it provides immediate consistency.
     * For persistent databases, this would implement adapter-specific waiting logic.
     */
    private function waitForConsistency(Operation $operation): void
    {
        // For InMemory adapter, no waiting is needed
        // For persistent adapters, this would implement polling or other
        // consistency verification mechanisms

        // Example for future persistent adapters:
        // if ($this->adapter instanceof WeaviateAdapter) {
        //     $this->waitForWeaviateIndexing($operation->getBindingId());
        // }
    }

    // ========================================
    // Phase 1 Critical Methods - API Gap Resolution
    // ========================================

    public function unbindEntities(object $from, object $to, ?string $type = null): int
    {
        // Extract entity information
        $fromType = $this->adapter->extractEntityType($from);
        $fromId = $this->adapter->extractEntityId($from);
        $toType = $this->adapter->extractEntityType($to);
        $toId = $this->adapter->extractEntityId($to);

        // Find bindings between entities using adapter (includes both cache and adapter results via query)
        $bindings = $this->adapter->findBetweenEntities(
            fromType: $fromType,
            fromId: $fromId,
            toType: $toType,
            toId: $toId,
            bindingType: $type
        );

        // Also check session cache for any additional bindings not yet in adapter
        $cacheBindings = $this->findBindingsInCache($fromId, $toId, $type);

        // Merge and deduplicate bindings
        $allBindings = $this->mergeAndDeduplicateBindings($bindings, $cacheBindings);

        $deletedCount = 0;
        foreach ($allBindings as $binding) {
            // Delete from adapter
            try {
                $this->adapter->delete($binding->getId());
            } catch (\Exception $e) {
                // If adapter delete fails, binding might not exist there
                // Continue with cache removal
            }

            // Remove from cache
            $this->cache->remove($binding->getId());
            $this->tracker->recordDelete($binding);
            ++$deletedCount;
        }

        if ($this->autoFlush && $deletedCount > 0) {
            $this->flush();
        }

        return $deletedCount;
    }

    public function findBindingsFor(object $entity): array
    {
        // Extract entity information
        $entityType = $this->adapter->extractEntityType($entity);
        $entityId = $this->adapter->extractEntityId($entity);

        // Get bindings from adapter
        $adapterBindings = $this->adapter->findByEntity($entityType, $entityId);

        // Get bindings from cache
        $cacheBindings = $this->findEntityBindingsInCache($entityId);

        // Merge and deduplicate
        return $this->mergeAndDeduplicateBindings($adapterBindings, $cacheBindings);
    }

    public function areBound(object $from, object $to, ?string $type = null): bool
    {
        $bindings = $this->findBindingsBetween($from, $to, $type);

        return count($bindings) > 0;
    }

    // ========================================
    // Phase 2 Important Methods - API Gap Resolution
    // ========================================

    public function findBinding(string $bindingId): ?BindingInterface
    {
        // Try to find binding in cache first
        $binding = $this->cache->findById($bindingId);

        // If not in cache, try to find in adapter
        if (null === $binding) {
            $binding = $this->adapter->find($bindingId);
        }

        return $binding;
    }

    public function findBindingsBetween(object $from, object $to, ?string $type = null): array
    {
        // Extract entity information
        $fromType = $this->adapter->extractEntityType($from);
        $fromId = $this->adapter->extractEntityId($from);
        $toType = $this->adapter->extractEntityType($to);
        $toId = $this->adapter->extractEntityId($to);

        // Get bindings from adapter
        $adapterBindings = $this->adapter->findBetweenEntities(
            fromType: $fromType,
            fromId: $fromId,
            toType: $toType,
            toId: $toId,
            bindingType: $type
        );

        // Get bindings from cache
        $cacheBindings = $this->findBindingsInCache($fromId, $toId, $type);

        // Merge and deduplicate
        return $this->mergeAndDeduplicateBindings($adapterBindings, $cacheBindings);
    }

    public function hasBindings(object $entity): bool
    {
        $bindings = $this->findBindingsFor($entity);

        return count($bindings) > 0;
    }

    public function countBindingsFor(object $entity, ?string $type = null): int
    {
        // Get all bindings for entity (both as source and target)
        $bindings = $this->findBindingsFor($entity);

        // Apply type filter if specified
        if (null !== $type) {
            $bindings = array_filter($bindings, fn ($binding) => $binding->getType() === $type);
        }

        return count($bindings);
    }

    public function unbindEntity(object $entity): int
    {
        // Extract entity information
        $entityType = $this->adapter->extractEntityType($entity);
        $entityId = $this->adapter->extractEntityId($entity);

        // Find all bindings involving this entity
        $bindings = $this->findBindingsFor($entity);

        $deletedCount = 0;
        foreach ($bindings as $binding) {
            // Delete from adapter
            try {
                $this->adapter->delete($binding->getId());
            } catch (\Exception $e) {
                // If adapter delete fails, binding might not exist there
                // Continue with cache removal
            }

            // Remove from cache
            $this->cache->remove($binding->getId());
            $this->tracker->recordDelete($binding);
            ++$deletedCount;
        }

        if ($this->autoFlush && $deletedCount > 0) {
            $this->flush();
        }

        return $deletedCount;
    }

    // ========================================
    // Phase 3 Convenience Methods - API Gap Resolution
    // ========================================

    public function bindMany(array $bindings): array
    {
        $createdBindings = [];

        foreach ($bindings as $bindingSpec) {
            $metadata = $bindingSpec['metadata'] ?? [];

            $binding = $this->bind(
                from: $bindingSpec['from'],
                to: $bindingSpec['to'],
                type: $bindingSpec['type'],
                metadata: $metadata
            );

            $createdBindings[] = $binding;
        }

        return $createdBindings;
    }

    public function updateMetadata(string $bindingId, array $metadata): BindingInterface
    {
        // Find the existing binding
        $binding = $this->findBinding($bindingId);
        if (null === $binding) {
            throw BindingNotFoundException::withId($bindingId);
        }

        // Validate and normalize the new metadata
        $normalizedMetadata = $this->adapter->validateAndNormalizeMetadata($metadata);

        // Merge with existing metadata
        $mergedMetadata = array_merge($binding->getMetadata(), $normalizedMetadata);

        // Update the binding in adapter
        $this->adapter->updateMetadata($bindingId, $mergedMetadata);

        // Update in session cache if present
        $cachedBinding = $this->cache->findById($bindingId);
        if (null !== $cachedBinding) {
            $updatedCachedBinding = $cachedBinding->withMetadata($mergedMetadata);
            $this->cache->store($updatedCachedBinding);
            $this->tracker->recordUpdate($updatedCachedBinding);
        }

        if ($this->autoFlush) {
            $this->flush();
        }

        // Return the updated binding
        $updatedBinding = $this->findBinding($bindingId);
        if (null === $updatedBinding) {
            throw new PersistenceException('update', 'Failed to retrieve updated binding');
        }

        return $updatedBinding;
    }

    public function replaceMetadata(string $bindingId, array $metadata): BindingInterface
    {
        // Find the existing binding
        $binding = $this->findBinding($bindingId);
        if (null === $binding) {
            throw BindingNotFoundException::withId($bindingId);
        }

        // Validate and normalize the new metadata
        $normalizedMetadata = $this->adapter->validateAndNormalizeMetadata($metadata);

        // Replace the metadata entirely in adapter
        $this->adapter->updateMetadata($bindingId, $normalizedMetadata);

        // Update in session cache if present
        $cachedBinding = $this->cache->findById($bindingId);
        if (null !== $cachedBinding) {
            $updatedCachedBinding = $cachedBinding->withMetadata($normalizedMetadata);
            $this->cache->store($updatedCachedBinding);
            $this->tracker->recordUpdate($updatedCachedBinding);
        }

        if ($this->autoFlush) {
            $this->flush();
        }

        // Return the updated binding
        $updatedBinding = $this->findBinding($bindingId);
        if (null === $updatedBinding) {
            throw new PersistenceException('update', 'Failed to retrieve updated binding');
        }

        return $updatedBinding;
    }

    public function getMetadata(string $bindingId): array
    {
        $binding = $this->findBinding($bindingId);
        if (null === $binding) {
            throw BindingNotFoundException::withId($bindingId);
        }

        return $binding->getMetadata();
    }

    /**
     * Find bindings in cache between two specific entities.
     *
     * @return array<BindingInterface>
     */
    private function findBindingsInCache(string $fromId, string $toId, ?string $type = null): array
    {
        $allCacheBindings = $this->cache->getAll();
        $matchingBindings = [];

        foreach ($allCacheBindings as $binding) {
            // Check if binding matches the from/to criteria
            if ($binding->getFromId() === $fromId && $binding->getToId() === $toId) {
                // Check type filter if specified
                if (null === $type || $binding->getType() === $type) {
                    $matchingBindings[] = $binding;
                }
            }
        }

        return $matchingBindings;
    }

    /**
     * Find all bindings in cache involving a specific entity.
     *
     * @return array<BindingInterface>
     */
    private function findEntityBindingsInCache(string $entityId): array
    {
        $allCacheBindings = $this->cache->getAll();
        $matchingBindings = [];

        foreach ($allCacheBindings as $binding) {
            // Check if entity is involved as either from or to
            if ($binding->getFromId() === $entityId || $binding->getToId() === $entityId) {
                $matchingBindings[] = $binding;
            }
        }

        return $matchingBindings;
    }

    /**
     * Merge and deduplicate bindings from different sources.
     *
     * @param array<BindingInterface> $adapterBindings
     * @param array<BindingInterface> $cacheBindings
     *
     * @return array<BindingInterface>
     */
    private function mergeAndDeduplicateBindings(array $adapterBindings, array $cacheBindings): array
    {
        $bindingMap = [];

        // Add adapter bindings
        foreach ($adapterBindings as $binding) {
            $bindingMap[$binding->getId()] = $binding;
        }

        // Add cache bindings (will overwrite adapter bindings with same ID)
        foreach ($cacheBindings as $binding) {
            $bindingMap[$binding->getId()] = $binding;
        }

        return array_values($bindingMap);
    }
}

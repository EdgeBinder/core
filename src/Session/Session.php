<?php

declare(strict_types=1);

namespace EdgeBinder\Session;

use EdgeBinder\Binding;
use EdgeBinder\Contracts\BindingInterface;
use EdgeBinder\Contracts\PersistenceAdapterInterface;
use EdgeBinder\Contracts\QueryBuilderInterface;

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
}

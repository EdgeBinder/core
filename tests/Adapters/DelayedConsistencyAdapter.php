<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Adapters;

use EdgeBinder\Contracts\BindingInterface;
use EdgeBinder\Contracts\PersistenceAdapterInterface;
use EdgeBinder\Contracts\QueryBuilderInterface;
use EdgeBinder\Persistence\InMemory\InMemoryAdapter;

/**
 * Test adapter that simulates persistent database timing issues.
 * 
 * This adapter wraps InMemoryAdapter but introduces artificial delays
 * to simulate the indexing/consistency delays found in real persistent databases.
 */
class DelayedConsistencyAdapter implements PersistenceAdapterInterface
{
    private InMemoryAdapter $underlyingAdapter;
    private array $recentBindings = [];
    private int $consistencyDelayMs;

    public function __construct(
        int $consistencyDelayMs = 100  // Time before binding becomes queryable
    ) {
        $this->underlyingAdapter = new InMemoryAdapter();
        $this->consistencyDelayMs = $consistencyDelayMs;
    }

    public function store(BindingInterface $binding): void
    {
        // Store in underlying adapter immediately (like real databases do)
        $this->underlyingAdapter->store($binding);

        // Track when this binding was created (simulate indexing delay)
        $now = microtime(true);
        $queryableAt = $now + ($this->consistencyDelayMs / 1000);

        $this->recentBindings[$binding->getId()] = [
            'binding' => $binding,
            'created_at' => $now,
            'queryable_at' => $queryableAt
        ];


    }

    public function query(): QueryBuilderInterface
    {
        // InMemoryAdapter doesn't have a query() method, so we create a BindingQueryBuilder
        return new \EdgeBinder\Query\BindingQueryBuilder($this);
    }

    public function extractEntityId(object $entity): string
    {
        return $this->underlyingAdapter->extractEntityId($entity);
    }

    public function extractEntityType(object $entity): string
    {
        return $this->underlyingAdapter->extractEntityType($entity);
    }

    public function validateAndNormalizeMetadata(array $metadata): array
    {
        return $this->underlyingAdapter->validateAndNormalizeMetadata($metadata);
    }

    public function find(string $bindingId): ?BindingInterface
    {
        return $this->underlyingAdapter->find($bindingId);
    }

    public function findByEntity(string $entityType, string $entityId): array
    {
        return $this->underlyingAdapter->findByEntity($entityType, $entityId);
    }

    public function findBetweenEntities(
        string $fromType,
        string $fromId,
        string $toType,
        string $toId,
        ?string $bindingType = null
    ): array {
        return $this->underlyingAdapter->findBetweenEntities($fromType, $fromId, $toType, $toId, $bindingType);
    }

    public function executeQuery(\EdgeBinder\Query\QueryCriteria $criteria): \EdgeBinder\Contracts\QueryResultInterface
    {
        // This is where we apply the timing simulation
        $result = $this->underlyingAdapter->executeQuery($criteria);
        $allBindings = $result->getBindings();

        // Filter out bindings that aren't queryable yet
        $queryableBindings = $this->filterQueryableBindings($allBindings);

        return new \EdgeBinder\Query\QueryResult($queryableBindings);
    }

    public function count(\EdgeBinder\Query\QueryCriteria $criteria): int
    {
        $result = $this->executeQuery($criteria);
        return count($result->getBindings());
    }

    public function updateMetadata(string $bindingId, array $metadata): void
    {
        $this->underlyingAdapter->updateMetadata($bindingId, $metadata);
    }

    public function delete(string $bindingId): void
    {
        // Remove from recent bindings tracking
        unset($this->recentBindings[$bindingId]);
        $this->underlyingAdapter->delete($bindingId);
    }

    public function deleteByEntity(string $entityType, string $entityId): int
    {
        return $this->underlyingAdapter->deleteByEntity($entityType, $entityId);
    }

    /**
     * Simulate database "refresh" or "sync" operation that makes all recent bindings queryable.
     */
    public function forceConsistency(): void
    {
        $now = microtime(true);
        foreach ($this->recentBindings as &$entry) {
            $entry['queryable_at'] = $now;
        }
    }

    /**
     * Get the number of bindings that are created but not yet queryable.
     */
    public function getPendingIndexingCount(): int
    {
        $now = microtime(true);
        $pending = 0;
        
        foreach ($this->recentBindings as $entry) {
            if ($entry['queryable_at'] > $now) {
                $pending++;
            }
        }
        
        return $pending;
    }

    /**
     * Check if a specific binding is queryable yet.
     */
    public function isBindingQueryable(string $bindingId): bool
    {
        if (!isset($this->recentBindings[$bindingId])) {
            return true; // Old bindings are always queryable
        }

        $now = microtime(true);
        return $this->recentBindings[$bindingId]['queryable_at'] <= $now;
    }

    /**
     * Filter bindings to only include those that are "queryable" based on timing simulation.
     */
    private function filterQueryableBindings(array $bindings): array
    {
        $now = microtime(true);
        $queryableBindings = [];

        foreach ($bindings as $binding) {
            $bindingId = $binding->getId();

            // If binding is not in recent tracking, it's old and queryable
            if (!isset($this->recentBindings[$bindingId])) {
                $queryableBindings[] = $binding;
                continue;
            }

            // Check if enough time has passed for this binding to be queryable
            $entry = $this->recentBindings[$bindingId];
            if ($entry['queryable_at'] <= $now) {
                $queryableBindings[] = $binding;
            }
            // Otherwise, skip this binding (simulate indexing delay)
        }

        return $queryableBindings;
    }
}

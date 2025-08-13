<?php

declare(strict_types=1);

namespace EdgeBinder\Session;

use EdgeBinder\Contracts\BindingInterface;

/**
 * In-memory cache for bindings with efficient indexing.
 *
 * Provides fast lookups by entity IDs and binding types to support
 * immediate read-after-write consistency within sessions.
 */
class BindingCache
{
    /** @var array<string, BindingInterface> Main storage indexed by binding ID */
    private array $bindings = [];

    /** @var array<string, string[]> From entity index: [fromId] => [bindingId, ...] */
    private array $fromIndex = [];

    /** @var array<string, string[]> To entity index: [toId] => [bindingId, ...] */
    private array $toIndex = [];

    /** @var array<string, string[]> Type index: [type] => [bindingId, ...] */
    private array $typeIndex = [];

    /**
     * Store a binding in the cache with indexing.
     */
    public function store(BindingInterface $binding): void
    {
        $id = $binding->getId();
        $this->bindings[$id] = $binding;

        // Update from index
        $fromId = $binding->getFromId();
        if (!isset($this->fromIndex[$fromId])) {
            $this->fromIndex[$fromId] = [];
        }
        if (!in_array($id, $this->fromIndex[$fromId], true)) {
            $this->fromIndex[$fromId][] = $id;
        }

        // Update to index
        $toId = $binding->getToId();
        if (!isset($this->toIndex[$toId])) {
            $this->toIndex[$toId] = [];
        }
        if (!in_array($id, $this->toIndex[$toId], true)) {
            $this->toIndex[$toId][] = $id;
        }

        // Update type index
        $type = $binding->getType();
        if (!isset($this->typeIndex[$type])) {
            $this->typeIndex[$type] = [];
        }
        if (!in_array($id, $this->typeIndex[$type], true)) {
            $this->typeIndex[$type][] = $id;
        }
    }

    /**
     * Find a binding by its ID.
     */
    public function findById(string $bindingId): ?BindingInterface
    {
        return $this->bindings[$bindingId] ?? null;
    }

    /**
     * Find bindings by from entity ID.
     *
     * @return array<BindingInterface>
     */
    public function findByFrom(string $fromId): array
    {
        $bindingIds = $this->fromIndex[$fromId] ?? [];

        return array_map(fn ($id) => $this->bindings[$id], $bindingIds);
    }

    /**
     * Find bindings by to entity ID.
     *
     * @return array<BindingInterface>
     */
    public function findByTo(string $toId): array
    {
        $bindingIds = $this->toIndex[$toId] ?? [];

        return array_map(fn ($id) => $this->bindings[$id], $bindingIds);
    }

    /**
     * Find bindings by type.
     *
     * @return array<BindingInterface>
     */
    public function findByType(string $type): array
    {
        $bindingIds = $this->typeIndex[$type] ?? [];

        return array_map(fn ($id) => $this->bindings[$id], $bindingIds);
    }

    /**
     * Find bindings matching query criteria.
     *
     * @return array<BindingInterface>
     */
    public function findByQuery(QueryCriteria $criteria): array
    {
        $candidates = $this->bindings;

        // Apply from filter
        if ($criteria->hasFrom()) {
            $fromBindingIds = $this->fromIndex[$criteria->getFrom()] ?? [];
            $candidates = array_intersect_key($candidates, array_flip($fromBindingIds));
        }

        // Apply to filter
        if ($criteria->hasTo()) {
            $toBindingIds = $this->toIndex[$criteria->getTo()] ?? [];
            $candidates = array_intersect_key($candidates, array_flip($toBindingIds));
        }

        // Apply type filter
        if ($criteria->hasType()) {
            $typeBindingIds = $this->typeIndex[$criteria->getType()] ?? [];
            $candidates = array_intersect_key($candidates, array_flip($typeBindingIds));
        }

        return array_values($candidates);
    }

    /**
     * Remove a binding from the cache and update indexes.
     */
    public function remove(string $bindingId): void
    {
        if (!isset($this->bindings[$bindingId])) {
            return;
        }

        $binding = $this->bindings[$bindingId];

        // Remove from main storage
        unset($this->bindings[$bindingId]);

        // Remove from indexes
        $this->removeFromIndex($this->fromIndex, $binding->getFromId(), $bindingId);
        $this->removeFromIndex($this->toIndex, $binding->getToId(), $bindingId);
        $this->removeFromIndex($this->typeIndex, $binding->getType(), $bindingId);
    }

    /**
     * Clear all cached bindings and indexes.
     */
    public function clear(): void
    {
        $this->bindings = [];
        $this->fromIndex = [];
        $this->toIndex = [];
        $this->typeIndex = [];
    }

    /**
     * Get the number of cached bindings.
     */
    public function size(): int
    {
        return count($this->bindings);
    }

    /**
     * Check if a binding is cached.
     */
    public function has(string $bindingId): bool
    {
        return isset($this->bindings[$bindingId]);
    }

    /**
     * Get all cached bindings.
     *
     * @return array<BindingInterface>
     */
    public function getAll(): array
    {
        return array_values($this->bindings);
    }

    /**
     * Remove a binding ID from an index array.
     *
     * @param array<string, string[]> $index
     */
    private function removeFromIndex(array &$index, string $key, string $bindingId): void
    {
        if (!isset($index[$key])) {
            return;
        }

        $index[$key] = array_filter($index[$key], fn ($id) => $id !== $bindingId);

        // Clean up empty index entries
        if (empty($index[$key])) {
            unset($index[$key]);
        }
    }
}

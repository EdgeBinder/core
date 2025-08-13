<?php

declare(strict_types=1);

namespace EdgeBinder\Session;

use EdgeBinder\Contracts\BindingInterface;
use EdgeBinder\Contracts\QueryBuilderInterface;

/**
 * Session interface for managing consistency and caching of bindings.
 *
 * Sessions provide immediate read-after-write consistency by maintaining
 * an in-memory cache of recent operations. This solves the database timing
 * issues identified in DATABASE_TIMING_ISSUE.md.
 */
interface SessionInterface
{
    /**
     * Create a binding between two entities within this session.
     *
     * @param object               $from     Source entity
     * @param object               $to       Target entity
     * @param string               $type     Binding type/relationship name
     * @param array<string, mixed> $metadata Optional metadata for the binding
     *
     * @return BindingInterface The created binding
     */
    public function bind(object $from, object $to, string $type, array $metadata = []): BindingInterface;

    /**
     * Remove a binding by its identifier within this session.
     *
     * @param string $bindingId The binding identifier
     *
     * @return bool True if binding was removed, false if not found
     */
    public function unbind(string $bindingId): bool;

    /**
     * Create a query builder that sees session cache and adapter results.
     *
     * @return QueryBuilderInterface Session-aware query builder
     */
    public function query(): QueryBuilderInterface;

    /**
     * Flush all pending operations to ensure they are queryable in the adapter.
     *
     * For InMemory adapter, this is a no-op since it provides immediate consistency.
     * For persistent databases, this waits for indexing/persistence to complete.
     */
    public function flush(): void;

    /**
     * Clear the session cache and reset all tracked operations.
     *
     * This removes all cached bindings and pending operations from the session
     * but does not affect data that has already been persisted to the adapter.
     */
    public function clear(): void;

    /**
     * Close the session and clean up resources.
     *
     * This flushes any pending operations and clears the session cache.
     */
    public function close(): void;

    /**
     * Check if the session has uncommitted changes.
     *
     * @return bool True if there are pending operations
     */
    public function isDirty(): bool;

    /**
     * Get all pending operations that haven't been flushed.
     *
     * @return array<Operation> Array of pending operations
     */
    public function getPendingOperations(): array;

    /**
     * Get all bindings currently tracked in the session cache.
     *
     * @return array<BindingInterface> Array of cached bindings
     */
    public function getTrackedBindings(): array;

    // ========================================
    // Phase 1 Critical Methods - API Gap Resolution
    // ========================================

    /**
     * Remove all bindings between two entities within this session.
     *
     * This method provides bulk unbinding functionality, eliminating the need
     * for inefficient query + loop + individual unbind patterns.
     *
     * @param object      $from Source entity
     * @param object      $to   Target entity
     * @param string|null $type Optional binding type filter
     *
     * @return int Number of bindings removed
     */
    public function unbindEntities(object $from, object $to, ?string $type = null): int;

    /**
     * Find all bindings for an entity within this session.
     *
     * Returns bindings where the entity appears as either source or target,
     * merging results from session cache and adapter.
     *
     * @param object $entity The entity to find bindings for
     *
     * @return array<BindingInterface> Array of bindings involving the entity
     */
    public function findBindingsFor(object $entity): array;

    /**
     * Check if two entities are bound within this session.
     *
     * Essential for relationship validation logic, checking both session
     * cache and adapter for existing relationships.
     *
     * @param object      $from Source entity
     * @param object      $to   Target entity
     * @param string|null $type Optional binding type filter
     *
     * @return bool True if entities are bound
     */
    public function areBound(object $from, object $to, ?string $type = null): bool;

    // ========================================
    // Phase 2 Important Methods - API Gap Resolution
    // ========================================

    /**
     * Find a binding by its identifier within this session.
     *
     * Provides direct binding lookup functionality, checking both session
     * cache and adapter for the binding.
     *
     * @param string $bindingId The binding identifier
     *
     * @return BindingInterface|null The binding if found, null otherwise
     */
    public function findBinding(string $bindingId): ?BindingInterface;

    /**
     * Find bindings between two entities within this session.
     *
     * Returns bindings where the specified entities are connected in the
     * given direction, merging results from session cache and adapter.
     *
     * @param object      $from Source entity
     * @param object      $to   Target entity
     * @param string|null $type Optional binding type filter
     *
     * @return array<BindingInterface> Array of bindings between the entities
     */
    public function findBindingsBetween(object $from, object $to, ?string $type = null): array;

    /**
     * Check if an entity has any bindings within this session.
     *
     * Essential for entity relationship checking, verifying if an entity
     * is involved in any relationships as either source or target.
     *
     * @param object $entity The entity to check
     *
     * @return bool True if entity has bindings
     */
    public function hasBindings(object $entity): bool;

    /**
     * Count bindings for an entity within this session.
     *
     * Provides efficient counting of entity relationships with optional
     * type filtering, merging counts from session cache and adapter.
     *
     * @param object      $entity The entity to count bindings for
     * @param string|null $type   Optional binding type filter
     *
     * @return int Number of bindings
     */
    public function countBindingsFor(object $entity, ?string $type = null): int;

    /**
     * Remove all bindings involving an entity within this session.
     *
     * Bulk entity cleanup functionality, removing all relationships where
     * the entity appears as either source or target.
     *
     * @param object $entity The entity to unbind
     *
     * @return int Number of bindings removed
     */
    public function unbindEntity(object $entity): int;
}

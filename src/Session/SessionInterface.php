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
}

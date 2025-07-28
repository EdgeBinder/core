<?php

declare(strict_types=1);

namespace EdgeBinder\Contracts;

use EdgeBinder\Exception\BindingNotFoundException;
use EdgeBinder\Exception\InvalidMetadataException;
use EdgeBinder\Exception\PersistenceException;

/**
 * Main EdgeBinder service interface.
 *
 * EdgeBinder provides the primary API for managing entity relationships
 * with rich metadata support. It acts as a facade over the storage layer,
 * providing a clean, consistent interface regardless of the underlying
 * storage implementation.
 *
 * Key responsibilities:
 * - Creating and managing bindings between entities
 * - Querying relationships with flexible criteria
 * - Updating relationship metadata
 * - Providing a storage-agnostic API
 * - Dispatching events for relationship changes (if configured)
 */
interface EdgeBinderInterface
{
    /**
     * Create a binding between two entities.
     *
     * @param object               $from     Source entity
     * @param object               $to       Target entity
     * @param string               $type     Binding type/relationship name
     * @param array<string, mixed> $metadata Optional metadata for the binding
     *
     * @return BindingInterface The created binding
     *
     * @throws InvalidMetadataException If metadata is invalid
     * @throws PersistenceException     If the binding cannot be stored
     */
    public function bind(
        object $from,
        object $to,
        string $type,
        array $metadata = []
    ): BindingInterface;

    /**
     * Remove a binding by its identifier.
     *
     * @param string $bindingId The binding identifier
     *
     * @throws BindingNotFoundException If the binding doesn't exist
     * @throws PersistenceException     If the binding cannot be deleted
     */
    public function unbind(string $bindingId): void;

    /**
     * Remove bindings between two entities.
     *
     * @param object      $from Source entity
     * @param object      $to   Target entity
     * @param string|null $type Optional binding type filter
     *
     * @return int Number of bindings removed
     *
     * @throws PersistenceException If bindings cannot be deleted
     */
    public function unbindEntities(object $from, object $to, ?string $type = null): int;

    /**
     * Remove all bindings involving an entity.
     *
     * @param object $entity The entity to unbind
     *
     * @return int Number of bindings removed
     *
     * @throws PersistenceException If bindings cannot be deleted
     */
    public function unbindEntity(object $entity): int;

    /**
     * Create a new query builder for finding bindings.
     *
     * @return QueryBuilderInterface New query builder instance
     */
    public function query(): QueryBuilderInterface;

    /**
     * Find a binding by its identifier.
     *
     * @param string $bindingId The binding identifier
     *
     * @return BindingInterface|null The binding if found, null otherwise
     *
     * @throws PersistenceException If the query fails
     */
    public function findBinding(string $bindingId): ?BindingInterface;

    /**
     * Find all bindings involving an entity.
     *
     * @param object $entity The entity to find bindings for
     *
     * @return BindingInterface[] Array of bindings involving the entity
     *
     * @throws PersistenceException If the query fails
     */
    public function findBindingsFor(object $entity): array;

    /**
     * Find bindings between two entities.
     *
     * @param object      $from Source entity
     * @param object      $to   Target entity
     * @param string|null $type Optional binding type filter
     *
     * @return BindingInterface[] Array of bindings between the entities
     *
     * @throws PersistenceException If the query fails
     */
    public function findBindingsBetween(object $from, object $to, ?string $type = null): array;

    /**
     * Check if two entities are bound.
     *
     * @param object      $from Source entity
     * @param object      $to   Target entity
     * @param string|null $type Optional binding type filter
     *
     * @return bool True if entities are bound
     *
     * @throws PersistenceException If the query fails
     */
    public function areBound(object $from, object $to, ?string $type = null): bool;

    /**
     * Update a binding's metadata.
     *
     * @param string               $bindingId The binding identifier
     * @param array<string, mixed> $metadata  New metadata to merge with existing
     *
     * @return BindingInterface The updated binding
     *
     * @throws BindingNotFoundException If the binding doesn't exist
     * @throws InvalidMetadataException If metadata is invalid
     * @throws PersistenceException     If the update fails
     */
    public function updateMetadata(string $bindingId, array $metadata): BindingInterface;

    /**
     * Replace a binding's metadata entirely.
     *
     * @param string               $bindingId The binding identifier
     * @param array<string, mixed> $metadata  New metadata to replace existing
     *
     * @return BindingInterface The updated binding
     *
     * @throws BindingNotFoundException If the binding doesn't exist
     * @throws InvalidMetadataException If metadata is invalid
     * @throws PersistenceException     If the update fails
     */
    public function replaceMetadata(string $bindingId, array $metadata): BindingInterface;

    /**
     * Get metadata for a specific binding.
     *
     * @param string $bindingId The binding identifier
     *
     * @return array<string, mixed> The binding's metadata
     *
     * @throws BindingNotFoundException If the binding doesn't exist
     * @throws PersistenceException     If the query fails
     */
    public function getMetadata(string $bindingId): array;

    /**
     * Get the storage adapter being used.
     *
     * @return PersistenceAdapterInterface The storage adapter
     */
    public function getStorageAdapter(): PersistenceAdapterInterface;
}

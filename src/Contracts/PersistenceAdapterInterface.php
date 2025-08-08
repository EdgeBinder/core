<?php

declare(strict_types=1);

namespace EdgeBinder\Contracts;

use EdgeBinder\Exception\BindingNotFoundException;
use EdgeBinder\Exception\EntityExtractionException;
use EdgeBinder\Exception\InvalidMetadataException;
use EdgeBinder\Exception\PersistenceException;
use EdgeBinder\Query\QueryCriteria;

/**
 * Persistence adapter interface for persisting and retrieving bindings.
 *
 * Persistence adapters are responsible for:
 * - Entity identification and validation
 * - Metadata validation and normalization
 * - Persistence operations (CRUD)
 * - Query execution and filtering
 * - Storage-specific optimizations
 *
 * Each adapter can implement storage-specific features while maintaining
 * a consistent interface for the EdgeBinder core.
 */
interface PersistenceAdapterInterface
{
    /**
     * Extract the unique identifier from an entity object.
     *
     * The adapter is responsible for determining how to extract an ID
     * from the given entity. This might involve:
     * - Checking for EntityInterface implementation
     * - Using reflection to find ID properties
     * - Applying naming conventions
     * - Using adapter-specific configuration
     *
     * @param object $entity The entity to extract ID from
     *
     * @return string The entity's unique identifier
     *
     * @throws EntityExtractionException If ID cannot be extracted or is invalid
     */
    public function extractEntityId(object $entity): string;

    /**
     * Extract the type identifier from an entity object.
     *
     * The adapter determines the entity type, which might be:
     * - The class name
     * - A configured type mapping
     * - A property value
     * - Database table/collection name
     *
     * @param object $entity The entity to extract type from
     *
     * @return string The entity's type identifier
     *
     * @throws EntityExtractionException If type cannot be determined or is invalid
     */
    public function extractEntityType(object $entity): string;

    /**
     * Validate and normalize metadata for storage.
     *
     * Adapters can enforce storage-specific constraints:
     * - Size limits (JSON column limits, document size)
     * - Type restrictions (vector dimensions, schema validation)
     * - Field naming conventions
     * - Value transformations for optimal storage
     *
     * @param array<string, mixed> $metadata The metadata to validate
     *
     * @return array<string, mixed> Normalized metadata ready for storage
     *
     * @throws InvalidMetadataException If metadata is invalid or cannot be normalized
     */
    public function validateAndNormalizeMetadata(array $metadata): array;

    /**
     * Store a binding in the storage system.
     *
     * @param BindingInterface $binding The binding to store
     *
     * @throws PersistenceException If the binding cannot be stored
     */
    public function store(BindingInterface $binding): void;

    /**
     * Find a binding by its unique identifier.
     *
     * @param string $bindingId The binding identifier
     *
     * @return BindingInterface|null The binding if found, null otherwise
     *
     * @throws PersistenceException If the query fails
     */
    public function find(string $bindingId): ?BindingInterface;

    /**
     * Find all bindings involving a specific entity.
     *
     * Returns bindings where the entity appears as either source or target.
     *
     * @param string $entityType The entity type
     * @param string $entityId   The entity identifier
     *
     * @return BindingInterface[] Array of bindings involving the entity
     *
     * @throws PersistenceException If the query fails
     */
    public function findByEntity(string $entityType, string $entityId): array;

    /**
     * Find bindings between two specific entities.
     *
     * @param string      $fromType    Source entity type
     * @param string      $fromId      Source entity identifier
     * @param string      $toType      Target entity type
     * @param string      $toId        Target entity identifier
     * @param string|null $bindingType Optional binding type filter
     *
     * @return BindingInterface[] Array of bindings between the entities
     *
     * @throws PersistenceException If the query fails
     */
    public function findBetweenEntities(
        string $fromType,
        string $fromId,
        string $toType,
        string $toId,
        ?string $bindingType = null
    ): array;

    /**
     * Execute a query and return matching bindings.
     *
     * @param QueryCriteria $criteria The query criteria to execute
     *
     * @return QueryResultInterface Query results with bindings
     *
     * @throws PersistenceException If the query fails
     */
    public function executeQuery(QueryCriteria $criteria): QueryResultInterface;

    /**
     * Count bindings matching a query.
     *
     * @param QueryCriteria $criteria The query criteria to count
     *
     * @return int Number of matching bindings
     *
     * @throws PersistenceException If the query fails
     */
    public function count(QueryCriteria $criteria): int;

    /**
     * Update a binding's metadata.
     *
     * @param string               $bindingId The binding identifier
     * @param array<string, mixed> $metadata  New metadata
     *
     * @throws BindingNotFoundException If the binding doesn't exist
     * @throws InvalidMetadataException If the metadata is invalid
     * @throws PersistenceException     If the update fails
     */
    public function updateMetadata(string $bindingId, array $metadata): void;

    /**
     * Delete a binding from storage.
     *
     * @param string $bindingId The binding identifier
     *
     * @throws BindingNotFoundException If the binding doesn't exist
     * @throws PersistenceException     If the deletion fails
     */
    public function delete(string $bindingId): void;

    /**
     * Delete all bindings involving a specific entity.
     *
     * @param string $entityType The entity type
     * @param string $entityId   The entity identifier
     *
     * @return int Number of bindings deleted
     *
     * @throws PersistenceException If the deletion fails
     */
    public function deleteByEntity(string $entityType, string $entityId): int;
}

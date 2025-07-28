<?php

declare(strict_types=1);

namespace EdgeBinder\Contracts;

use DateTimeImmutable;

/**
 * Represents a binding (relationship) between two entities.
 *
 * A binding captures:
 * - The source entity (from)
 * - The target entity (to)
 * - The relationship type
 * - Rich metadata about the relationship
 * - Timestamps for tracking
 *
 * Bindings are immutable once created - metadata updates create new versions
 * while preserving the core relationship identity.
 */
interface BindingInterface
{
    /**
     * Get the unique identifier for this binding.
     *
     * @return string Unique binding identifier
     */
    public function getId(): string;

    /**
     * Get the source entity type.
     *
     * @return string The type of the source entity
     */
    public function getFromType(): string;

    /**
     * Get the source entity identifier.
     *
     * @return string The ID of the source entity
     */
    public function getFromId(): string;

    /**
     * Get the target entity type.
     *
     * @return string The type of the target entity
     */
    public function getToType(): string;

    /**
     * Get the target entity identifier.
     *
     * @return string The ID of the target entity
     */
    public function getToId(): string;

    /**
     * Get the binding type/relationship name.
     *
     * Examples: 'has_access', 'belongs_to', 'created_by', 'similar_to'
     *
     * @return string The binding type
     */
    public function getType(): string;

    /**
     * Get the binding metadata.
     *
     * Metadata can contain any additional information about the relationship:
     * - Business data (access_level, permissions, roles)
     * - Vector/AI data (similarity_score, embedding_version)
     * - Graph data (weight, strength, direction)
     * - Temporal data (expires_at, valid_from)
     * - Audit data (created_by, modified_by)
     *
     * @return array<string, mixed> The binding metadata
     */
    public function getMetadata(): array;

    /**
     * Get when this binding was created.
     *
     * @return DateTimeImmutable Creation timestamp
     */
    public function getCreatedAt(): DateTimeImmutable;

    /**
     * Get when this binding was last updated.
     *
     * @return DateTimeImmutable Last update timestamp
     */
    public function getUpdatedAt(): DateTimeImmutable;

    /**
     * Create a new binding with updated metadata.
     *
     * Since bindings are immutable, this returns a new instance with
     * the same core relationship but updated metadata and timestamp.
     *
     * @param array<string, mixed> $metadata New metadata to merge/replace
     * @return static New binding instance with updated metadata
     */
    public function withMetadata(array $metadata): static;

    /**
     * Check if this binding connects the specified entities.
     *
     * @param string $fromType Source entity type
     * @param string $fromId Source entity ID
     * @param string $toType Target entity type
     * @param string $toId Target entity ID
     * @return bool True if this binding connects the specified entities
     */
    public function connects(string $fromType, string $fromId, string $toType, string $toId): bool;

    /**
     * Check if this binding involves the specified entity.
     *
     * @param string $entityType Entity type to check
     * @param string $entityId Entity ID to check
     * @return bool True if this binding involves the specified entity (as source or target)
     */
    public function involves(string $entityType, string $entityId): bool;
}

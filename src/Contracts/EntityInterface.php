<?php

declare(strict_types=1);

namespace EdgeBinder\Contracts;

/**
 * Interface for entities that can participate in bindings.
 *
 * This interface is optional - EdgeBinder can work with any object through
 * storage adapter entity extraction. However, implementing this interface
 * provides better type safety and explicit contracts for entity identification.
 *
 * Entities implementing this interface guarantee they can provide:
 * - A unique identifier within their type
 * - A type identifier for categorization
 */
interface EntityInterface
{
    /**
     * Get the unique identifier for this entity.
     *
     * The ID must be:
     * - Unique within the entity type
     * - Non-empty string
     * - Stable across the entity's lifetime
     * - Suitable for storage and retrieval
     *
     * @return string The entity's unique identifier
     */
    public function getId(): string;

    /**
     * Get the type identifier for this entity.
     *
     * The type should:
     * - Identify the entity's class/category
     * - Be consistent across all instances of the same type
     * - Be suitable for grouping and filtering
     * - Typically be the class name or a domain-specific type
     *
     * Examples: 'User', 'Project', 'Document', 'Workspace'
     *
     * @return string The entity's type identifier
     */
    public function getType(): string;
}

<?php

declare(strict_types=1);

namespace EdgeBinder\Exception;

/**
 * Exception thrown when entity identification fails.
 *
 * This exception is thrown by storage adapters when they cannot extract
 * a valid ID or type from an entity object. This typically happens when
 * entities don't follow expected conventions or lack required properties.
 */
class EntityExtractionException extends EdgeBinderException
{
    public function __construct(
        string $reason,
        public readonly object $entity,
        ?\Throwable $previous = null
    ) {
        $entityClass = $entity::class;

        parent::__construct(
            message: "Failed to extract entity information from {$entityClass}: {$reason}",
            previous: $previous
        );
    }

    /**
     * Create exception for entities missing required ID property.
     */
    public static function missingId(
        object $entity,
        ?\Throwable $previous = null
    ): self {
        return new self(
            reason: 'Entity does not have a valid ID property',
            entity: $entity,
            previous: $previous
        );
    }

    /**
     * Create exception for entities with invalid ID values.
     */
    public static function invalidId(
        object $entity,
        mixed $invalidId,
        ?\Throwable $previous = null
    ): self {
        $idType = get_debug_type($invalidId);

        return new self(
            reason: "Entity ID must be a non-empty string, got {$idType}",
            entity: $entity,
            previous: $previous
        );
    }

    /**
     * Create exception for entities where type cannot be determined.
     */
    public static function cannotDetermineType(
        object $entity,
        ?\Throwable $previous = null
    ): self {
        return new self(
            reason: 'Cannot determine entity type from object',
            entity: $entity,
            previous: $previous
        );
    }

    /**
     * Create exception for entities with invalid type values.
     */
    public static function invalidType(
        object $entity,
        mixed $invalidType,
        ?\Throwable $previous = null
    ): self {
        $typeType = get_debug_type($invalidType);

        return new self(
            reason: "Entity type must be a non-empty string, got {$typeType}",
            entity: $entity,
            previous: $previous
        );
    }
}

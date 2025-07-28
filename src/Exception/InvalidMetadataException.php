<?php

declare(strict_types=1);

namespace EdgeBinder\Exception;

/**
 * Exception thrown when metadata validation fails.
 *
 * This exception is thrown by storage adapters when metadata does not
 * meet the requirements of the underlying storage system (e.g., size limits,
 * type constraints, schema validation failures).
 */
class InvalidMetadataException extends EdgeBinderException
{
    public function __construct(
        string $reason,
        public readonly array $invalidMetadata = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            message: "Invalid metadata: {$reason}",
            previous: $previous
        );
    }

    /**
     * Create exception for metadata that exceeds size limits.
     */
    public static function sizeLimitExceeded(
        int $actualSize,
        int $maxSize,
        array $metadata = [],
        ?\Throwable $previous = null
    ): self {
        return new self(
            reason: "Metadata size {$actualSize} bytes exceeds maximum allowed size of {$maxSize} bytes",
            invalidMetadata: $metadata,
            previous: $previous
        );
    }

    /**
     * Create exception for metadata with invalid field types.
     */
    public static function invalidFieldType(
        string $fieldName,
        string $expectedType,
        string $actualType,
        array $metadata = [],
        ?\Throwable $previous = null
    ): self {
        return new self(
            reason: "Field '{$fieldName}' expected {$expectedType}, got {$actualType}",
            invalidMetadata: $metadata,
            previous: $previous
        );
    }

    /**
     * Create exception for metadata with forbidden field names.
     */
    public static function forbiddenField(
        string $fieldName,
        array $metadata = [],
        ?\Throwable $previous = null
    ): self {
        return new self(
            reason: "Field '{$fieldName}' is not allowed in metadata",
            invalidMetadata: $metadata,
            previous: $previous
        );
    }
}

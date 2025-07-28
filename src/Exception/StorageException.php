<?php

declare(strict_types=1);

namespace EdgeBinder\Exception;

/**
 * Exception thrown when storage operations fail.
 *
 * This exception wraps underlying storage system errors (database connection
 * failures, disk I/O errors, network timeouts, etc.) and provides a consistent
 * interface for handling storage-related failures.
 */
class StorageException extends EdgeBinderException
{
    public function __construct(
        string $operation,
        string $reason,
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            message: "Storage operation '{$operation}' failed: {$reason}",
            previous: $previous
        );
    }

    /**
     * Create exception for failed store operations.
     */
    public static function storeFailed(
        string $reason,
        ?\Throwable $previous = null
    ): self {
        return new self('store', $reason, $previous);
    }

    /**
     * Create exception for failed find operations.
     */
    public static function findFailed(
        string $reason,
        ?\Throwable $previous = null
    ): self {
        return new self('find', $reason, $previous);
    }

    /**
     * Create exception for failed delete operations.
     */
    public static function deleteFailed(
        string $reason,
        ?\Throwable $previous = null
    ): self {
        return new self('delete', $reason, $previous);
    }

    /**
     * Create exception for failed update operations.
     */
    public static function updateFailed(
        string $reason,
        ?\Throwable $previous = null
    ): self {
        return new self('update', $reason, $previous);
    }

    /**
     * Create exception for connection failures.
     */
    public static function connectionFailed(
        string $reason,
        ?\Throwable $previous = null
    ): self {
        return new self('connection', $reason, $previous);
    }
}

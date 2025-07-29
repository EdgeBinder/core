<?php

declare(strict_types=1);

namespace EdgeBinder\Exception;

/**
 * Exception thrown when a binding cannot be found.
 *
 * This exception is typically thrown when attempting to retrieve, update,
 * or delete a binding that does not exist in the storage system.
 */
class BindingNotFoundException extends EdgeBinderException
{
    public function __construct(
        string $bindingId,
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            message: "Binding with ID '{$bindingId}' was not found",
            previous: $previous
        );
    }

    /**
     * Create exception for a specific binding ID.
     */
    public static function withId(string $bindingId, ?\Throwable $previous = null): self
    {
        return new self($bindingId, $previous);
    }

    /**
     * Create exception for a binding that was expected between specific entities.
     */
    public static function betweenEntities(
        string $fromType,
        string $fromId,
        string $toType,
        string $toId,
        string $bindingType,
        ?\Throwable $previous = null
    ): self {
        $message = "Binding of type '{$bindingType}' between {$fromType}:{$fromId} and {$toType}:{$toId} was not found";

        $exception = new self('', $previous);
        $exception->message = $message;

        return $exception;
    }
}

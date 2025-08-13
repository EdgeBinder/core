<?php

declare(strict_types=1);

namespace EdgeBinder\Session;

use EdgeBinder\Contracts\BindingInterface;

/**
 * Represents an operation performed within a session.
 *
 * Used for tracking pending operations and managing flush behavior.
 */
class Operation
{
    public const TYPE_CREATE = 'create';
    public const TYPE_DELETE = 'delete';
    public const TYPE_UPDATE = 'update';

    public function __construct(
        private readonly string $type,
        private readonly BindingInterface $binding,
        private readonly \DateTimeImmutable $timestamp
    ) {
    }

    /**
     * Get the operation type.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the binding associated with this operation.
     */
    public function getBinding(): BindingInterface
    {
        return $this->binding;
    }

    /**
     * Get the binding ID.
     */
    public function getBindingId(): string
    {
        return $this->binding->getId();
    }

    /**
     * Get the operation timestamp.
     */
    public function getTimestamp(): \DateTimeImmutable
    {
        return $this->timestamp;
    }

    /**
     * Create a create operation.
     */
    public static function create(BindingInterface $binding): self
    {
        return new self(self::TYPE_CREATE, $binding, new \DateTimeImmutable());
    }

    /**
     * Create a delete operation.
     */
    public static function delete(BindingInterface $binding): self
    {
        return new self(self::TYPE_DELETE, $binding, new \DateTimeImmutable());
    }

    /**
     * Create an update operation.
     */
    public static function update(BindingInterface $binding): self
    {
        return new self(self::TYPE_UPDATE, $binding, new \DateTimeImmutable());
    }
}

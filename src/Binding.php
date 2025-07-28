<?php

declare(strict_types=1);

namespace EdgeBinder;

use EdgeBinder\Contracts\BindingInterface;

/**
 * Immutable binding implementation representing a relationship between two entities.
 *
 * This class provides a concrete implementation of the BindingInterface with:
 * - Immutable design for thread safety
 * - Rich metadata support for vector/graph databases
 * - Automatic timestamp management
 * - Helper methods for relationship queries
 * - Value object semantics
 */
readonly class Binding implements BindingInterface
{
    /**
     * Create a new binding instance.
     *
     * @param string                   $id        Unique binding identifier
     * @param string                   $fromType  Source entity type
     * @param string                   $fromId    Source entity identifier
     * @param string                   $toType    Target entity type
     * @param string                   $toId      Target entity identifier
     * @param string                   $type      Binding type/relationship name
     * @param array<string, mixed>     $metadata  Binding metadata
     * @param \DateTimeImmutable       $createdAt Creation timestamp
     * @param \DateTimeImmutable       $updatedAt Last update timestamp
     */
    public function __construct(
        private string $id,
        private string $fromType,
        private string $fromId,
        private string $toType,
        private string $toId,
        private string $type,
        private array $metadata,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * Create a new binding with generated ID and current timestamps.
     *
     * @param string               $fromType Source entity type
     * @param string               $fromId   Source entity identifier
     * @param string               $toType   Target entity type
     * @param string               $toId     Target entity identifier
     * @param string               $type     Binding type/relationship name
     * @param array<string, mixed> $metadata Optional binding metadata
     *
     * @return self New binding instance
     */
    public static function create(
        string $fromType,
        string $fromId,
        string $toType,
        string $toId,
        string $type,
        array $metadata = []
    ): self {
        $now = new \DateTimeImmutable();

        return new self(
            id: self::generateId(),
            fromType: $fromType,
            fromId: $fromId,
            toType: $toType,
            toId: $toId,
            type: $type,
            metadata: $metadata,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getFromType(): string
    {
        return $this->fromType;
    }

    public function getFromId(): string
    {
        return $this->fromId;
    }

    public function getToType(): string
    {
        return $this->toType;
    }

    public function getToId(): string
    {
        return $this->toId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function withMetadata(array $metadata): static
    {
        return new self(
            id: $this->id,
            fromType: $this->fromType,
            fromId: $this->fromId,
            toType: $this->toType,
            toId: $this->toId,
            type: $this->type,
            metadata: $metadata,
            createdAt: $this->createdAt,
            updatedAt: new \DateTimeImmutable(),
        );
    }

    public function connects(string $fromType, string $fromId, string $toType, string $toId): bool
    {
        return $this->fromType === $fromType
            && $this->fromId === $fromId
            && $this->toType === $toType
            && $this->toId === $toId;
    }

    public function involves(string $entityType, string $entityId): bool
    {
        return ($this->fromType === $entityType && $this->fromId === $entityId)
            || ($this->toType === $entityType && $this->toId === $entityId);
    }

    /**
     * Merge new metadata with existing metadata.
     *
     * @param array<string, mixed> $newMetadata Metadata to merge
     *
     * @return static New binding instance with merged metadata
     */
    public function mergeMetadata(array $newMetadata): static
    {
        return $this->withMetadata(array_merge($this->metadata, $newMetadata));
    }

    /**
     * Get a specific metadata value.
     *
     * @param string $key     Metadata key
     * @param mixed  $default Default value if key doesn't exist
     *
     * @return mixed Metadata value or default
     */
    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Check if metadata contains a specific key.
     *
     * @param string $key Metadata key to check
     *
     * @return bool True if key exists
     */
    public function hasMetadata(string $key): bool
    {
        return array_key_exists($key, $this->metadata);
    }

    /**
     * Get the reverse direction of this binding.
     *
     * Creates a new binding with from/to entities swapped.
     * Useful for bidirectional relationships.
     *
     * @param string|null              $reverseType Optional different type for reverse binding
     * @param array<string, mixed>|null $metadata    Optional different metadata for reverse binding
     *
     * @return static New binding with reversed direction
     */
    public function reverse(?string $reverseType = null, ?array $metadata = null): static
    {
        return new self(
            id: self::generateId(),
            fromType: $this->toType,
            fromId: $this->toId,
            toType: $this->fromType,
            toId: $this->fromId,
            type: $reverseType ?? $this->type,
            metadata: $metadata ?? $this->metadata,
            createdAt: $this->createdAt,
            updatedAt: new \DateTimeImmutable(),
        );
    }

    /**
     * Convert binding to array representation.
     *
     * @return array<string, mixed> Array representation of the binding
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'from_type' => $this->fromType,
            'from_id' => $this->fromId,
            'to_type' => $this->toType,
            'to_id' => $this->toId,
            'type' => $this->type,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt->format(\DateTimeInterface::ATOM),
            'updated_at' => $this->updatedAt->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * Create binding from array representation.
     *
     * @param array<string, mixed> $data Array data
     *
     * @return self New binding instance
     *
     * @throws \InvalidArgumentException If required fields are missing
     */
    public static function fromArray(array $data): self
    {
        $requiredFields = ['id', 'from_type', 'from_id', 'to_type', 'to_id', 'type', 'created_at', 'updated_at'];

        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $data)) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        return new self(
            id: $data['id'],
            fromType: $data['from_type'],
            fromId: $data['from_id'],
            toType: $data['to_type'],
            toId: $data['to_id'],
            type: $data['type'],
            metadata: $data['metadata'] ?? [],
            createdAt: new \DateTimeImmutable($data['created_at']),
            updatedAt: new \DateTimeImmutable($data['updated_at']),
        );
    }

    /**
     * Generate a unique binding identifier.
     *
     * @return string Unique identifier
     */
    private static function generateId(): string
    {
        return 'binding_' . bin2hex(random_bytes(16));
    }
}

<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Integration;

use EdgeBinder\Contracts\BindingInterface;
use EdgeBinder\Contracts\PersistenceAdapterInterface;
use EdgeBinder\Contracts\QueryBuilderInterface;
use EdgeBinder\Registry\AdapterFactoryInterface;

/**
 * Mock adapter factory for integration testing.
 *
 * This factory creates mock adapters that can be used to test the
 * adapter registry integration without requiring real storage backends.
 */
final class MockAdapterFactory implements AdapterFactoryInterface
{
    public function __construct(
        private readonly string $adapterType = 'mock',
        private readonly ?PersistenceAdapterInterface $adapter = null
    ) {
    }

    public function createAdapter(array $config): PersistenceAdapterInterface
    {
        // Return pre-configured adapter if provided, otherwise create a new mock
        if ($this->adapter !== null) {
            return $this->adapter;
        }

        return new MockAdapter($config);
    }

    public function getAdapterType(): string
    {
        return $this->adapterType;
    }
}

/**
 * Mock persistence adapter for testing.
 *
 * This adapter provides a minimal implementation that can be used
 * for testing the EdgeBinder integration without real storage.
 */
final class MockAdapter implements PersistenceAdapterInterface
{
    /** @var array<string, BindingInterface> */
    private array $bindings = [];

    /** @var array<string, mixed> */
    private array $config;

    /** @param array<string, mixed> $config */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function store(BindingInterface $binding): void
    {
        $this->bindings[$binding->getId()] = $binding;
    }

    public function find(string $bindingId): ?BindingInterface
    {
        return $this->bindings[$bindingId] ?? null;
    }

    public function delete(string $bindingId): void
    {
        unset($this->bindings[$bindingId]);
    }

    public function executeQuery(QueryBuilderInterface $query): array
    {
        // Simple implementation for testing
        return array_values($this->bindings);
    }

    public function count(QueryBuilderInterface $query): int
    {
        // Simple implementation for testing - just return count of all bindings
        return count($this->bindings);
    }

    public function extractEntityId(object $entity): string
    {
        // Simple implementation for testing
        if (method_exists($entity, 'getId')) {
            return (string) $entity->getId();
        }

        if (property_exists($entity, 'id')) {
            return (string) $entity->id;
        }

        return spl_object_hash($entity);
    }

    public function extractEntityType(object $entity): string
    {
        // Simple implementation for testing
        if (method_exists($entity, 'getType')) {
            return $entity->getType();
        }

        return get_class($entity);
    }

    public function validateAndNormalizeMetadata(array $metadata): array
    {
        // Simple implementation for testing - just return as-is
        return $metadata;
    }

    public function findByEntity(string $entityType, string $entityId): array
    {
        $result = [];
        foreach ($this->bindings as $binding) {
            if (($binding->getFromType() === $entityType && $binding->getFromId() === $entityId) ||
                ($binding->getToType() === $entityType && $binding->getToId() === $entityId)) {
                $result[] = $binding;
            }
        }
        return $result;
    }

    public function findBetweenEntities(
        string $fromType,
        string $fromId,
        string $toType,
        string $toId,
        ?string $bindingType = null
    ): array {
        $result = [];
        foreach ($this->bindings as $binding) {
            if ($binding->getFromType() === $fromType &&
                $binding->getFromId() === $fromId &&
                $binding->getToType() === $toType &&
                $binding->getToId() === $toId &&
                ($bindingType === null || $binding->getType() === $bindingType)) {
                $result[] = $binding;
            }
        }
        return $result;
    }

    public function deleteByEntity(string $entityType, string $entityId): int
    {
        $deletedCount = 0;
        foreach ($this->bindings as $id => $binding) {
            if (($binding->getFromType() === $entityType && $binding->getFromId() === $entityId) ||
                ($binding->getToType() === $entityType && $binding->getToId() === $entityId)) {
                unset($this->bindings[$id]);
                $deletedCount++;
            }
        }
        return $deletedCount;
    }

    public function updateMetadata(string $bindingId, array $metadata): void
    {
        if (isset($this->bindings[$bindingId])) {
            // For testing purposes, we'll create a new binding with updated metadata
            $binding = $this->bindings[$bindingId];
            $updatedBinding = new class($binding, $metadata) implements BindingInterface {
                public function __construct(
                    private readonly BindingInterface $original,
                    /** @var array<string, mixed> */
                    private readonly array $newMetadata
                ) {
                }

                public function getId(): string
                {
                    return $this->original->getId();
                }

                public function getFromType(): string
                {
                    return $this->original->getFromType();
                }

                public function getFromId(): string
                {
                    return $this->original->getFromId();
                }

                public function getToType(): string
                {
                    return $this->original->getToType();
                }

                public function getToId(): string
                {
                    return $this->original->getToId();
                }

                public function getType(): string
                {
                    return $this->original->getType();
                }

                public function getMetadata(): array
                {
                    return $this->newMetadata;
                }

                public function getCreatedAt(): \DateTimeImmutable
                {
                    return $this->original->getCreatedAt();
                }

                public function getUpdatedAt(): \DateTimeImmutable
                {
                    return new \DateTimeImmutable();
                }

                public function withMetadata(array $metadata): static
                {
                    return new self($this->original, $metadata);
                }

                public function connects(string $fromType, string $fromId, string $toType, string $toId): bool
                {
                    return $this->original->connects($fromType, $fromId, $toType, $toId);
                }

                public function involves(string $entityType, string $entityId): bool
                {
                    return $this->original->involves($entityType, $entityId);
                }

                /** @return array<string, mixed> */
                public function toArray(): array
                {
                    return [
                        'id' => $this->getId(),
                        'from_type' => $this->getFromType(),
                        'from_id' => $this->getFromId(),
                        'to_type' => $this->getToType(),
                        'to_id' => $this->getToId(),
                        'type' => $this->getType(),
                        'metadata' => $this->getMetadata(),
                        'created_at' => $this->getCreatedAt()->format('c'),
                        'updated_at' => $this->getUpdatedAt()->format('c'),
                    ];
                }
            };

            $this->bindings[$bindingId] = $updatedBinding;
        }
    }

    /**
     * Get the configuration passed to this adapter.
     *
     * This method is useful for testing to verify that configuration
     * was passed correctly from the factory.
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}

<?php

declare(strict_types=1);

namespace EdgeBinder;

use EdgeBinder\Contracts\BindingInterface;
use EdgeBinder\Contracts\EdgeBinderInterface;
use EdgeBinder\Contracts\PersistenceAdapterInterface;
use EdgeBinder\Contracts\QueryBuilderInterface;
use EdgeBinder\Exception\AdapterException;
use EdgeBinder\Exception\BindingNotFoundException;
use EdgeBinder\Exception\InvalidMetadataException;
use EdgeBinder\Exception\PersistenceException;
use EdgeBinder\Query\BindingQueryBuilder;
use EdgeBinder\Registry\AdapterConfiguration;
use EdgeBinder\Registry\AdapterRegistry;
use Psr\Container\ContainerInterface;

/**
 * Main EdgeBinder service implementation.
 *
 * EdgeBinder provides the primary API for managing entity relationships
 * with rich metadata support. It acts as a facade over the persistence layer,
 * providing a clean, consistent interface regardless of the underlying
 * storage implementation.
 *
 * Key responsibilities:
 * - Creating and managing bindings between entities
 * - Querying relationships with flexible criteria
 * - Updating relationship metadata
 * - Providing a storage-agnostic API
 */
class EdgeBinder implements EdgeBinderInterface
{
    /**
     * EdgeBinder version for compatibility checks.
     */
    public const VERSION = '0.7.2';

    /**
     * Create a new EdgeBinder instance.
     *
     * @param PersistenceAdapterInterface $persistenceAdapter Storage adapter for persistence operations
     */
    public function __construct(
        private readonly PersistenceAdapterInterface $persistenceAdapter,
    ) {
    }

    /**
     * Create EdgeBinder instance from configuration using registered adapters.
     *
     * This factory method enables framework-agnostic adapter creation through
     * the adapter registry system. It supports third-party adapters that have
     * been registered via AdapterRegistry::register().
     *
     * Configuration format:
     * ```php
     * $config = [
     *     'adapter' => 'inmemory',  // Required: adapter type
     *     'validateMetadata' => true,  // Adapter-specific config
     *     'maxBindings' => 10000,  // More adapter-specific config
     * ];
     * ```
     *
     * Framework usage examples:
     * ```php
     * // Laminas/Mezzio
     * $edgeBinder = EdgeBinder::fromConfiguration($config, $container);
     *
     * // Symfony
     * $edgeBinder = EdgeBinder::fromConfiguration($config, $this->container);
     *
     * // Laravel
     * $edgeBinder = EdgeBinder::fromConfiguration($config, app());
     * ```
     *
     * @param array<string, mixed> $config       Instance configuration containing adapter type and settings
     * @param ContainerInterface   $container    PSR-11 container for dependency injection
     * @param array<string, mixed> $globalConfig Optional global EdgeBinder configuration
     *
     * @return self Configured EdgeBinder instance
     *
     * @throws \InvalidArgumentException If required configuration is missing
     * @throws AdapterException          If adapter type is not registered or creation fails
     */
    public static function fromConfiguration(
        array $config,
        ContainerInterface $container,
        array $globalConfig = []
    ): self {
        // Validate required configuration
        if (!isset($config['adapter'])) {
            throw new \InvalidArgumentException("Configuration must contain 'adapter' key specifying the adapter type. ".'Available types: '.implode(', ', AdapterRegistry::getRegisteredTypes()));
        }

        $adapterType = $config['adapter'];
        if (!is_string($adapterType) || empty($adapterType)) {
            throw new \InvalidArgumentException('Adapter type must be a non-empty string, got: '.gettype($adapterType));
        }

        // Build adapter configuration object
        $adapterConfig = new AdapterConfiguration(
            instance: $config,
            global: $globalConfig,
            container: $container
        );

        // Create adapter through registry
        $adapter = AdapterRegistry::create($adapterType, $adapterConfig);

        return new self($adapter);
    }

    /**
     * Create EdgeBinder instance from an adapter (backward compatibility).
     *
     * This factory method provides a consistent factory pattern while maintaining
     * backward compatibility with direct adapter injection.
     *
     * @param PersistenceAdapterInterface $adapter The persistence adapter to use
     *
     * @return self EdgeBinder instance with the provided adapter
     */
    public static function fromAdapter(PersistenceAdapterInterface $adapter): self
    {
        return new self($adapter);
    }

    public function bind(
        object $from,
        object $to,
        string $type,
        array $metadata = []
    ): BindingInterface {
        // Extract entity information using the persistence adapter
        $fromType = $this->persistenceAdapter->extractEntityType($from);
        $fromId = $this->persistenceAdapter->extractEntityId($from);
        $toType = $this->persistenceAdapter->extractEntityType($to);
        $toId = $this->persistenceAdapter->extractEntityId($to);

        // Validate and normalize metadata
        $normalizedMetadata = $this->persistenceAdapter->validateAndNormalizeMetadata($metadata);

        // Create the binding
        $binding = Binding::create(
            fromType: $fromType,
            fromId: $fromId,
            toType: $toType,
            toId: $toId,
            type: $type,
            metadata: $normalizedMetadata
        );

        // Store the binding
        $this->persistenceAdapter->store($binding);

        return $binding;
    }

    public function unbind(string $bindingId): void
    {
        $this->persistenceAdapter->delete($bindingId);
    }

    public function unbindEntities(object $from, object $to, ?string $type = null): int
    {
        $fromType = $this->persistenceAdapter->extractEntityType($from);
        $fromId = $this->persistenceAdapter->extractEntityId($from);
        $toType = $this->persistenceAdapter->extractEntityType($to);
        $toId = $this->persistenceAdapter->extractEntityId($to);

        $bindings = $this->persistenceAdapter->findBetweenEntities(
            fromType: $fromType,
            fromId: $fromId,
            toType: $toType,
            toId: $toId,
            bindingType: $type
        );

        $deletedCount = 0;
        foreach ($bindings as $binding) {
            $this->persistenceAdapter->delete($binding->getId());
            ++$deletedCount;
        }

        return $deletedCount;
    }

    public function unbindEntity(object $entity): int
    {
        $entityType = $this->persistenceAdapter->extractEntityType($entity);
        $entityId = $this->persistenceAdapter->extractEntityId($entity);

        return $this->persistenceAdapter->deleteByEntity($entityType, $entityId);
    }

    public function query(): QueryBuilderInterface
    {
        return new BindingQueryBuilder($this->persistenceAdapter);
    }

    public function findBinding(string $bindingId): ?BindingInterface
    {
        return $this->persistenceAdapter->find($bindingId);
    }

    public function findBindingsFor(object $entity): array
    {
        $entityType = $this->persistenceAdapter->extractEntityType($entity);
        $entityId = $this->persistenceAdapter->extractEntityId($entity);

        return $this->persistenceAdapter->findByEntity($entityType, $entityId);
    }

    public function findBindingsBetween(object $from, object $to, ?string $type = null): array
    {
        $fromType = $this->persistenceAdapter->extractEntityType($from);
        $fromId = $this->persistenceAdapter->extractEntityId($from);
        $toType = $this->persistenceAdapter->extractEntityType($to);
        $toId = $this->persistenceAdapter->extractEntityId($to);

        return $this->persistenceAdapter->findBetweenEntities(
            fromType: $fromType,
            fromId: $fromId,
            toType: $toType,
            toId: $toId,
            bindingType: $type
        );
    }

    public function areBound(object $from, object $to, ?string $type = null): bool
    {
        $bindings = $this->findBindingsBetween($from, $to, $type);

        return count($bindings) > 0;
    }

    public function updateMetadata(string $bindingId, array $metadata): BindingInterface
    {
        // Find the existing binding
        $binding = $this->findBinding($bindingId);
        if (null === $binding) {
            throw BindingNotFoundException::withId($bindingId);
        }

        // Validate and normalize the new metadata
        $normalizedMetadata = $this->persistenceAdapter->validateAndNormalizeMetadata($metadata);

        // Merge with existing metadata
        $mergedMetadata = array_merge($binding->getMetadata(), $normalizedMetadata);

        // Update the binding
        $this->persistenceAdapter->updateMetadata($bindingId, $mergedMetadata);

        // Return the updated binding
        $updatedBinding = $this->findBinding($bindingId);
        if (null === $updatedBinding) {
            throw new PersistenceException('update', 'Failed to retrieve updated binding');
        }

        return $updatedBinding;
    }

    public function replaceMetadata(string $bindingId, array $metadata): BindingInterface
    {
        // Find the existing binding
        $binding = $this->findBinding($bindingId);
        if (null === $binding) {
            throw BindingNotFoundException::withId($bindingId);
        }

        // Validate and normalize the new metadata
        $normalizedMetadata = $this->persistenceAdapter->validateAndNormalizeMetadata($metadata);

        // Replace the metadata entirely
        $this->persistenceAdapter->updateMetadata($bindingId, $normalizedMetadata);

        // Return the updated binding
        $updatedBinding = $this->findBinding($bindingId);
        if (null === $updatedBinding) {
            throw new PersistenceException('update', 'Failed to retrieve updated binding');
        }

        return $updatedBinding;
    }

    public function getMetadata(string $bindingId): array
    {
        $binding = $this->findBinding($bindingId);
        if (null === $binding) {
            throw BindingNotFoundException::withId($bindingId);
        }

        return $binding->getMetadata();
    }

    public function getStorageAdapter(): PersistenceAdapterInterface
    {
        return $this->persistenceAdapter;
    }

    /**
     * Create multiple bindings in a batch operation.
     *
     * @param array<array{from: object, to: object, type: string, metadata?: array<string, mixed>}> $bindings Array of binding specifications
     *
     * @return BindingInterface[] Array of created bindings
     *
     * @throws InvalidMetadataException If any metadata is invalid
     * @throws PersistenceException     If any binding cannot be stored
     */
    public function bindMany(array $bindings): array
    {
        $createdBindings = [];

        foreach ($bindings as $bindingSpec) {
            $metadata = $bindingSpec['metadata'] ?? [];

            $binding = $this->bind(
                from: $bindingSpec['from'],
                to: $bindingSpec['to'],
                type: $bindingSpec['type'],
                metadata: $metadata
            );

            $createdBindings[] = $binding;
        }

        return $createdBindings;
    }

    /**
     * Check if an entity has any bindings.
     *
     * @param object $entity The entity to check
     *
     * @return bool True if the entity has any bindings
     *
     * @throws PersistenceException If the query fails
     */
    public function hasBindings(object $entity): bool
    {
        $bindings = $this->findBindingsFor($entity);

        return count($bindings) > 0;
    }

    /**
     * Count bindings for an entity.
     *
     * @param object      $entity The entity to count bindings for
     * @param string|null $type   Optional binding type filter
     *
     * @return int Number of bindings
     *
     * @throws PersistenceException If the query fails
     */
    public function countBindingsFor(object $entity, ?string $type = null): int
    {
        $query = $this->query()->from($entity);

        if (null !== $type) {
            $query = $query->type($type);
        }

        return $query->count();
    }
}

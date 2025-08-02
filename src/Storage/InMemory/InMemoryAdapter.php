<?php

declare(strict_types=1);

namespace EdgeBinder\Storage\InMemory;

use EdgeBinder\Contracts\PersistenceAdapterInterface;
use EdgeBinder\Contracts\BindingInterface;
use EdgeBinder\Contracts\QueryBuilderInterface;
use EdgeBinder\Contracts\EntityInterface;
use EdgeBinder\Exception\PersistenceException;
use EdgeBinder\Exception\EntityExtractionException;
use EdgeBinder\Exception\InvalidMetadataException;
use EdgeBinder\Exception\BindingNotFoundException;
use EdgeBinder\Binding;

/**
 * In-memory persistence adapter for EdgeBinder.
 *
 * This adapter stores bindings in memory using PHP arrays with efficient indexing
 * for fast queries. It's designed for testing, development, and small-scale applications
 * where persistence across requests is not required.
 *
 * Features:
 * - Fast in-memory storage with efficient indexing
 * - Full query support with filtering, ordering, and pagination
 * - Comprehensive metadata validation
 * - Standard entity extraction patterns
 * - Thread-safe operations (within single request)
 *
 * Limitations:
 * - Data is lost when the process ends
 * - Memory usage grows with the number of bindings
 * - Not suitable for large-scale production use
 * - No persistence across requests
 */
final class InMemoryAdapter implements PersistenceAdapterInterface
{
    /** @var array<string, BindingInterface> Main storage indexed by binding ID */
    private array $bindings = [];

    /** @var array<string, array<string, string[]>> Entity index: [entityType][entityId] => [bindingId, ...] */
    private array $entityIndex = [];

    /** @var array<string, string[]> Type index: [bindingType] => [bindingId, ...] */
    private array $typeIndex = [];

    public function extractEntityId(object $entity): string
    {
        // Try EntityInterface first
        if ($entity instanceof EntityInterface) {
            return $entity->getId();
        }

        // Try getId() method
        if (method_exists($entity, 'getId')) {
            $id = $entity->getId();
            if (is_string($id) && !empty($id)) {
                return $id;
            }
            if (is_int($id) || is_float($id)) {
                return (string) $id;
            }
        }

        // Try id property via reflection
        try {
            $reflection = new \ReflectionObject($entity);
            if ($reflection->hasProperty('id')) {
                $property = $reflection->getProperty('id');
                $property->setAccessible(true);
                $id = $property->getValue($entity);
                
                if (is_string($id) && !empty($id)) {
                    return $id;
                }
                if (is_int($id) || is_float($id)) {
                    return (string) $id;
                }
            }
        } catch (\ReflectionException $e) {
            // Fall through to object hash
        }

        // Last resort: use object hash
        return spl_object_hash($entity);
    }

    public function extractEntityType(object $entity): string
    {
        // Try EntityInterface first
        if ($entity instanceof EntityInterface) {
            return $entity->getType();
        }

        // Try getType() method
        if (method_exists($entity, 'getType')) {
            $type = $entity->getType();
            if (is_string($type) && !empty($type)) {
                return $type;
            }
        }

        // Fall back to class name
        return get_class($entity);
    }

    public function validateAndNormalizeMetadata(array $metadata): array
    {
        return $this->validateMetadataRecursive($metadata, 0);
    }

    /**
     * Recursively validate metadata with depth checking.
     *
     * @param array<string, mixed> $data  The data to validate
     * @param int                  $depth Current nesting depth
     *
     * @return array<string, mixed> Normalized metadata
     *
     * @throws InvalidMetadataException If metadata is invalid
     */
    private function validateMetadataRecursive(array $data, int $depth): array
    {
        if ($depth >= 10) {
            throw new InvalidMetadataException('Metadata nesting too deep (max 10 levels)');
        }

        $normalized = [];

        foreach ($data as $key => $value) {
            if (!is_string($key)) {
                throw new InvalidMetadataException('Metadata keys must be strings');
            }

            if (is_resource($value)) {
                throw new InvalidMetadataException('Metadata cannot contain resources');
            }

            if (is_object($value)) {
                if ($value instanceof \DateTimeInterface) {
                    // Convert DateTime objects to ISO 8601 strings
                    $normalized[$key] = $value->format(\DateTimeInterface::ATOM);
                } else {
                    throw new InvalidMetadataException('Metadata can only contain DateTime objects, not ' . get_class($value));
                }
            } elseif (is_array($value)) {
                $normalized[$key] = $this->validateMetadataRecursive($value, $depth + 1);
            } else {
                // Scalar values are allowed as-is
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    public function store(BindingInterface $binding): void
    {
        try {
            $id = $binding->getId();
            
            // Validate metadata
            $this->validateAndNormalizeMetadata($binding->getMetadata());
            
            // Store binding
            $this->bindings[$id] = $binding;
            
            // Update indexes
            $this->updateIndexes($binding);
            
        } catch (InvalidMetadataException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new PersistenceException('store', 'Failed to store binding: ' . $e->getMessage(), $e);
        }
    }

    /**
     * Update internal indexes for efficient querying.
     */
    private function updateIndexes(BindingInterface $binding): void
    {
        $id = $binding->getId();
        
        // Update entity index for from entity
        $fromKey = $binding->getFromType();
        $fromId = $binding->getFromId();
        if (!isset($this->entityIndex[$fromKey])) {
            $this->entityIndex[$fromKey] = [];
        }
        if (!isset($this->entityIndex[$fromKey][$fromId])) {
            $this->entityIndex[$fromKey][$fromId] = [];
        }
        if (!in_array($id, $this->entityIndex[$fromKey][$fromId], true)) {
            $this->entityIndex[$fromKey][$fromId][] = $id;
        }
        
        // Update entity index for to entity
        $toKey = $binding->getToType();
        $toId = $binding->getToId();
        if (!isset($this->entityIndex[$toKey])) {
            $this->entityIndex[$toKey] = [];
        }
        if (!isset($this->entityIndex[$toKey][$toId])) {
            $this->entityIndex[$toKey][$toId] = [];
        }
        if (!in_array($id, $this->entityIndex[$toKey][$toId], true)) {
            $this->entityIndex[$toKey][$toId][] = $id;
        }
        
        // Update type index
        $type = $binding->getType();
        if (!isset($this->typeIndex[$type])) {
            $this->typeIndex[$type] = [];
        }
        if (!in_array($id, $this->typeIndex[$type], true)) {
            $this->typeIndex[$type][] = $id;
        }
    }

    public function find(string $bindingId): ?BindingInterface
    {
        return $this->bindings[$bindingId] ?? null;
    }

    public function findByEntity(string $entityType, string $entityId): array
    {
        $bindingIds = $this->entityIndex[$entityType][$entityId] ?? [];
        
        $bindings = [];
        foreach ($bindingIds as $id) {
            if (isset($this->bindings[$id])) {
                $bindings[] = $this->bindings[$id];
            }
        }
        
        return $bindings;
    }

    public function findBetweenEntities(
        string $fromType,
        string $fromId,
        string $toType,
        string $toId,
        ?string $bindingType = null
    ): array {
        $results = [];

        foreach ($this->bindings as $binding) {
            if ($binding->getFromType() === $fromType
                && $binding->getFromId() === $fromId
                && $binding->getToType() === $toType
                && $binding->getToId() === $toId
                && ($bindingType === null || $binding->getType() === $bindingType)
            ) {
                $results[] = $binding;
            }
        }

        return $results;
    }

    public function executeQuery(QueryBuilderInterface $query): array
    {
        try {
            $criteria = $query->getCriteria();
            $results = $this->filterBindings($criteria);

            // Apply ordering
            if (isset($criteria['orderBy'])) {
                $results = $this->applyOrdering($results, $criteria['orderBy']);
            }

            // Apply pagination
            if (isset($criteria['offset']) || isset($criteria['limit'])) {
                $offset = $criteria['offset'] ?? 0;
                $limit = $criteria['limit'] ?? null;
                $results = array_slice($results, $offset, $limit);
            }

            return array_values($results);
        } catch (\Throwable $e) {
            throw new PersistenceException('query', 'Query execution failed: ' . $e->getMessage(), $e);
        }
    }

    public function count(QueryBuilderInterface $query): int
    {
        try {
            $criteria = $query->getCriteria();
            $results = $this->filterBindings($criteria);
            return count($results);
        } catch (\Throwable $e) {
            throw new PersistenceException('count', 'Query count failed: ' . $e->getMessage(), $e);
        }
    }

    public function updateMetadata(string $bindingId, array $metadata): void
    {
        if (!isset($this->bindings[$bindingId])) {
            throw new BindingNotFoundException("Binding with ID '{$bindingId}' not found");
        }

        try {
            // Validate new metadata
            $normalizedMetadata = $this->validateAndNormalizeMetadata($metadata);

            // Update binding with new metadata
            $binding = $this->bindings[$bindingId];
            $updatedBinding = $binding->withMetadata($normalizedMetadata);
            $this->bindings[$bindingId] = $updatedBinding;

        } catch (InvalidMetadataException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new PersistenceException('update', 'Failed to update metadata: ' . $e->getMessage(), $e);
        }
    }

    public function delete(string $bindingId): void
    {
        if (!isset($this->bindings[$bindingId])) {
            throw new BindingNotFoundException("Binding with ID '{$bindingId}' not found");
        }

        $binding = $this->bindings[$bindingId];

        // Remove from main storage
        unset($this->bindings[$bindingId]);

        // Remove from indexes
        $this->removeFromIndexes($binding);
    }

    public function deleteByEntity(string $entityType, string $entityId): int
    {
        $bindingsToDelete = $this->findByEntity($entityType, $entityId);
        $deletedCount = 0;

        foreach ($bindingsToDelete as $binding) {
            try {
                $this->delete($binding->getId());
                $deletedCount++;
            } catch (BindingNotFoundException $e) {
                // Binding was already deleted, continue
            }
        }

        return $deletedCount;
    }

    /**
     * Remove binding from all indexes.
     */
    private function removeFromIndexes(BindingInterface $binding): void
    {
        $id = $binding->getId();

        // Remove from entity index (from entity)
        $fromType = $binding->getFromType();
        $fromId = $binding->getFromId();
        if (isset($this->entityIndex[$fromType][$fromId])) {
            $this->entityIndex[$fromType][$fromId] = array_filter(
                $this->entityIndex[$fromType][$fromId],
                fn($bindingId) => $bindingId !== $id
            );
            if (empty($this->entityIndex[$fromType][$fromId])) {
                unset($this->entityIndex[$fromType][$fromId]);
                if (empty($this->entityIndex[$fromType])) {
                    unset($this->entityIndex[$fromType]);
                }
            }
        }

        // Remove from entity index (to entity)
        $toType = $binding->getToType();
        $toId = $binding->getToId();
        if (isset($this->entityIndex[$toType][$toId])) {
            $this->entityIndex[$toType][$toId] = array_filter(
                $this->entityIndex[$toType][$toId],
                fn($bindingId) => $bindingId !== $id
            );
            if (empty($this->entityIndex[$toType][$toId])) {
                unset($this->entityIndex[$toType][$toId]);
                if (empty($this->entityIndex[$toType])) {
                    unset($this->entityIndex[$toType]);
                }
            }
        }

        // Remove from type index
        $type = $binding->getType();
        if (isset($this->typeIndex[$type])) {
            $this->typeIndex[$type] = array_filter(
                $this->typeIndex[$type],
                fn($bindingId) => $bindingId !== $id
            );
            if (empty($this->typeIndex[$type])) {
                unset($this->typeIndex[$type]);
            }
        }
    }

    /**
     * Filter bindings based on query criteria.
     *
     * @param array<string, mixed> $criteria Query criteria from QueryBuilder
     *
     * @return BindingInterface[] Filtered bindings
     */
    private function filterBindings(array $criteria): array
    {
        $results = array_values($this->bindings);

        // Filter by from entity
        if (isset($criteria['from'])) {
            $fromType = $criteria['from']['type'];
            $fromId = $criteria['from']['id'];
            $results = array_filter($results, fn(BindingInterface $binding) =>
                $binding->getFromType() === $fromType && $binding->getFromId() === $fromId
            );
        }

        // Filter by to entity
        if (isset($criteria['to'])) {
            $toType = $criteria['to']['type'];
            $toId = $criteria['to']['id'];
            $results = array_filter($results, fn(BindingInterface $binding) =>
                $binding->getToType() === $toType && $binding->getToId() === $toId
            );
        }

        // Filter by binding type
        if (isset($criteria['type'])) {
            $type = $criteria['type'];
            $results = array_filter($results, fn(BindingInterface $binding) =>
                $binding->getType() === $type
            );
        }

        // Apply where conditions
        if (isset($criteria['where'])) {
            foreach ($criteria['where'] as $condition) {
                $results = $this->applyWhereCondition($results, $condition);
            }
        }

        // Apply OR conditions
        if (isset($criteria['orWhere'])) {
            foreach ($criteria['orWhere'] as $orGroup) {
                $orResults = array_values($this->bindings);
                foreach ($orGroup as $condition) {
                    $orResults = $this->applyWhereCondition($orResults, $condition);
                }
                // Merge OR results with main results
                $results = array_merge($results, $orResults);
                $results = array_unique($results, SORT_REGULAR);
            }
        }

        return array_values($results);
    }

    /**
     * Apply a single where condition to filter bindings.
     *
     * @param BindingInterface[]   $bindings  Bindings to filter
     * @param array<string, mixed> $condition Where condition
     *
     * @return BindingInterface[] Filtered bindings
     */
    private function applyWhereCondition(array $bindings, array $condition): array
    {
        $field = $condition['field'];
        $operator = $condition['operator'];
        $value = $condition['value'] ?? null;

        return array_filter($bindings, function (BindingInterface $binding) use ($field, $operator, $value) {
            $metadata = $binding->getMetadata();
            $fieldValue = $metadata[$field] ?? null;

            return match ($operator) {
                '=' => $fieldValue === $value,
                '!=' => $fieldValue !== $value,
                '>' => $fieldValue > $value,
                '>=' => $fieldValue >= $value,
                '<' => $fieldValue < $value,
                '<=' => $fieldValue <= $value,
                'in' => is_array($value) && in_array($fieldValue, $value, true),
                'not_in' => is_array($value) && !in_array($fieldValue, $value, true),
                'between' => is_array($value) && count($value) === 2 &&
                           $fieldValue >= $value[0] && $fieldValue <= $value[1],
                'exists' => array_key_exists($field, $metadata),
                'null' => !array_key_exists($field, $metadata) || $fieldValue === null,
                'not_null' => array_key_exists($field, $metadata) && $fieldValue !== null,
                default => throw new PersistenceException('query', "Unsupported operator: {$operator}"),
            };
        });
    }

    /**
     * Apply ordering to bindings.
     *
     * @param BindingInterface[]   $bindings Bindings to order
     * @param array<string, mixed> $orderBy  Order criteria
     *
     * @return BindingInterface[] Ordered bindings
     */
    private function applyOrdering(array $bindings, array $orderBy): array
    {
        $field = $orderBy['field'];
        $direction = strtolower($orderBy['direction'] ?? 'asc');

        usort($bindings, function (BindingInterface $a, BindingInterface $b) use ($field, $direction) {
            $valueA = $this->getOrderingValue($a, $field);
            $valueB = $this->getOrderingValue($b, $field);

            $comparison = $valueA <=> $valueB;

            return $direction === 'desc' ? -$comparison : $comparison;
        });

        return $bindings;
    }

    /**
     * Get value for ordering from binding.
     *
     * @param BindingInterface $binding The binding
     * @param string           $field   The field to get value for
     *
     * @return mixed The value for ordering
     */
    private function getOrderingValue(BindingInterface $binding, string $field): mixed
    {
        return match ($field) {
            'id' => $binding->getId(),
            'fromType' => $binding->getFromType(),
            'fromId' => $binding->getFromId(),
            'toType' => $binding->getToType(),
            'toId' => $binding->getToId(),
            'type' => $binding->getType(),
            'createdAt' => $binding->getCreatedAt()->getTimestamp(),
            'updatedAt' => $binding->getUpdatedAt()->getTimestamp(),
            default => $binding->getMetadata()[$field] ?? null,
        };
    }
}

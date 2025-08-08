<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Support;

use EdgeBinder\Contracts\BindingInterface;
use EdgeBinder\Contracts\CriteriaTransformerInterface;
use EdgeBinder\Contracts\PersistenceAdapterInterface;
use EdgeBinder\Contracts\QueryBuilderInterface;
use EdgeBinder\Contracts\QueryResultInterface;
use EdgeBinder\Exception\BindingNotFoundException;
use EdgeBinder\Exception\EntityExtractionException;
use EdgeBinder\Query\QueryCriteria;
use EdgeBinder\Query\QueryResult;

/**
 * Mock adapter demonstrating the transformer pattern.
 *
 * This shows how a real adapter would be much lighter when using
 * the criteria transformer pattern.
 */
class MockAdapter implements PersistenceAdapterInterface
{
    private CriteriaTransformerInterface $transformer;
    /** @var array<string, BindingInterface> */
    private array $bindings = [];

    public function __construct(?CriteriaTransformerInterface $transformer = null)
    {
        $this->transformer = $transformer ?? new MockCriteriaTransformer();
    }

    public function executeQuery(QueryCriteria $criteria): QueryResultInterface
    {
        // This is the key benefit - adapter just asks criteria to transform itself!
        $transformedQuery = $criteria->transform($this->transformer);

        // Simulate query execution with the transformed query
        $results = $this->simulateQueryExecution($transformedQuery);

        return new QueryResult($results);
    }

    public function count(QueryCriteria $criteria): int
    {
        $result = $this->executeQuery($criteria);

        return $result->count();
    }

    /**
     * Simulate query execution - in a real adapter this would execute
     * the native query against the actual storage backend.
     *
     * @return array<BindingInterface>
     */
    private function simulateQueryExecution(mixed $transformedQuery): array
    {
        // For demonstration, just return some mock bindings
        // In a real adapter, this would execute the transformed query
        // The $transformedQuery would be used to filter/sort the results
        unset($transformedQuery); // Suppress unused parameter warning
        return array_slice($this->bindings, 0, 2); // Return first 2 bindings
    }

    // Standard adapter methods (unchanged by transformer pattern)

    public function store(BindingInterface $binding): void
    {
        $this->bindings[$binding->getId()] = $binding;
    }

    public function find(string $id): ?BindingInterface
    {
        return $this->bindings[$id] ?? null;
    }

    public function delete(string $id): void
    {
        if (!isset($this->bindings[$id])) {
            throw new BindingNotFoundException("Binding with ID {$id} not found");
        }

        unset($this->bindings[$id]);
    }

    public function query(): QueryBuilderInterface
    {
        // Return EdgeBinder's query builder - adapter doesn't need its own
        return new \EdgeBinder\Query\BindingQueryBuilder($this);
    }

    public function validateAndNormalizeMetadata(array $metadata): array
    {
        return $metadata; // Simple validation for mock
    }

    public function extractEntityId(object $entity): string
    {
        if (method_exists($entity, 'getId')) {
            return (string) $entity->getId();
        }

        if (property_exists($entity, 'id')) {
            return (string) $entity->id;
        }

        throw new EntityExtractionException('Cannot extract ID from entity', $entity);
    }

    public function extractEntityType(object $entity): string
    {
        return basename(str_replace('\\', '/', get_class($entity)));
    }

    /**
     * @return BindingInterface[]
     */
    public function findByEntity(string $entityType, string $entityId): array
    {
        return array_filter($this->bindings, function (BindingInterface $binding) use ($entityType, $entityId) {
            return ($binding->getFromType() === $entityType && $binding->getFromId() === $entityId) ||
                   ($binding->getToType() === $entityType && $binding->getToId() === $entityId);
        });
    }

    /**
     * @return BindingInterface[]
     */
    public function findBetweenEntities(
        string $fromType,
        string $fromId,
        string $toType,
        string $toId,
        ?string $bindingType = null
    ): array {
        return array_filter($this->bindings, function (BindingInterface $binding) use ($fromType, $fromId, $toType, $toId, $bindingType) {
            $matches = $binding->getFromType() === $fromType &&
                      $binding->getFromId() === $fromId &&
                      $binding->getToType() === $toType &&
                      $binding->getToId() === $toId;

            if ($bindingType !== null) {
                $matches = $matches && $binding->getType() === $bindingType;
            }

            return $matches;
        });
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function updateMetadata(string $bindingId, array $metadata): void
    {
        if (!isset($this->bindings[$bindingId])) {
            throw new BindingNotFoundException("Binding with ID {$bindingId} not found");
        }

        // For mock purposes, we'll create a new binding with updated metadata
        $binding = $this->bindings[$bindingId];
        $this->bindings[$bindingId] = new \EdgeBinder\Binding(
            $binding->getId(),
            $binding->getFromType(),
            $binding->getFromId(),
            $binding->getToType(),
            $binding->getToId(),
            $binding->getType(),
            $metadata,
            $binding->getCreatedAt(),
            new \DateTimeImmutable() // Updated timestamp
        );
    }

    public function deleteByEntity(string $entityType, string $entityId): int
    {
        $count = 0;
        foreach ($this->bindings as $id => $binding) {
            if (($binding->getFromType() === $entityType && $binding->getFromId() === $entityId) ||
                ($binding->getToType() === $entityType && $binding->getToId() === $entityId)) {
                unset($this->bindings[$id]);
                $count++;
            }
        }
        return $count;
    }

    // Helper method to add test data
    public function addTestBinding(BindingInterface $binding): void
    {
        $this->bindings[$binding->getId()] = $binding;
    }

    // Helper method to get transformer for testing
    public function getTransformer(): CriteriaTransformerInterface
    {
        return $this->transformer;
    }
}

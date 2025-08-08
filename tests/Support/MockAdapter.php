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
use EdgeBinder\Exception\InvalidMetadataException;
use EdgeBinder\Exception\PersistenceException;
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
    private array $bindings = [];
    
    public function __construct(?CriteriaTransformerInterface $transformer = null)
    {
        $this->transformer = $transformer ?? new \EdgeBinder\Tests\Support\MockCriteriaTransformer();
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
     */
    private function simulateQueryExecution(mixed $transformedQuery): array
    {
        // For demonstration, just return some mock bindings
        // In a real adapter, this would execute the transformed query
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
        
        throw new EntityExtractionException('Cannot extract ID from entity');
    }
    
    public function extractEntityType(object $entity): string
    {
        return basename(str_replace('\\', '/', get_class($entity)));
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

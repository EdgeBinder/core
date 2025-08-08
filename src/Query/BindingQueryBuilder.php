<?php

declare(strict_types=1);

namespace EdgeBinder\Query;

use EdgeBinder\Contracts\BindingInterface;
use EdgeBinder\Contracts\PersistenceAdapterInterface;
use EdgeBinder\Contracts\QueryBuilderInterface;
use EdgeBinder\Contracts\QueryResultInterface;
use EdgeBinder\Query\EntityCriteria;
use EdgeBinder\Query\OrderByCriteria;
use EdgeBinder\Query\QueryCriteria;
use EdgeBinder\Query\WhereCriteria;

/**
 * Fluent query builder for constructing binding queries.
 *
 * This class provides a storage-agnostic way to build queries for finding bindings.
 * The query builder is immutable - each method returns a new instance with the
 * additional criteria applied.
 *
 * The builder collects query criteria and delegates execution to the storage adapter,
 * which translates the criteria into its native query format.
 */
readonly class BindingQueryBuilder implements QueryBuilderInterface
{
    /**
     * Create a new query builder instance.
     *
     * @param PersistenceAdapterInterface $storage  Storage adapter for query execution
     * @param array<string, mixed>        $criteria Query criteria
     */
    public function __construct(
        private PersistenceAdapterInterface $storage,
        private array $criteria = [],
    ) {
    }

    public function from(object|string $entity, ?string $entityId = null): static
    {
        if (is_object($entity)) {
            $entityType = $this->storage->extractEntityType($entity);
            $entityId = $this->storage->extractEntityId($entity);
        } else {
            $entityType = $entity;
            if (null === $entityId) {
                throw new \InvalidArgumentException('Entity ID is required when entity is provided as string');
            }
        }

        return $this->withCriteria(['fromType' => $entityType, 'fromId' => $entityId]);
    }

    public function to(object|string $entity, ?string $entityId = null): static
    {
        if (is_object($entity)) {
            $entityType = $this->storage->extractEntityType($entity);
            $entityId = $this->storage->extractEntityId($entity);
        } else {
            $entityType = $entity;
            if (null === $entityId) {
                throw new \InvalidArgumentException('Entity ID is required when entity is provided as string');
            }
        }

        return $this->withCriteria(['toType' => $entityType, 'toId' => $entityId]);
    }

    public function type(string $type): static
    {
        return $this->withCriteria(['type' => $type]);
    }

    public function where(string $field, mixed $operator, mixed $value = null): static
    {
        // If only two arguments provided, treat operator as value and use '=' as operator
        // Use func_num_args() to distinguish between where($field, $value) and where($field, '=', null)
        if (2 === func_num_args()) {
            $value = $operator;
            $operator = '=';
        }

        $whereClause = [
            'field' => $field,
            'operator' => $operator,
            'value' => $value,
        ];

        return $this->addToArray('where', $whereClause);
    }

    public function whereIn(string $field, array $values): static
    {
        $whereClause = [
            'field' => $field,
            'operator' => 'in',
            'value' => $values,
        ];

        return $this->addToArray('where', $whereClause);
    }

    public function whereBetween(string $field, mixed $min, mixed $max): static
    {
        $whereClause = [
            'field' => $field,
            'operator' => 'between',
            'value' => [$min, $max],
        ];

        return $this->addToArray('where', $whereClause);
    }

    public function whereExists(string $field): static
    {
        $whereClause = [
            'field' => $field,
            'operator' => 'exists',
            'value' => true,
        ];

        return $this->addToArray('where', $whereClause);
    }

    public function whereNull(string $field): static
    {
        $whereClause = [
            'field' => $field,
            'operator' => 'null',
            'value' => true,
        ];

        return $this->addToArray('where', $whereClause);
    }

    public function whereNotNull(string $field): static
    {
        $whereClause = [
            'field' => $field,
            'operator' => 'notNull',
            'value' => true,
        ];

        return $this->addToArray('where', $whereClause);
    }

    /**
     * @param array<mixed> $values
     */
    public function whereNotIn(string $field, array $values): static
    {
        $whereClause = [
            'field' => $field,
            'operator' => 'notIn',
            'value' => $values,
        ];

        return $this->addToArray('where', $whereClause);
    }

    public function orWhere(callable $callback): static
    {
        $subQuery = new static($this->storage, []);
        $subQuery = $callback($subQuery);

        // Get the where conditions from the subquery
        $orConditions = $subQuery->getCriteria()['where'] ?? [];

        return $this->addToArray('orWhere', $orConditions);
    }

    public function orderBy(string $field, string $direction = 'asc'): static
    {
        $direction = strtolower($direction);
        if (!in_array($direction, ['asc', 'desc'], true)) {
            throw new \InvalidArgumentException("Order direction must be 'asc' or 'desc', got: {$direction}");
        }

        $orderClause = [
            'field' => $field,
            'direction' => $direction,
        ];

        return $this->addToArray('orderBy', $orderClause);
    }

    public function limit(int $limit): static
    {
        if ($limit < 0) {
            throw new \InvalidArgumentException("Limit must be non-negative, got: {$limit}");
        }

        return $this->withCriteria(['limit' => $limit]);
    }

    public function offset(int $offset): static
    {
        if ($offset < 0) {
            throw new \InvalidArgumentException("Offset must be non-negative, got: {$offset}");
        }

        return $this->withCriteria(['offset' => $offset]);
    }

    public function get(): QueryResultInterface
    {
        $criteria = $this->buildQueryCriteria();
        return $this->storage->executeQuery($criteria);
    }

    public function first(): ?BindingInterface
    {
        $result = $this->limit(1)->get();
        return $result->first();
    }

    public function count(): int
    {
        $criteria = $this->buildQueryCriteria();
        return $this->storage->count($criteria);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function getCriteria(): array
    {
        return $this->criteria;
    }

    /**
     * Build QueryCriteria object from internal criteria array.
     */
    private function buildQueryCriteria(): QueryCriteria
    {
        return new QueryCriteria(
            from: isset($this->criteria['fromType'], $this->criteria['fromId'])
                ? new EntityCriteria($this->criteria['fromType'], $this->criteria['fromId'])
                : null,
            to: isset($this->criteria['toType'], $this->criteria['toId'])
                ? new EntityCriteria($this->criteria['toType'], $this->criteria['toId'])
                : null,
            type: $this->criteria['type'] ?? null,
            where: array_map(
                fn($w) => new WhereCriteria($w['field'], $w['operator'], $w['value']),
                $this->criteria['where'] ?? []
            ),
            orderBy: array_map(
                fn($o) => new OrderByCriteria($o['field'], $o['direction']),
                $this->criteria['orderBy'] ?? []
            ),
            limit: $this->criteria['limit'] ?? null,
            offset: $this->criteria['offset'] ?? null
        );
    }

    /**
     * Create a new instance with additional criteria.
     *
     * @param array<string, mixed> $newCriteria Additional criteria to merge
     *
     * @return static New query builder instance
     */
    private function withCriteria(array $newCriteria): static
    {
        return new static($this->storage, array_merge($this->criteria, $newCriteria));
    }

    /**
     * Add a value to an array field in criteria.
     *
     * @param string $key   Criteria key
     * @param mixed  $value Value to add to array
     *
     * @return static New query builder instance
     */
    private function addToArray(string $key, mixed $value): static
    {
        $criteria = $this->criteria;
        $criteria[$key] ??= [];
        $criteria[$key][] = $value;

        return new static($this->storage, $criteria);
    }
}

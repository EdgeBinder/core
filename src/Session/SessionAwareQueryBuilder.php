<?php

declare(strict_types=1);

namespace EdgeBinder\Session;

use EdgeBinder\Contracts\BindingInterface;
use EdgeBinder\Contracts\PersistenceAdapterInterface;
use EdgeBinder\Contracts\QueryBuilderInterface;
use EdgeBinder\Contracts\QueryResultInterface;
use EdgeBinder\Query\BindingQueryBuilder;
use EdgeBinder\Query\QueryResult;

/**
 * Query builder that merges results from session cache and adapter.
 *
 * Provides immediate read-after-write consistency by combining cached
 * bindings with adapter results and deduplicating the results.
 */
class SessionAwareQueryBuilder implements QueryBuilderInterface
{
    private BindingQueryBuilder $adapterQueryBuilder;
    private QueryCriteria $sessionCriteria;

    public function __construct(
        private readonly PersistenceAdapterInterface $adapter,
        private readonly BindingCache $cache
    ) {
        $this->adapterQueryBuilder = new BindingQueryBuilder($adapter);
        $this->sessionCriteria = new QueryCriteria();
    }

    public function from(object|string $entity, ?string $entityId = null): static
    {
        $clone = clone $this;
        $clone->adapterQueryBuilder = $this->adapterQueryBuilder->from($entity, $entityId);

        // Extract entity ID for session criteria
        if (is_object($entity)) {
            $entityId = $this->adapter->extractEntityId($entity);
        }
        if (null !== $entityId) {
            $clone->sessionCriteria->setFrom($entityId);
        }

        return $clone;
    }

    public function to(object|string $entity, ?string $entityId = null): static
    {
        $clone = clone $this;
        $clone->adapterQueryBuilder = $this->adapterQueryBuilder->to($entity, $entityId);

        // Extract entity ID for session criteria
        if (is_object($entity)) {
            $entityId = $this->adapter->extractEntityId($entity);
        }
        if (null !== $entityId) {
            $clone->sessionCriteria->setTo($entityId);
        }

        return $clone;
    }

    public function type(string $type): static
    {
        $clone = clone $this;
        $clone->adapterQueryBuilder = $this->adapterQueryBuilder->type($type);
        $clone->sessionCriteria->setType($type);

        return $clone;
    }

    public function where(string $field, mixed $operator, mixed $value = null): static
    {
        $clone = clone $this;
        $clone->adapterQueryBuilder = $this->adapterQueryBuilder->where($field, $operator, $value);

        return $clone;
    }

    public function whereIn(string $field, array $values): static
    {
        $clone = clone $this;
        $clone->adapterQueryBuilder = $this->adapterQueryBuilder->whereIn($field, $values);

        return $clone;
    }

    public function whereBetween(string $field, mixed $min, mixed $max): static
    {
        $clone = clone $this;
        $clone->adapterQueryBuilder = $this->adapterQueryBuilder->whereBetween($field, $min, $max);

        return $clone;
    }

    public function whereNotIn(string $field, array $values): static
    {
        $clone = clone $this;
        $clone->adapterQueryBuilder = $this->adapterQueryBuilder->whereNotIn($field, $values);

        return $clone;
    }

    public function whereNull(string $field): static
    {
        $clone = clone $this;
        $clone->adapterQueryBuilder = $this->adapterQueryBuilder->whereNull($field);

        return $clone;
    }

    public function whereNotNull(string $field): static
    {
        $clone = clone $this;
        $clone->adapterQueryBuilder = $this->adapterQueryBuilder->whereNotNull($field);

        return $clone;
    }

    public function whereExists(string $field): static
    {
        $clone = clone $this;
        $clone->adapterQueryBuilder = $this->adapterQueryBuilder->whereExists($field);

        return $clone;
    }

    /**
     * @param callable(static): static $callback
     *
     * @phpstan-ignore-next-line
     */
    public function orWhere(callable $callback): static
    {
        $clone = clone $this;

        // Create a wrapper callback that works with BindingQueryBuilder
        $adapterCallback = function (BindingQueryBuilder $query) use ($callback): BindingQueryBuilder {
            // Create a SessionAwareQueryBuilder wrapper for the callback
            $sessionQuery = new self($this->adapter, $this->cache);
            $sessionQuery->adapterQueryBuilder = $query;

            // Call the original callback with the session query
            /** @var static $result */
            /** @phpstan-ignore-next-line */
            $result = $callback($sessionQuery);

            // Return the underlying adapter query builder
            return $result->adapterQueryBuilder;
        };

        $clone->adapterQueryBuilder = $this->adapterQueryBuilder->orWhere($adapterCallback);

        return $clone;
    }

    public function orderBy(string $field, string $direction = 'asc'): static
    {
        $clone = clone $this;
        $clone->adapterQueryBuilder = $this->adapterQueryBuilder->orderBy($field, $direction);

        return $clone;
    }

    public function limit(int $limit): static
    {
        $clone = clone $this;
        $clone->adapterQueryBuilder = $this->adapterQueryBuilder->limit($limit);

        return $clone;
    }

    public function offset(int $offset): static
    {
        $clone = clone $this;
        $clone->adapterQueryBuilder = $this->adapterQueryBuilder->offset($offset);

        return $clone;
    }

    public function get(): QueryResultInterface
    {
        // Get results from cache
        $cacheResults = $this->cache->findByQuery($this->sessionCriteria);

        // Get results from adapter
        $adapterResults = $this->adapterQueryBuilder->get()->getBindings();

        // Merge and deduplicate
        $mergedResults = $this->mergeResults($cacheResults, $adapterResults);

        return new QueryResult($mergedResults);
    }

    public function first(): ?BindingInterface
    {
        $results = $this->get()->getBindings();

        return $results[0] ?? null;
    }

    public function count(): int
    {
        // For count, we need to merge and deduplicate to get accurate count
        $results = $this->get()->getBindings();

        return count($results);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function getCriteria(): array
    {
        return $this->adapterQueryBuilder->getCriteria();
    }

    /**
     * Merge cache and adapter results, prioritizing cache results and deduplicating.
     *
     * @param array<BindingInterface> $cacheResults
     * @param array<BindingInterface> $adapterResults
     *
     * @return array<BindingInterface>
     */
    private function mergeResults(array $cacheResults, array $adapterResults): array
    {
        $merged = [];
        $seen = [];

        // Add cache results first (they're guaranteed fresh)
        foreach ($cacheResults as $binding) {
            $merged[] = $binding;
            $seen[$binding->getId()] = true;
        }

        // Add adapter results that aren't already in cache
        foreach ($adapterResults as $binding) {
            if (!isset($seen[$binding->getId()])) {
                $merged[] = $binding;
            }
        }

        return $merged;
    }

    /**
     * Clone the session criteria when cloning the query builder.
     */
    public function __clone(): void
    {
        $this->sessionCriteria = clone $this->sessionCriteria;
    }
}

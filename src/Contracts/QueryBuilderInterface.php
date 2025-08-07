<?php

declare(strict_types=1);

namespace EdgeBinder\Contracts;

/**
 * Query builder interface for constructing binding queries.
 *
 * Provides a fluent interface for building queries to find bindings
 * based on various criteria. The query builder is storage-agnostic
 * and gets translated by storage adapters into their native query format.
 *
 * Query builders are immutable - each method returns a new instance
 * with the additional criteria applied.
 */
interface QueryBuilderInterface
{
    /**
     * Filter bindings by source entity.
     *
     * @param object|string $entity   Entity object or type
     * @param string|null   $entityId Entity ID (required if $entity is string)
     *
     * @return static New query builder instance
     */
    public function from(object|string $entity, ?string $entityId = null): static;

    /**
     * Filter bindings by target entity.
     *
     * @param object|string $entity   Entity object or type
     * @param string|null   $entityId Entity ID (required if $entity is string)
     *
     * @return static New query builder instance
     */
    public function to(object|string $entity, ?string $entityId = null): static;

    /**
     * Filter bindings by type.
     *
     * @param string $type The binding type to filter by
     *
     * @return static New query builder instance
     */
    public function type(string $type): static;

    /**
     * Filter bindings by metadata field value.
     *
     * @param string $field    The metadata field name
     * @param mixed  $operator The comparison operator or value (if no operator)
     * @param mixed  $value    The value to compare against (if operator provided)
     *
     * @return static New query builder instance
     */
    public function where(string $field, mixed $operator, mixed $value = null): static;

    /**
     * Filter bindings where metadata field is in a list of values.
     *
     * @param string       $field  The metadata field name
     * @param array<mixed> $values Array of values to match
     *
     * @return static New query builder instance
     */
    public function whereIn(string $field, array $values): static;

    /**
     * Filter bindings where metadata field is between two values.
     *
     * @param string $field The metadata field name
     * @param mixed  $min   Minimum value (inclusive)
     * @param mixed  $max   Maximum value (inclusive)
     *
     * @return static New query builder instance
     */
    public function whereBetween(string $field, mixed $min, mixed $max): static;

    /**
     * Filter bindings where metadata field exists.
     *
     * @param string $field The metadata field name
     *
     * @return static New query builder instance
     */
    public function whereExists(string $field): static;

    /**
     * Filter bindings where metadata field is null or doesn't exist.
     *
     * @param string $field The metadata field name
     *
     * @return static New query builder instance
     */
    public function whereNull(string $field): static;

    /**
     * Filter bindings where metadata field is not null and exists.
     *
     * @param string $field The metadata field name
     *
     * @return static New query builder instance
     */
    public function whereNotNull(string $field): static;

    /**
     * Filter bindings where field value is not in the given array.
     *
     * @param string       $field  The field name
     * @param array<mixed> $values Array of values to exclude
     *
     * @return static New query builder instance
     */
    public function whereNotIn(string $field, array $values): static;

    /**
     * Add OR condition group.
     *
     * @param callable(static): static $callback Callback to build OR conditions
     *
     * @return static New query builder instance
     */
    public function orWhere(callable $callback): static;

    /**
     * Order results by a field.
     *
     * @param string $field     The field to order by (metadata field or binding property)
     * @param string $direction 'asc' or 'desc'
     *
     * @return static New query builder instance
     */
    public function orderBy(string $field, string $direction = 'asc'): static;

    /**
     * Limit the number of results.
     *
     * @param int $limit Maximum number of results
     *
     * @return static New query builder instance
     */
    public function limit(int $limit): static;

    /**
     * Skip a number of results (pagination).
     *
     * @param int $offset Number of results to skip
     *
     * @return static New query builder instance
     */
    public function offset(int $offset): static;

    /**
     * Execute the query and return all matching bindings.
     *
     * @return BindingInterface[] Array of matching bindings
     */
    public function get(): array;

    /**
     * Execute the query and return the first matching binding.
     *
     * @return BindingInterface|null First matching binding or null
     */
    public function first(): ?BindingInterface;

    /**
     * Execute the query and return the count of matching bindings.
     *
     * @return int Number of matching bindings
     */
    public function count(): int;

    /**
     * Check if any bindings match the query.
     *
     * @return bool True if at least one binding matches
     */
    public function exists(): bool;

    /**
     * Get the query criteria for storage adapter execution.
     *
     * Returns the internal query state that storage adapters can use
     * to build their native queries.
     *
     * @return array<string, mixed> Query criteria
     */
    public function getCriteria(): array;
}

<?php

declare(strict_types=1);

namespace EdgeBinder\Contracts;

/**
 * Interface for query results.
 *
 * Provides access to binding results and metadata about the query execution.
 *
 * @extends \IteratorAggregate<int, BindingInterface>
 */
interface QueryResultInterface extends \Countable, \IteratorAggregate
{
    /**
     * Get the binding results.
     *
     * @return BindingInterface[]
     */
    public function getBindings(): array;

    /**
     * Check if the result set is empty.
     */
    public function isEmpty(): bool;

    /**
     * Get the first binding from the results.
     */
    public function first(): ?BindingInterface;
}

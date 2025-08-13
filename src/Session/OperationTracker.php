<?php

declare(strict_types=1);

namespace EdgeBinder\Session;

use EdgeBinder\Contracts\BindingInterface;

/**
 * Tracks operations performed within a session for flush management.
 */
class OperationTracker
{
    /** @var array<Operation> */
    private array $pendingOperations = [];

    /** @var array<Operation> */
    private array $completedOperations = [];

    /**
     * Record a create operation.
     */
    public function recordCreate(BindingInterface $binding): void
    {
        $this->pendingOperations[] = Operation::create($binding);
    }

    /**
     * Record a delete operation.
     */
    public function recordDelete(BindingInterface $binding): void
    {
        $this->pendingOperations[] = Operation::delete($binding);
    }

    /**
     * Record an update operation.
     */
    public function recordUpdate(BindingInterface $binding): void
    {
        $this->pendingOperations[] = Operation::update($binding);
    }

    /**
     * Get all pending operations.
     *
     * @return array<Operation>
     */
    public function getPendingOperations(): array
    {
        return $this->pendingOperations;
    }

    /**
     * Get all completed operations.
     *
     * @return array<Operation>
     */
    public function getCompletedOperations(): array
    {
        return $this->completedOperations;
    }

    /**
     * Mark all pending operations as complete.
     */
    public function markAllComplete(): void
    {
        $this->completedOperations = array_merge($this->completedOperations, $this->pendingOperations);
        $this->pendingOperations = [];
    }

    /**
     * Mark a specific operation as complete.
     */
    public function markComplete(Operation $operation): void
    {
        $key = array_search($operation, $this->pendingOperations, true);
        if (false !== $key) {
            unset($this->pendingOperations[$key]);
            $this->completedOperations[] = $operation;
            $this->pendingOperations = array_values($this->pendingOperations);
        }
    }

    /**
     * Check if there are pending operations.
     */
    public function hasPendingOperations(): bool
    {
        return !empty($this->pendingOperations);
    }

    /**
     * Clear all operations.
     */
    public function clear(): void
    {
        $this->pendingOperations = [];
        $this->completedOperations = [];
    }

    /**
     * Get the total number of operations (pending + completed).
     */
    public function getTotalOperationCount(): int
    {
        return count($this->pendingOperations) + count($this->completedOperations);
    }
}

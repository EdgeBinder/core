<?php

declare(strict_types=1);

namespace EdgeBinder\Session;

/**
 * Simple query criteria for session cache filtering.
 *
 * This is a lightweight version of the main QueryCriteria class
 * specifically for session cache operations.
 */
class QueryCriteria
{
    private ?string $from = null;
    private ?string $to = null;
    private ?string $type = null;

    /**
     * Set the from entity ID filter.
     */
    public function setFrom(string $fromId): void
    {
        $this->from = $fromId;
    }

    /**
     * Set the to entity ID filter.
     */
    public function setTo(string $toId): void
    {
        $this->to = $toId;
    }

    /**
     * Set the binding type filter.
     */
    public function setType(string $type): void
    {
        $this->type = $type;
    }

    /**
     * Check if from filter is set.
     */
    public function hasFrom(): bool
    {
        return null !== $this->from;
    }

    /**
     * Check if to filter is set.
     */
    public function hasTo(): bool
    {
        return null !== $this->to;
    }

    /**
     * Check if type filter is set.
     */
    public function hasType(): bool
    {
        return null !== $this->type;
    }

    /**
     * Get the from entity ID filter.
     */
    public function getFrom(): ?string
    {
        return $this->from;
    }

    /**
     * Get the to entity ID filter.
     */
    public function getTo(): ?string
    {
        return $this->to;
    }

    /**
     * Get the binding type filter.
     */
    public function getType(): ?string
    {
        return $this->type;
    }
}

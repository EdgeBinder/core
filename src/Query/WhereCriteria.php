<?php

declare(strict_types=1);

namespace EdgeBinder\Query;

/**
 * Immutable where criteria for binding queries.
 * 
 * Represents a single where condition for filtering bindings.
 */
readonly class WhereCriteria
{
    public function __construct(
        public string $field,
        public string $operator,
        public mixed $value
    ) {}
}

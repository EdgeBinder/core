<?php

declare(strict_types=1);

namespace EdgeBinder\Query;

/**
 * Immutable order by criteria for binding queries.
 * 
 * Represents ordering criteria for sorting binding results.
 */
readonly class OrderByCriteria
{
    public function __construct(
        public string $field,
        public string $direction = 'asc'
    ) {}
}

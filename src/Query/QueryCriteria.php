<?php

declare(strict_types=1);

namespace EdgeBinder\Query;

/**
 * Immutable query criteria for binding queries.
 * 
 * This class represents the complete set of criteria for querying bindings
 * in a storage-agnostic way. Adapters convert these criteria to their
 * native query format.
 */
readonly class QueryCriteria
{
    /**
     * @param EntityCriteria|null $from Entity to query bindings from
     * @param EntityCriteria|null $to Entity to query bindings to  
     * @param string|null $type Binding type filter
     * @param WhereCriteria[] $where Where conditions
     * @param OrderByCriteria[] $orderBy Ordering criteria
     * @param int|null $limit Maximum number of results
     * @param int|null $offset Number of results to skip
     */
    public function __construct(
        public ?EntityCriteria $from = null,
        public ?EntityCriteria $to = null,
        public ?string $type = null,
        public array $where = [],
        public array $orderBy = [],
        public ?int $limit = null,
        public ?int $offset = null
    ) {}
}

<?php

declare(strict_types=1);

namespace EdgeBinder\Query;

use EdgeBinder\Contracts\CriteriaTransformerInterface;

/**
 * Immutable query criteria for binding queries.
 *
 * This class represents the complete set of criteria for querying bindings
 * in a storage-agnostic way. Can transform itself using adapter-specific
 * transformers by orchestrating the transformation of all child criteria.
 */
class QueryCriteria
{
    private mixed $transformedValue = null;
    private mixed $lastTransformer = null;

    /**
     * @param EntityCriteria|null         $from    Entity to query bindings from
     * @param EntityCriteria|null         $to      Entity to query bindings to
     * @param string|null                 $type    Binding type filter
     * @param array<WhereCriteria>        $where   Where conditions
     * @param array<array<WhereCriteria>> $orWhere OR condition groups
     * @param array<OrderByCriteria>      $orderBy Ordering criteria
     * @param int|null                    $limit   Maximum number of results
     * @param int|null                    $offset  Number of results to skip
     */
    public function __construct(
        public readonly ?EntityCriteria $from = null,
        public readonly ?EntityCriteria $to = null,
        public readonly ?string $type = null,
        public readonly array $where = [],
        public readonly array $orWhere = [],
        public readonly array $orderBy = [],
        public readonly ?int $limit = null,
        public readonly ?int $offset = null
    ) {
    }

    /**
     * Transform this query criteria using the provided transformer.
     *
     * Orchestrates the transformation of all child criteria and combines
     * them into a final adapter-specific query object.
     *
     * @param CriteriaTransformerInterface $transformer The transformer to use
     *
     * @return mixed Adapter-specific query object
     */
    public function transform(CriteriaTransformerInterface $transformer): mixed
    {
        // Cache based on transformer instance to avoid re-transformation
        if (null === $this->transformedValue || $this->lastTransformer !== $transformer) {
            $this->transformedValue = $this->doTransform($transformer);
            $this->lastTransformer = $transformer;
        }

        return $this->transformedValue;
    }

    /**
     * Perform the actual transformation by orchestrating child criteria transformations.
     *
     * @param CriteriaTransformerInterface $transformer The transformer to use
     *
     * @return mixed Transformed representation
     */
    protected function doTransform(CriteriaTransformerInterface $transformer): mixed
    {
        $filters = [];

        // Transform entity criteria
        if ($this->from) {
            $filters[] = $this->from->transform($transformer, 'from');
        }

        if ($this->to) {
            $filters[] = $this->to->transform($transformer, 'to');
        }

        // Transform binding type filter
        if ($this->type) {
            $filters[] = $transformer->transformBindingType($this->type);
        }

        // Transform where conditions
        foreach ($this->where as $condition) {
            $filters[] = $condition->transform($transformer);
        }

        // Transform OR where conditions
        $orFilters = [];
        foreach ($this->orWhere as $orGroup) {
            $orGroupFilters = [];
            foreach ($orGroup as $condition) {
                $orGroupFilters[] = $condition->transform($transformer);
            }
            $orFilters[] = $orGroupFilters;
        }

        // Transform order by conditions
        foreach ($this->orderBy as $orderBy) {
            $filters[] = $orderBy->transform($transformer);
        }

        // Let transformer combine all filters and handle pagination
        $result = $transformer->combineFilters($filters, $orFilters);

        // Add limit and offset if present
        if (is_array($result)) {
            if (null !== $this->limit) {
                $result['limit'] = $this->limit;
            }
            if (null !== $this->offset) {
                $result['offset'] = $this->offset;
            }
        }

        return $result;
    }
}

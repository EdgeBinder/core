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
     * @param EntityCriteria|null $from Entity to query bindings from
     * @param EntityCriteria|null $to Entity to query bindings to
     * @param string|null $type Binding type filter
     * @param WhereCriteria[] $where Where conditions
     * @param OrderByCriteria[] $orderBy Ordering criteria
     * @param int|null $limit Maximum number of results
     * @param int|null $offset Number of results to skip
     */
    public function __construct(
        public readonly ?EntityCriteria $from = null,
        public readonly ?EntityCriteria $to = null,
        public readonly ?string $type = null,
        public readonly array $where = [],
        public readonly array $orderBy = [],
        public readonly ?int $limit = null,
        public readonly ?int $offset = null
    ) {}

    /**
     * Transform this query criteria using the provided transformer.
     *
     * Orchestrates the transformation of all child criteria and combines
     * them into a final adapter-specific query object.
     *
     * @param CriteriaTransformerInterface $transformer The transformer to use
     * @return mixed Adapter-specific query object
     */
    public function transform(CriteriaTransformerInterface $transformer): mixed
    {
        // Cache based on transformer instance to avoid re-transformation
        if ($this->transformedValue === null || $this->lastTransformer !== $transformer) {
            $this->transformedValue = $this->doTransform($transformer);
            $this->lastTransformer = $transformer;
        }

        return $this->transformedValue;
    }

    /**
     * Perform the actual transformation by orchestrating child criteria transformations.
     *
     * @param CriteriaTransformerInterface $transformer The transformer to use
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

        // Let transformer combine all filters
        return $transformer->combineFilters($filters);
    }
}

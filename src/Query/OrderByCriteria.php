<?php

declare(strict_types=1);

namespace EdgeBinder\Query;

use EdgeBinder\Contracts\CriteriaTransformerInterface;

/**
 * Immutable order by criteria for binding queries.
 *
 * Represents ordering criteria for sorting binding results.
 * Can transform itself using adapter-specific transformers.
 */
class OrderByCriteria
{
    private mixed $transformedValue = null;
    private mixed $lastTransformer = null;

    public function __construct(
        public readonly string $field,
        public readonly string $direction = 'asc'
    ) {}

    /**
     * Transform this order by criteria using the provided transformer.
     *
     * @param CriteriaTransformerInterface $transformer The transformer to use
     * @return mixed Adapter-specific representation of this order by criteria
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
     * Perform the actual transformation by delegating to the transformer.
     *
     * @param CriteriaTransformerInterface $transformer The transformer to use
     * @return mixed Transformed representation
     */
    protected function doTransform(CriteriaTransformerInterface $transformer): mixed
    {
        return $transformer->transformOrderBy($this);
    }
}

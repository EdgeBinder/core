<?php

declare(strict_types=1);

namespace EdgeBinder\Query;

use EdgeBinder\Contracts\CriteriaTransformerInterface;

/**
 * Immutable where criteria for binding queries.
 *
 * Represents a single where condition for filtering bindings.
 * Can transform itself using adapter-specific transformers.
 */
class WhereCriteria
{
    private mixed $transformedValue = null;
    private mixed $lastTransformer = null;

    public function __construct(
        public readonly string $field,
        public readonly string $operator,
        public readonly mixed $value
    ) {
    }

    /**
     * Transform this where criteria using the provided transformer.
     *
     * @param CriteriaTransformerInterface $transformer The transformer to use
     *
     * @return mixed Adapter-specific representation of this where criteria
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
     * Perform the actual transformation by delegating to the transformer.
     *
     * @param CriteriaTransformerInterface $transformer The transformer to use
     *
     * @return mixed Transformed representation
     */
    protected function doTransform(CriteriaTransformerInterface $transformer): mixed
    {
        return $transformer->transformWhere($this);
    }
}
